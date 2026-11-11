<?php

namespace App\Modules\Licensing\Services;

use App\Models\User;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceApplicationDocument;
use App\Modules\Licensing\Models\LicenceCertificate;
use App\Modules\Licensing\Models\LicenceInspection;
use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Licensing\Models\LicenceTypeVersion;
use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\PaymentService;
use App\Modules\Revenue\Services\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Versioned municipal licence workflows. Configuration is snapshotted at
 * submission, citizen access is linked-account based, and every consequential
 * transition is explicit and audited.
 */
class LicensingService
{
    public function __construct(
        private readonly AccountLinkService $links,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
        private readonly TenantManager $tenants,
        private readonly PaymentService $payments,
        private readonly PermissionService $permissions,
        private readonly RefundService $refunds,
        private readonly LicenceChargeService $charges,
        private readonly LicenceNotificationService $notifications,
    ) {}

    public function seedDemonstrationType(?User $user = null): LicenceType
    {
        if ($user) {
            $this->permissions->authorize('licensing.types.manage');
        }
        if (! $this->tenants->requireCouncil()->is_demo) {
            throw new \DomainException('Demonstration configurations may only be seeded into a council explicitly marked as a demo.');
        }

        return DB::connection('tenant')->transaction(function () use ($user) {
            $type = LicenceType::firstOrCreate(['code' => 'DEMO_TRADER'], [
                'name' => 'Demonstration trading licence',
                'status' => 'published',
                'configuration_authority' => 'Demonstration configuration only; no statutory authority asserted',
                'effective_from' => now()->toDateString(),
                'created_by' => $user?->getKey(),
            ]);

            if (! $type->versions()->exists()) {
                LicenceTypeVersion::create([
                    'licence_type_id' => $type->id,
                    'version' => 1,
                    'approval_status' => 'demonstration',
                    'created_by' => $user?->getKey(),
                    'configuration' => [
                        'label' => 'DEMONSTRATION ONLY — not an approved council or statutory configuration',
                        'authority' => 'Demonstration only',
                        'application_fields' => [
                            ['name' => 'trading_name', 'label' => 'Trading name', 'required' => true, 'max_length' => 160],
                            ['name' => 'activity', 'label' => 'Proposed activity', 'required' => true, 'max_length' => 500],
                        ],
                        'required_documents' => [['code' => 'identity', 'label' => 'Identity document'], ['code' => 'premises', 'label' => 'Premises evidence']],
                        'eligibility' => ['description' => 'Demonstration review only; no legal eligibility conclusion.'],
                        'inspection' => ['required' => true, 'description' => 'Demonstration inspection.'],
                        'charges' => ['application_minor' => 2500, 'inspection_minor' => 0, 'issuance_minor' => 0, 'renewal_minor' => 2500, 'currency' => 'USD', 'refundable' => false, 'recognition' => 'immediate demonstration policy only'],
                        'approval_stages' => ['review', 'inspection', 'approval', 'issuance'],
                        'delegated_authority' => ['description' => 'Not approved; demonstration only', 'approval_threshold_minor' => null],
                        'validity_days' => 365,
                        'renewal_window_days' => 30,
                        'renewal_validity_anchor' => 'decision_date',
                        'expiry_rules' => ['timezone' => 'Africa/Harare', 'local_date_boundary' => 'expires through the stated local date', 'renewal_after_expiry_allowed' => false],
                        'conditions' => [],
                        'notification_days' => [30, 7, 1],
                        'certificate_template' => 'demo-council-licence-v1',
                        'allow_concurrent_attempts' => false,
                        'appeal' => ['enabled' => false],
                        'refund_policy' => ['refundable' => false, 'reason_required' => true],
                        'revenue_recognition' => ['charge_type' => 'licence_application', 'method' => 'immediate', 'demonstration_only' => true],
                    ],
                ]);
            }

            $this->audit->record(action: 'licensing.demo_type.seeded', subject: $type, after: ['code' => $type->code], module: 'licensing');

            return $type->load('versions');
        });
    }

    public function createType(array $data, User $user): LicenceType
    {
        $this->permissions->authorize('licensing.types.manage');
        $this->validateConfiguration($data['configuration']);
        if (empty($data['authority']) || empty($data['effective_from']) || empty($data['approval_status'])) {
            throw new \DomainException('Authority, effective date and approval status are required configuration metadata.');
        }
        if (($data['configuration']['appeal']['enabled'] ?? false) === true) {
            throw new \DomainException('Appeal workflow is not implemented. Keep appeal disabled until an approved council appeal process is configured.');
        }
        if (($data['approval_status'] ?? null) === 'demonstration' && ! $this->tenants->requireCouncil()->is_demo) {
            throw new \DomainException('Demonstration configurations cannot be designated for a non-demo council.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $user) {
            $type = LicenceType::create([
                'code' => Str::upper($data['code']), 'name' => $data['name'], 'status' => $data['status'] ?? 'draft',
                'configuration_authority' => $data['authority'], 'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null, 'created_by' => $user->getKey(),
            ]);
            LicenceTypeVersion::create([
                'licence_type_id' => $type->id, 'version' => 1, 'configuration' => $data['configuration'],
                'approval_status' => $data['approval_status'], 'created_by' => $user->getKey(),
            ]);
            $this->audit->record(action: 'licensing.type.created', subject: $type, after: ['code' => $type->code, 'configuration_status' => $data['approval_status']], module: 'licensing');

            return $type->load('versions');
        });
    }

