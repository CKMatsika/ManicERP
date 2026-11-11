<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\RevenuePolicy;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Revenue\Models\Bill;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RevenuePolicyService
{
    public function draft(array $data, User $user): RevenuePolicy
    {
        $this->authorize($user, 'accounting.revenue_policy.manage');

        return DB::connection('tenant')->transaction(function () use ($data, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('revenue-policy'))");
            $version = (int) RevenuePolicy::where('charge_type', $data['charge_type'])->max('version') + 1;

            return RevenuePolicy::create([...$data, 'version' => $version, 'status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'created_by' => $user->id]);
        });
    }

    public function approve(RevenuePolicy $policy, User $user): RevenuePolicy
    {
        $this->authorize($user, 'accounting.revenue_policy.approve');

        return DB::connection('tenant')->transaction(function () use ($policy, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('revenue-policy'))");
            $policy = $policy->fresh();
            if ($policy->status !== 'draft' || $policy->created_by === $user->id) {
                throw new \DomainException('A draft policy requires approval by a different officer.');
            }
            if (! $policy->source_document_id || ! $policy->source_ref || ! $policy->notes) {
                throw new \DomainException('Record source, section/page and the binding-arrangement/compliance-obligation assessment.');
            }
            if (! in_array($policy->recognition_method, ['immediate', 'deferred', 'advance_receipt', 'refundable_deposit'], true)) {
                throw new \DomainException('Unsupported recognition method.');
            }
            if ($policy->recognition_method === 'deferred') {
                $rule = $policy->recognition_rule ?? [];
                if (! in_array($rule['trigger_event'] ?? null, ['application_approved', 'inspection_passed', 'certificate_issued', 'term_elapsed', 'manual_evidence'], true)
                    || empty($rule['description']) || ($rule['evidence_required'] ?? true) !== true) {
                    throw new \DomainException('Deferred revenue needs an approved recognition trigger, rule description and evidence requirement.');
                }
                if (($rule['trigger_event'] ?? null) === 'term_elapsed' && ((int) ($rule['term_days'] ?? 0) < 1 || (int) ($rule['term_days'] ?? 0) > 36500)) {
                    throw new \DomainException('A term-elapsed recognition trigger requires a bounded schedule from 1 through 36500 days.');
                }
            }
            if ($policy->effective_to && $policy->effective_to->lt($policy->effective_from)) {
                throw new \DomainException('Invalid policy effective period.');
            }
            $lastBill = Bill::where('service_type', $policy->charge_type)->max('issue_date');
            if ($lastBill && $policy->effective_from->toDateString() <= $lastBill) {
                throw new \DomainException('Policy changes must be prospective; historical bills require controlled corrections.');
            }
            $chart = ChartVersion::where('status', 'active')->firstOrFail();
            $credit = $policy->recognition_method === 'immediate' ? $policy->revenue_account_code : $policy->deferred_account_code;
            foreach ([[$policy->receivable_account_code, 'asset'], [$credit, $policy->recognition_method === 'immediate' ? 'revenue' : 'liability']] as [$code, $class]) {
                $account = GlAccount::where('chart_version_id', $chart->id)->where('code', $code)->first();
                if (! $account || ! $account->isPostable() || $account->account_class !== $class || ! $account->isActiveOn($policy->effective_from->toDateString())) {
                    throw new \DomainException('Policy accounts must be active posting accounts of the appropriate class.');
                }
            }
            $overlaps = RevenuePolicy::where('charge_type', $policy->charge_type)->where('status', 'approved')
                ->where('effective_from', '<=', $policy->effective_to?->toDateString() ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $policy->effective_from->toDateString()))->get();
            foreach ($overlaps as $prior) {
                if ($prior->effective_to !== null || ! $prior->effective_from->lt($policy->effective_from)) {
                    throw new \DomainException('Approved policy periods overlap.');
                }
                $end = $policy->effective_from->copy()->subDay()->toDateString();
                RevenuePolicy::whereKey($prior->id)->update(['effective_to' => $end]);
                app(AuditLogger::class)->record(action: 'accounting.revenue_policy.superseded', subject: $prior, before: ['effective_to' => null], after: ['effective_to' => $end, 'successor' => $policy->id], module: 'accounting');
            }
            $policy->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            app(AuditLogger::class)->record(action: 'accounting.revenue_policy.approved', subject: $policy, after: $policy->toArray(), module: 'accounting');

            return $policy;
        });
    }

    public function resolve(string $chargeType, string $date): ?RevenuePolicy
    {
        $matches = RevenuePolicy::where('charge_type', $chargeType)->where('status', 'approved')
            ->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->get();
        if ($matches->count() > 1) {
            throw new \DomainException('Ambiguous revenue policies.');
        }

        return $matches->first();
    }

    private function authorize(User $user, string $permission): void
    {
        if (! app(PermissionService::class)->allows($user, $permission)) {
            throw new AuthorizationException;
        }
    }
}