    public function reviseType(LicenceType $type, array $data, User $user): LicenceTypeVersion
    {
        $this->permissions->authorize('licensing.types.manage');
        $this->validateConfiguration($data['configuration']);
        if (($data['configuration']['appeal']['enabled'] ?? false) === true) {
            throw new \DomainException('Appeal workflow is not implemented. Keep appeal disabled until an approved council appeal process is configured.');
        }
        if (($data['approval_status'] ?? null) === 'demonstration' && ! $this->tenants->requireCouncil()->is_demo) {
            throw new \DomainException('Demonstration configurations cannot be designated for a non-demo council.');
        }

        return DB::connection('tenant')->transaction(function () use ($type, $data, $user) {
            $locked = LicenceType::query()->lockForUpdate()->findOrFail($type->id);
            $latest = $locked->versions()->first();
            $version = LicenceTypeVersion::create([
                'licence_type_id' => $locked->id, 'version' => ((int) ($latest?->version ?? 0)) + 1,
                'configuration' => $data['configuration'], 'approval_status' => $data['approval_status'],
                'created_by' => $user->getKey(),
            ]);
            $locked->forceFill([
                'name' => $data['name'] ?? $locked->name,
                'status' => $data['status'] ?? $locked->status,
                'configuration_authority' => $data['authority'] ?? $locked->configuration_authority,
                'effective_from' => $data['effective_from'] ?? $locked->effective_from,
                'effective_to' => $data['effective_to'] ?? $locked->effective_to,
            ])->save();
            $this->audit->record(action: 'licensing.type.versioned', subject: $version, after: ['type' => $locked->code, 'version' => $version->version, 'approval_status' => $version->approval_status], module: 'licensing');

            return $version;
        });
    }

    public function approveTypeVersion(LicenceTypeVersion $version, User $user): LicenceTypeVersion
    {
        $this->permissions->authorize('licensing.types.approve');

        return DB::connection('tenant')->transaction(function () use ($version, $user) {
            $locked = LicenceTypeVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->created_by === $user->getKey()) {
                throw new \DomainException('Separation of duties: a configuration author cannot approve their own version.');
            }
            if (! in_array($locked->approval_status, ['draft', 'pending'], true)) {
                throw new \DomainException('Only draft licence configurations can be approved.');
            }
            $type = LicenceType::query()->lockForUpdate()->findOrFail($locked->licence_type_id);
            if (! $type->effective_from || $type->effective_from->isFuture()) {
                throw new \DomainException('Set an effective date that has begun before approving this configuration.');
            }
            $locked->forceFill(['approval_status' => 'approved', 'approved_by' => $user->getKey(), 'approved_at' => now()])->save();
            $type->forceFill(['status' => 'published', 'approved_by' => $user->getKey(), 'approved_at' => now()])->save();
            $this->audit->record(action: 'licensing.type.version_approved', subject: $locked, after: ['type' => $type->code, 'version' => $locked->version, 'authority' => $type->configuration_authority], module: 'licensing');

            return $locked;
        });
    }

    public function saveDraft(User $user, string $accountId, string $typeCode, array $form, ?string $applicationId = null, ?string $licenceId = null): LicenceApplication
    {
        $account = $this->links->requireAccount($user, $accountId);
        $type = $this->publicType($typeCode);
        $version = $this->effectiveVersion($type);
        $this->validateForm($version->configuration, $form, false);
        if ($licenceId) {
            $licence = Licence::where('customer_account_id', $account->id)->where('licence_type_id', $type->id)->findOrFail($licenceId);
            $this->assertRenewalEligible($licence, $version->configuration);
        }

        if ($applicationId) {
            $application = LicenceApplication::where('customer_account_id', $account->id)->findOrFail($applicationId);
            if ($application->submitted_at) {
                throw new \DomainException('Submitted application details are immutable. Start a new attempt for amendments.');
            }
            $application->form_data = $form;
            $application->holder_name = $account->primaryParty?->display_name ?? $account->account_number;
            $application->save();

            return $application;
        }

        return LicenceApplication::firstOrCreate([
            'customer_account_id' => $account->id,
            'idempotency_key' => 'draft:'.Str::uuid(),
        ], [
            'reference' => $this->sequences->next('licence_application', 'LICAPP', 7),
            'licence_type_id' => $type->id,
            'licence_type_version_id' => $version->id,
            'submitted_by' => $user->getKey(),
            'licence_id' => $licenceId,
            'attempt_type' => $licenceId ? 'renewal' : 'application',
            'status' => 'draft',
            'configuration_snapshot' => $version->configuration,
            'form_data' => $form,
            'holder_name' => $account->primaryParty?->display_name ?? $account->account_number,
            'fee_currency' => $account->currency_code,
        ]);
    }

    public function submit(User $user, string $accountId, string $typeCode, array $form, string $idempotencyKey, ?string $applicationId = null): LicenceApplication
    {
        $account = $this->links->requireAccount($user, $accountId);

        return DB::connection('tenant')->transaction(function () use ($user, $account, $typeCode, $form, $idempotencyKey, $applicationId) {
            $existing = LicenceApplication::where('customer_account_id', $account->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing?->submitted_at) {
                return $existing->load('documents');
            }
            $type = $this->publicType($typeCode);
            $version = $this->effectiveVersion($type);
            $this->validateForm($version->configuration, $form);
            $draft = $applicationId ? LicenceApplication::where('customer_account_id', $account->id)->findOrFail($applicationId) : null;
            $licence = $draft?->licence_id;
            $attemptType = $licence ? 'renewal' : 'application';
            DB::connection('tenant')->selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ['licence-submit:'.$account->id.':'.$type->id.':'.($licence ?? 'new')]);
            $existing = LicenceApplication::where('customer_account_id', $account->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing?->submitted_at) {
                return $existing->load('documents');
            }
            if ($licence) {
                $licence = Licence::query()->lockForUpdate()->findOrFail($licence);
                $config = $version->configuration;
                $this->assertRenewalEligible($licence, $config);
            }
            if (! ($version->configuration['allow_concurrent_attempts'] ?? false)) {
                $query = LicenceApplication::query()->where('licence_type_id', $type->id)->where('customer_account_id', $account->id)
                    ->whereIn('status', ['submitted', 'information_requested', 'under_review', 'inspection_pending', 'approved', 'ready_to_issue']);
                if ($licence) {
                    $query->where('licence_id', $licence->id);
                }
                if ($query->exists()) {
                    throw new \DomainException('An active application attempt already exists for this licence and type.');
                }
            }
            $this->validateRequiredDocuments($version->configuration, $applicationId);
            $chargeStage = $attemptType === 'renewal' ? 'renewal' : 'application';
            $chargeKey = $attemptType === 'renewal' ? 'renewal_minor' : 'application_minor';
            $amount = (int) ($version->configuration['charges'][$chargeKey] ?? 0);
            if ($amount < 0) {
                throw new \DomainException('Licence charges cannot be negative.');
            }
            $application = $existing ?? ($draft ?? new LicenceApplication);
            $application->fill([
                'reference' => $application->reference ?: $this->sequences->next('licence_application', 'LICAPP', 7),
                'licence_id' => $licence?->id,
                'licence_type_id' => $type->id, 'licence_type_version_id' => $version->id,
                'customer_account_id' => $account->id, 'submitted_by' => $user->getKey(),
                'attempt_type' => $attemptType, 'status' => 'submitted', 'idempotency_key' => $idempotencyKey,
                'configuration_snapshot' => $version->configuration, 'form_data' => $form,
                'holder_name' => $account->primaryParty?->display_name ?? $account->account_number,
                'fee_currency' => $account->currency_code, 'fee_amount_minor' => $amount,
                'fee_bill_id' => null, 'submitted_at' => now(),
            ])->save();
            $charge = $amount > 0 ? $this->charges->assess($application, $chargeStage, $amount, $user) : null;
            if ($charge) {
                $application->forceFill(['fee_bill_id' => $charge->bill_id])->save();
            }
            $this->queueNotification($application, null, 'application_submitted');
            $this->audit->record(action: 'licensing.application.submitted', subject: $application, after: ['reference' => $application->reference, 'type' => $type->code, 'version' => $version->version, 'fee_minor' => $amount], module: 'licensing');

            return $application->load('documents');
        });
    }

    public function attachDocument(User $user, string $accountId, LicenceApplication $application, Document $document, string $documentType): LicenceApplicationDocument
    {
        $this->links->requireAccount($user, $accountId);
        if ($application->customer_account_id !== $accountId || ($application->submitted_at && $application->status !== 'information_requested')) {
            abort(404);
        }
        if ($document->uploaded_by !== $user->getKey() || $document->status !== 'available') {
            abort(404);
        }
        $previous = LicenceApplicationDocument::where('licence_application_id', $application->id)->where('document_type', $documentType)->orderByDesc('version')->first();

        return DB::connection('tenant')->transaction(function () use ($user, $application, $document, $documentType, $previous) {
            $link = LicenceApplicationDocument::create([
                'licence_application_id' => $application->id, 'document_id' => $document->id,
                'document_type' => $documentType, 'version' => ((int) ($previous?->version ?? 0)) + 1,
                'supersedes_id' => $previous?->id, 'uploaded_by' => $user->getKey(),
            ]);
            $this->audit->record(action: 'licensing.application.document_added', subject: $link, after: ['type' => $documentType, 'version' => $link->version, 'sha256' => $document->sha256], module: 'licensing');

            return $link;
        });
    }

    public function respondToInformation(User $user, string $accountId, LicenceApplication $application, array $form): LicenceApplication
    {
        $this->links->requireAccount($user, $accountId);

        return DB::connection('tenant')->transaction(function () use ($user, $accountId, $application, $form) {
            $locked = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($locked->customer_account_id !== $accountId || $locked->submitted_by !== $user->getKey()) {
                abort(404);
            }
            if ($locked->status !== 'information_requested') {
                throw new \DomainException('The council has not requested information for this application.');
            }
            $this->validateForm($locked->configuration_snapshot, $form);
            $this->validateRequiredDocuments($locked->configuration_snapshot, $locked->id);
            $locked->forceFill(['form_data' => $form, 'status' => 'submitted', 'reviewed_by' => null])->save();
            $this->audit->record(action: 'licensing.application.information_responded', subject: $locked, after: ['status' => 'submitted'], module: 'licensing');

            return $locked;
        });
    }

    public function requestInformation(LicenceApplication $application, string $message, User $user): LicenceApplication
    {
        return $this->transition($application, ['submitted', 'under_review', 'inspection_pending'], 'information_requested', $user, $message, 'licensing.application.information_requested');
    }

    public function review(LicenceApplication $application, User $user): LicenceApplication
    {
        $this->permissions->authorize('licensing.applications.review');

        return DB::connection('tenant')->transaction(function () use ($application, $user) {
            $application = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->submitted_by === $user->getKey()) {
                throw new \DomainException('Separation of duties: an applicant cannot review their own application.');
            }
            if (! in_array($application->status, ['submitted', 'information_requested'], true)) {
                throw new \DomainException('This application is not ready for review.');
            }
            $application->status = 'under_review';
            $application->reviewed_by = $user->getKey();
            $application->save();
            $this->audit->record(action: 'licensing.application.review_started', subject: $application, after: ['status' => $application->status], module: 'licensing');

            return $application;
        });
    }

    public function assignInspection(LicenceApplication $application, User $inspector, ?string $scheduledAt): LicenceInspection
    {
        $this->permissions->authorize('licensing.inspections.manage');
        $activeInspector = $inspector->activeMemberships()->where('council_id', $this->tenants->requireCouncil()->id)->where('member_type', 'staff')->exists();
        if (! $activeInspector) {
            throw new \DomainException('The selected inspector must be an active staff member of this council.');
        }

        return DB::connection('tenant')->transaction(function () use ($application, $inspector, $scheduledAt) {
            $application = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->status !== 'under_review') {
                throw new \DomainException('An application must be under review before inspection is assigned.');
            }
            if ($application->submitted_by === $inspector->getKey()) {
                throw new \DomainException('The applicant cannot inspect their own application.');
            }
            if ($application->inspections()->where('status', 'assigned')->exists()) {
                throw new \DomainException('An inspection is already assigned to this application.');
            }
            $inspectionCharge = (int) ($application->configuration_snapshot['charges']['inspection_minor'] ?? 0);
            if ($inspectionCharge > 0) {
                $this->charges->assess($application, 'inspection', $inspectionCharge, $inspector);
            }
            $inspection = LicenceInspection::create(['licence_application_id' => $application->id, 'assigned_to' => $inspector->getKey(), 'scheduled_at' => $scheduledAt, 'status' => 'assigned']);
            $application->status = 'inspection_pending';
            $application->save();
            $this->audit->record(action: 'licensing.inspection.assigned', subject: $inspection, after: ['application' => $application->reference, 'inspector' => $inspector->getKey()], module: 'licensing');

            return $inspection;
        });
    }

    public function completeInspection(LicenceInspection $inspection, array $findings, string $result, User $user): LicenceInspection
    {
        $this->permissions->authorize('licensing.inspections.manage');

        return DB::connection('tenant')->transaction(function () use ($inspection, $findings, $result, $user) {
            $inspection = LicenceInspection::query()->lockForUpdate()->findOrFail($inspection->id);
            if ($inspection->assigned_to !== $user->getKey()) {
                throw new \DomainException('Only the assigned inspector may record the inspection.');
            }
            if ($inspection->status !== 'assigned') {
                throw new \DomainException('This inspection is already completed.');
            }
            $this->charges->assertStageSettled($inspection->application, 'inspection_completion');
            $inspection->forceFill(['findings' => $findings, 'result' => $result, 'status' => 'completed', 'completed_by' => $user->getKey(), 'completed_at' => now()])->save();
            $this->audit->record(action: 'licensing.inspection.completed', subject: $inspection, after: ['result' => $result, 'findings' => $findings], module: 'licensing');

            return $inspection;
        });
    }

    public function decide(LicenceApplication $application, string $decision, string $reason, array $conditions, User $user): LicenceApplication
    {
        $this->permissions->authorize('licensing.applications.approve');
        if (! in_array($decision, ['approved', 'rejected'], true) || trim($reason) === '') {
            throw new \DomainException('Choose an allowed decision and record its reason.');
        }

        return DB::connection('tenant')->transaction(function () use ($application, $decision, $reason, $conditions, $user) {
            $locked = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($locked->submitted_by === $user->getKey()) {
                throw new \DomainException('Separation of duties: the applicant cannot decide their own application.');
            }
            if (! in_array($locked->status, ['under_review', 'inspection_pending'], true)) {
                throw new \DomainException('This application is not ready for a decision.');
            }
            if (! $locked->charges()->whereIn('stage', ['application', 'renewal'])->exists() && $locked->fee_amount_minor > 0
                && (! $locked->fee_bill_id || Bill::find($locked->fee_bill_id)?->balance_minor !== 0)) {
                throw new \DomainException('The legacy application fee must be fully paid and allocated before a decision.');
            }
            $this->charges->assertStageSettled($locked, 'decision');
            $config = $locked->configuration_snapshot;
            $authority = $config['delegated_authority'] ?? [];
            $threshold = is_array($authority) ? (int) ($authority['approval_threshold_minor'] ?? 0) : 0;
            $assessedTotal = (int) $locked->charges()->sum('amount_minor');
            if ($threshold > 0 && $assessedTotal >= $threshold) {
                $this->permissions->authorize('licensing.applications.approve_high');
            }
            if ($locked->reviewed_by === $user->getKey() || $locked->inspections()->where('status', 'completed')->where('completed_by', $user->getKey())->exists()) {
                throw new \DomainException('Separation of duties: the reviewer and inspector cannot approve this application.');
            }
            if (($config['inspection']['required'] ?? false) && ! $locked->inspections()->where('status', 'completed')->exists()) {
                throw new \DomainException('A completed inspection is required before a decision.');
            }
            if ($decision === 'approved' && ($config['inspection']['required'] ?? false) && $locked->inspections()->where('status', 'completed')->where('result', 'not like', 'pass%')->exists()) {
                throw new \DomainException('Approval requires a passing inspection result. Record corrective actions or reject the application.');
            }
            $locked->forceFill(['status' => $decision, 'decided_at' => now(), 'decided_by' => $user->getKey(), 'decision_reason' => $reason, 'conditions' => $decision === 'approved' ? $conditions : []])->save();
            $issuanceCharge = (int) ($config['charges']['issuance_minor'] ?? 0);
            if ($decision === 'approved' && $issuanceCharge > 0) {
                $this->charges->assess($locked, 'issuance', $issuanceCharge, $user);
            }
            $this->queueNotification($locked, null, 'decision_'.$decision);
            $this->audit->record(action: 'licensing.application.'.$decision, subject: $locked, after: ['reason' => $reason, 'conditions' => $locked->conditions], module: 'licensing');

            return $locked;
        });
    }

    public function issue(LicenceApplication $application, User $user): LicenceCertificate
    {
        $this->permissions->authorize('licensing.certificates.issue');

        return DB::connection('tenant')->transaction(function () use ($application, $user) {
            $locked = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            $existing = LicenceCertificate::where('licence_application_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }
            if ($locked->status !== 'approved' || $locked->decided_by === $user->getKey()) {
                throw new \DomainException('An independently approved application is required for certificate issuance.');
            }
            if (! $locked->charges()->whereIn('stage', ['application', 'renewal'])->exists() && $locked->fee_amount_minor > 0
                && (! $locked->fee_bill_id || Bill::find($locked->fee_bill_id)?->balance_minor !== 0)) {
                throw new \DomainException('The legacy licence fee must be fully paid before issuance.');
            }
            $this->charges->assertStageSettled($locked, 'certificate_issue');
            $config = $locked->configuration_snapshot;
            $start = CarbonImmutable::now($config['expiry_rules']['timezone'] ?? 'Africa/Harare')->startOfDay();
            $validityDays = max(1, (int) ($config['validity_days'] ?? 365));
            if ($locked->licence_id) {
                $licence = Licence::query()->lockForUpdate()->findOrFail($locked->licence_id);
                if ($licence->validity_status === 'revoked') {
                    throw new \DomainException('A revoked instrument cannot be renewed.');
                }
                if ($licence->validity_status !== 'active') {
                    throw new \DomainException('A suspended licence cannot be renewed until it is authorisedly reinstated.');
                }
                if ($licence->valid_to && $licence->valid_to->toDateString() < now($config['expiry_rules']['timezone'] ?? 'Africa/Harare')->toDateString()) {
                    throw new \DomainException('The prior licence expired before renewal issuance; the configured process does not automatically extend or renew expired licences.');
                }
                if (($config['renewal_validity_anchor'] ?? 'decision_date') === 'previous_expiry' && $licence->valid_to?->isAfter($start)) {
                    $start = CarbonImmutable::parse($licence->valid_to, $config['expiry_rules']['timezone'] ?? 'Africa/Harare')->addDay()->startOfDay();
                }
            } else {
                $licence = Licence::create([
                    'reference' => $this->sequences->next('licence', 'LIC', 7),
                    'licence_type_id' => $locked->licence_type_id,
                    'customer_account_id' => $locked->customer_account_id,
                    'holder_name' => $locked->holder_name,
                    'validity_status' => 'active',
                ]);
                $locked->licence_id = $licence->id;
            }
            $end = $start->addDays($validityDays - 1);
            $prior = $licence->certificates()->first();
            $certificate = LicenceCertificate::create([
                'licence_id' => $licence->id, 'licence_application_id' => $locked->id,
                'version' => ((int) ($prior?->version ?? 0)) + 1,
                'certificate_reference' => $this->sequences->next('licence_certificate', 'CERT', 7),
                'verification_id' => Str::random(48), 'template_code' => $config['certificate_template'] ?? 'demo-certificate',
                'holder_name' => $locked->holder_name, 'licence_type_name' => LicenceType::findOrFail($locked->licence_type_id)->name,
                'issued_on' => $start->toDateString(), 'expires_on' => $end->toDateString(), 'conditions' => $locked->conditions,
                'replaces_certificate_id' => $prior?->id, 'issued_by' => $user->getKey(), 'issued_at' => now(),
            ]);
            $licence->forceFill(['validity_status' => 'active', 'valid_from' => $start->toDateString(), 'valid_to' => $end->toDateString(), 'holder_name' => $locked->holder_name, 'lifecycle_reason' => 'Certificate issued'])->save();
            $locked->status = 'issued';
            $locked->licence_id = $licence->id;
            $locked->save();
            $this->queueNotification($locked, $licence, 'certificate_issued');
            $this->audit->record(action: 'licensing.certificate.issued', subject: $certificate, after: ['reference' => $certificate->certificate_reference, 'version' => $certificate->version, 'expires_on' => $certificate->expires_on->toDateString()], module: 'licensing');

            return $certificate;
        });
    }

    public function lifecycle(Licence $licence, string $action, string $reason, User $user): Licence
    {
        $this->permissions->authorize('licensing.applications.approve');
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required for licence status changes.');
        }
        $states = [
            'suspend' => ['active', 'suspended'], 'revoke' => [['active', 'suspended', 'expired'], 'revoked'],
            'reinstate' => ['suspended', 'active'],
        ];
        if (! isset($states[$action])) {
            throw new \DomainException('Unsupported licence lifecycle action.');
        }

        return DB::connection('tenant')->transaction(function () use ($licence, $action, $reason, $states) {
            $licence = Licence::query()->lockForUpdate()->findOrFail($licence->id);
            [$from, $to] = $states[$action];
            if (! is_array($from)) {
                $from = [$from];
            }
            if (! in_array($licence->validity_status, $from, true)) {
                throw new \DomainException("Cannot {$action} a licence in status [{$licence->validity_status}].");
            }
            if ($action === 'reinstate' && $licence->valid_to && $licence->valid_to->toDateString() < now($this->tenants->requireCouncil()->timezone ?: 'Africa/Harare')->toDateString()) {
                throw new \DomainException('An expired licence cannot be reinstated to active status; submit a new application if the configured rules allow it.');
            }
            $licence->forceFill([
                'validity_status' => $to,
                'suspended_at' => $action === 'suspend' ? now() : ($action === 'reinstate' ? null : $licence->suspended_at),
                'revoked_at' => $action === 'revoke' ? now() : $licence->revoked_at,
                'reinstated_at' => $action === 'reinstate' ? now() : $licence->reinstated_at,
                'lifecycle_reason' => $reason,
            ])->save();
            $this->audit->record(action: 'licensing.'.$action, subject: $licence, after: ['status' => $to, 'reason' => $reason], module: 'licensing');

            return $licence;
        });
    }

    public function publicVerification(string $verificationId): array
    {
        $certificate = LicenceCertificate::where('verification_id', $verificationId)->firstOrFail();
        $licence = Licence::findOrFail($certificate->licence_id);
        $today = now($this->timezoneFor($certificate))->toDateString();
        $currentlyValid = $licence->validity_status === 'active'
            && $licence->valid_from !== null && $licence->valid_to !== null
            && $licence->valid_from->toDateString() <= $today && $licence->valid_to->toDateString() >= $today;

        return [
            'authentic' => true,
            'currently_valid' => $currentlyValid,
            'certificate_reference' => $certificate->certificate_reference,
            'licence_type' => $certificate->licence_type_name,
            'holder' => $certificate->holder_name,
            'issued_on' => $certificate->issued_on->toDateString(),
            'expires_on' => $certificate->expires_on->toDateString(),
            'current_status' => $licence->validity_status,
        ];
    }

    public function applicationPaymentIntent(User $user, LicenceApplication $application, string $idempotencyKey, ?string $chargeId = null): array
    {
        $this->links->requireAccount($user, $application->customer_account_id);
        if ($application->submitted_by !== $user->getKey()) {
            abort(404);
        }
        $charge = $chargeId
            ? $application->charges()->whereKey($chargeId)->firstOrFail()
            : $application->charges->first(fn ($item) => $item->bill_id && Bill::find($item->bill_id)?->balance_minor > 0);
        $charge ??= $application->charges()->whereIn('stage', ['application', 'renewal'])->first();
        $charge ??= $this->charges->adoptLegacyApplicationCharge($application);
        if ($charge) {
            return $this->charges->paymentIntent($user, $application, $charge, $idempotencyKey);
        }
        if ($application->fee_amount_minor <= 0 || ! $application->fee_bill_id) {
            abort(404);
        }
        $account = $this->links->requireAccount($user, $application->customer_account_id);
        $bill = Bill::findOrFail($application->fee_bill_id);
        if (! $bill->isPayable()) {
            throw new \DomainException('The licence application fee is already settled.');
        }
        $intent = $this->payments->createIntent($account, (int) $bill->balance_minor, 'web', 'sandbox', $idempotencyKey, $user, ['licence_application_id' => $application->id, 'bill_id' => $bill->id]);

        return ['intent' => $intent, 'checkout' => $this->payments->gateway($intent->gateway)->createCheckout($intent)];
    }

    public function requestApplicationRefund(User $user, LicenceApplication $application, int $amountMinor, string $paymentId, string $idempotencyKey, string $customerNote, ?string $chargeId = null): Refund
    {
        $account = $this->links->requireAccount($user, $application->customer_account_id);
        $charge = $chargeId
            ? $application->charges()->whereKey($chargeId)->firstOrFail()
            : $application->charges()->whereIn('stage', ['application', 'renewal'])->first();
        if ($application->submitted_by !== $user->getKey() || ! $charge || ! ($charge->refund_policy['refundable'] ?? false)) {
            abort(404);
        }
        $isRejectedRefund = $application->status === 'rejected' && in_array('application_rejected', $charge->refund_policy['events'] ?? [], true);
        $isExpiredRefund = $application->status === 'issued' && $application->licence_id
            && Licence::whereKey($application->licence_id)->value('validity_status') === 'expired'
            && in_array('licence_expired', $charge->refund_policy['events'] ?? [], true);
        if (! $isRejectedRefund && ! $isExpiredRefund) {
            throw new \DomainException('The current application or licence event is not authorised by the snapshotted refund policy.');
        }
        if ($charge->status !== 'cancelled' || Bill::find($charge->bill_id)?->status !== 'void') {
            throw new \DomainException('The council must reject the application and issue an approved charge credit note before a refund can be requested.');
        }
        $allocation = PaymentAllocation::where('bill_id', $charge->bill_id)->where('payment_id', $paymentId)->whereNotNull('reversed_at')->first();
        if (! $allocation) {
            throw new \DomainException('The specified payment has not had its licence-charge allocation reversed.');
        }
        $refund = $this->refunds->request($account->id, $amountMinor, paymentId: $paymentId, reason: 'Licence application '.$application->reference.' refund request', requester: $user,
            idempotencyKey: $idempotencyKey, customerNote: $customerNote, metadata: ['licence_application_id' => $application->id, 'licence_application_reference' => $application->reference, 'licence_charge_id' => $charge->id]);

        return $refund;
    }

    public function cancelApplicationFeeForRefund(LicenceApplication $application, string $reason, User $user, ?string $chargeId = null): LicenceApplication
    {
        $charge = $chargeId
            ? $application->charges()->whereKey($chargeId)->firstOrFail()
            : $application->charges()->whereIn('stage', ['application', 'renewal'])->first();
        $charge ??= $this->charges->adoptLegacyApplicationCharge($application);
        if (! $charge) {
            abort(404);
        }
        $this->charges->cancelForRefund($charge, $reason, $user);

        return $application->fresh();
    }

    public function certificateDownload(LicenceCertificate $certificate, User $user): void
    {
        $licence = Licence::findOrFail($certificate->licence_id);
        $this->links->requireAccount($user, $licence->customer_account_id);
        $this->audit->record(action: 'licensing.certificate.downloaded', subject: $certificate, module: 'licensing');
    }

    private function publicType(string $code): LicenceType
    {
        return LicenceType::where('code', $code)->where('status', 'published')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))
            ->firstOrFail();
    }

    private function effectiveVersion(LicenceType $type): LicenceTypeVersion
    {
        $version = $type->versions()->first();
        if (! $version || ! in_array($version->approval_status, ['approved', 'demonstration'], true)) {
            throw new \DomainException('No approved or explicitly demonstration configuration is effective for this licence type.');
        }

        return $version;
    }

    private function validateForm(array $configuration, array $form, bool $submission = true): void
    {
        foreach ($configuration['application_fields'] ?? [] as $field) {
            $value = $form[$field['name']] ?? null;
            if ($submission && ($field['required'] ?? false) && (! is_string($value) || trim($value) === '')) {
                throw new \DomainException('Complete required field: '.($field['label'] ?? $field['name']).'.');
            }
            if (is_string($value) && isset($field['max_length']) && mb_strlen($value) > (int) $field['max_length']) {
                throw new \DomainException('Field is too long: '.($field['label'] ?? $field['name']).'.');
            }
        }
    }

    /** @param array<string, mixed> $configuration */
    private function validateConfiguration(array $configuration): void
    {
        foreach ($configuration['application_fields'] ?? [] as $field) {
            if (! is_array($field) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) ($field['name'] ?? '')) || ! is_string($field['label'] ?? null)) {
                throw new \DomainException('Every application field needs a safe key and a council-facing label.');
            }
            if (isset($field['max_length']) && ((int) $field['max_length'] < 1 || (int) $field['max_length'] > 4000)) {
                throw new \DomainException('Application field lengths must be between 1 and 4000 characters.');
            }
        }
        $fieldNames = array_column($configuration['application_fields'] ?? [], 'name');
        if (count($fieldNames) !== count(array_unique($fieldNames))) {
            throw new \DomainException('Application field names must be unique.');
        }
        foreach ($configuration['required_documents'] ?? [] as $document) {
            if (! is_array($document) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string) ($document['code'] ?? '')) || ! is_string($document['label'] ?? null)) {
                throw new \DomainException('Every required document needs a safe code and council-facing label.');
            }
        }
        foreach (['application_minor', 'inspection_minor', 'issuance_minor', 'renewal_minor'] as $charge) {
            if (isset($configuration['charges'][$charge]) && (! is_numeric($configuration['charges'][$charge]) || (int) $configuration['charges'][$charge] < 0)) {
                throw new \DomainException("Licence charge [{$charge}] must be a non-negative amount in minor units.");
            }
        }
        foreach ($configuration['charge_rules'] ?? [] as $stage => $rule) {
            if (! in_array($stage, ['application', 'renewal', 'inspection', 'issuance'], true) || ! is_array($rule)) {
                throw new \DomainException('Charge rules must be an object keyed by application, renewal, inspection or issuance.');
            }
            $expectedPaymentEvent = match ($stage) {
                'application', 'renewal' => 'decision', 'inspection' => 'inspection_completion', 'issuance' => 'certificate_issue',
            };
            $expectedAssessmentEvent = match ($stage) {
                'application' => 'application_submitted', 'renewal' => 'renewal_submitted',
                'inspection' => 'inspection_assigned', 'issuance' => 'application_approved',
            };
            if (isset($rule['trigger_event']) && $rule['trigger_event'] !== $expectedAssessmentEvent) {
                throw new \DomainException('A charge trigger must match its supported licensing stage event.');
            }
            if (($rule['payment_prerequisite'] ?? true) === true && ! in_array($rule['payment_required_before'] ?? $expectedPaymentEvent, ['decision', 'inspection_completion', 'certificate_issue'], true)) {
                throw new \DomainException('Charge payment prerequisites must name a supported decision, inspection completion or certificate issue event.');
            }
            if (isset($rule['recognition_method']) && ! in_array($rule['recognition_method'], ['immediate', 'deferred', 'advance_receipt', 'refundable_deposit'], true)) {
                throw new \DomainException('Unsupported stage-specific revenue recognition method.');
            }
            if (isset($rule['refund_policy']['events']) && ! is_array($rule['refund_policy']['events'])) {
                throw new \DomainException('Stage refund events must be provided as a list.');
            }
        }
        $expectedStages = ['review', ...((($configuration['inspection']['required'] ?? false) === true) ? ['inspection'] : []), 'approval', 'issuance'];
        if (isset($configuration['approval_stages']) && $configuration['approval_stages'] !== $expectedStages) {
            throw new \DomainException('Only the supported review, optional inspection, approval and issuance stages can currently be configured.');
        }
        if (isset($configuration['charges']['currency']) && ! in_array($configuration['charges']['currency'], array_keys(config('manicerp.currencies')), true)) {
            throw new \DomainException('The licence fee currency is not supported by this council.');
        }
        if (isset($configuration['validity_days']) && ((int) $configuration['validity_days'] < 1 || (int) $configuration['validity_days'] > 36500)) {
            throw new \DomainException('Licence validity must be between 1 and 36500 days.');
        }
        if (isset($configuration['renewal_window_days']) && (int) $configuration['renewal_window_days'] < 0) {
            throw new \DomainException('Renewal windows cannot be negative.');
        }
        if (isset($configuration['renewal_validity_anchor']) && ! in_array($configuration['renewal_validity_anchor'], ['decision_date', 'previous_expiry'], true)) {
            throw new \DomainException('Renewal validity must start from the decision date or configured previous expiry anchor.');
        }
        if (isset($configuration['expiry_rules']['timezone']) && ! in_array($configuration['expiry_rules']['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new \DomainException('Expiry timezone must be a valid IANA timezone identifier.');
        }
        $refundEvents = $configuration['refund_policy']['events'] ?? [];
        if (! is_array($refundEvents)) {
            throw new \DomainException('Refund events must be provided as a list.');
        }
        foreach ($refundEvents as $refundEvent) {
            if (! in_array($refundEvent, ['application_rejected', 'licence_expired'], true)) {
                throw new \DomainException('Refund events must be explicitly configured as application_rejected or licence_expired.');
            }
        }
        if (($configuration['inspection']['required'] ?? false) && ! is_bool($configuration['inspection']['required'])) {
            throw new \DomainException('Inspection requirement must be explicitly true or false.');
        }
        if (! in_array($configuration['revenue_recognition']['method'] ?? 'immediate', ['immediate', 'deferred', 'advance_receipt', 'refundable_deposit'], true)) {
            throw new \DomainException('Unsupported licence fee revenue recognition method.');
        }
    }

    /** @param array<string, mixed> $configuration */
    private function assertRenewalEligible(Licence $licence, array $configuration): void
    {
        if ($licence->validity_status === 'revoked') {
            throw new \DomainException('A revoked licence cannot be renewed. Use the authorised reinstatement process if configured.');
        }
        if ($licence->validity_status !== 'active') {
            throw new \DomainException('A suspended or expired licence cannot be renewed unless a separately approved rule permits it.');
        }
        if (! $licence->valid_to) {
            throw new \DomainException('The prior licence has no expiry date; renewal eligibility cannot be determined.');
        }
        $timezone = $configuration['expiry_rules']['timezone'] ?? 'Africa/Harare';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $expiry = CarbonImmutable::parse($licence->valid_to, $timezone)->startOfDay();
        $allowAfterExpiry = (bool) ($configuration['expiry_rules']['renewal_after_expiry_allowed'] ?? false);
        if ($today->greaterThan($expiry) && ! $allowAfterExpiry) {
            throw new \DomainException('The licence is expired and this configuration does not allow renewal after expiry.');
        }
        $windowDays = max(0, (int) ($configuration['renewal_window_days'] ?? 0));
        if ($today->lessThan($expiry->subDays($windowDays))) {
            throw new \DomainException('The configured renewal window has not opened.');
        }
    }

    private function validateRequiredDocuments(array $configuration, ?string $applicationId): void
    {
        $required = array_column($configuration['required_documents'] ?? [], 'code');
        if ($required === []) {
            return;
        }
        if (! $applicationId) {
            throw new \DomainException('Save a draft and upload the required private documents before submission.');
        }
        $present = LicenceApplicationDocument::where('licence_application_id', $applicationId)->pluck('document_type')->unique()->all();
        $missing = array_diff($required, $present);
        if ($missing !== []) {
            throw new \DomainException('Required documents are missing: '.implode(', ', $missing).'.');
        }
    }

    private function transition(LicenceApplication $application, array $from, string $to, User $user, string $reason, string $event): LicenceApplication
    {
        return DB::connection('tenant')->transaction(function () use ($application, $from, $to, $user, $reason, $event) {
            $application = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->submitted_by === $user->getKey()) {
                throw new \DomainException('Separation of duties: an applicant cannot review their own application.');
            }
            if (! in_array($application->status, $from, true)) {
                throw new \DomainException('The requested information transition is not allowed from the current state.');
            }
            if (trim($reason) === '') {
                throw new \DomainException('A reason or clear request is required.');
            }
            $application->forceFill(['status' => $to, 'decision_reason' => $reason])->save();
            $this->queueNotification($application, null, 'information_requested');
            $this->audit->record(action: $event, subject: $application, after: ['status' => $to, 'reason' => $reason], module: 'licensing');

            return $application;
        });
    }

    private function queueNotification(LicenceApplication $application, ?Licence $licence, string $event): void
    {
        $this->notifications->application($application, $event, $licence);
    }

    private function timezoneFor(LicenceCertificate $certificate): string
    {
        return $this->tenants->requireCouncil()->timezone ?: 'Africa/Harare';
    }
}
