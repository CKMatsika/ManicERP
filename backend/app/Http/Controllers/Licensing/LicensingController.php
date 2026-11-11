<?php

namespace App\Http\Controllers\Licensing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ai\Services\PolicyAssistantService;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceCertificate;
use App\Modules\Licensing\Models\LicenceCharge;
use App\Modules\Licensing\Models\LicenceInspection;
use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Licensing\Models\LicenceTypeVersion;
use App\Modules\Licensing\Services\LicenceChargeService;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\RefundService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LicensingController extends Controller
{
    public function __construct(private readonly LicensingService $licensing, private readonly TenantManager $tenants) {}

    public function staffIndex(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $applicationQuery = LicenceApplication::with(['documents.document', 'inspections'])->latest();
        if ($search !== '') {
            $applicationQuery->where(fn ($query) => $query->where('reference', 'ilike', '%'.$search.'%')->orWhere('holder_name', 'ilike', '%'.$search.'%'));
        }
        if (in_array($status, ['draft', 'submitted', 'information_requested', 'under_review', 'inspection_pending', 'approved', 'rejected', 'issued'], true)) {
            $applicationQuery->where('status', $status);
        }
        $applications = $applicationQuery->limit(100)->get()->map(fn ($application) => $this->applicationPayload($application));
        $types = LicenceType::with('versions')->orderBy('name')->get()->map(fn (LicenceType $type) => [
            'id' => $type->id, 'code' => $type->code, 'name' => $type->name, 'status' => $type->status,
            'authority' => $type->configuration_authority, 'effective_from' => $type->effective_from?->toDateString(),
            'versions' => $type->versions->map(fn (LicenceTypeVersion $version) => ['id' => $version->id, 'version' => $version->version, 'status' => $version->approval_status, 'configuration' => $version->configuration]),
        ]);
        $inspectors = $this->tenants->requireCouncil()->memberships()->with('user')->where('status', 'active')->get()->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user?->name]);

        return Inertia::render('Licensing/Index', ['applications' => $applications, 'types' => $types, 'inspectors' => $inspectors, 'search' => $search, 'statusFilter' => $status]);
    }

    public function createType(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:48', 'alpha_dash'], 'name' => ['required', 'string', 'max:160'],
            'authority' => ['required', 'string', 'max:200'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'approval_status' => ['required', 'in:demonstration,draft'], 'status' => ['required', 'in:draft,published,suspended'],
            'configuration' => ['required', 'array'],
        ]);
        $this->licensing->createType($data, $request->user());

        return back()->with('success', 'Licence type created with an immutable versioned configuration.');
    }

    public function seedDemo(Request $request)
    {
        $this->licensing->seedDemonstrationType($request->user());

        return back()->with('success', 'Demonstration licence type seeded. Configuration is marked demonstration only.');
    }

    public function reviseType(Request $request, LicenceType $type)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'], 'authority' => ['sometimes', 'string', 'max:200'],
            'effective_from' => ['sometimes', 'date'], 'effective_to' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published,suspended'], 'approval_status' => ['required', 'in:draft,demonstration'],
            'configuration' => ['required', 'array'],
        ]);
        $this->licensing->reviseType($type, $data, $request->user());

        return back()->with('success', 'A new version was created. Existing applications retain their submission snapshot.');
    }

    public function approveType(Request $request, LicenceTypeVersion $version)
    {
        $this->licensing->approveTypeVersion($version, $request->user());

        return back()->with('success', 'Licence configuration version approved.');
    }

    public function review(Request $request, LicenceApplication $application)
    {
        $this->licensing->review($application, $request->user());

        return back()->with('success', 'Application placed under review.');
    }

    public function information(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $this->licensing->requestInformation($application, $data['message'], $request->user());

        return back()->with('success', 'Information request recorded.');
    }

    public function assignInspection(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['inspector_id' => ['required', 'exists:central.users,id'], 'scheduled_at' => ['nullable', 'date']]);
        $inspector = User::findOrFail($data['inspector_id']);
        $this->licensing->assignInspection($application, $inspector, $data['scheduled_at'] ?? null);

        return back()->with('success', 'Inspection assigned.');
    }

    public function inspectionResult(Request $request, LicenceInspection $inspection)
    {
        $data = $request->validate(['result' => ['required', 'in:pass,fail,corrective_action'], 'notes' => ['required', 'string', 'max:4000'], 'findings' => ['array']]);
        $this->licensing->completeInspection($inspection, $data['findings'] ?? [], $data['result'].': '.$data['notes'], $request->user());

        return back()->with('success', 'Inspection result recorded.');
    }

    public function decide(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'reason' => ['required', 'string', 'max:2000'], 'conditions' => ['array']]);
        $this->licensing->decide($application, $data['decision'], $data['reason'], $data['conditions'] ?? [], $request->user());

        return back()->with('success', 'Application decision recorded.');
    }

    public function issue(Request $request, LicenceApplication $application)
    {
        $certificate = $this->licensing->issue($application, $request->user());

        return back()->with('success', 'Certificate '.$certificate->certificate_reference.' issued.');
    }

    public function lifecycle(Request $request, Licence $licence, string $action)
    {
        abort_unless(in_array($action, ['suspend', 'revoke', 'reinstate'], true), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->licensing->lifecycle($licence, $action, $data['reason'], $request->user());

        return back()->with('success', 'Licence status updated.');
    }

    public function citizen(Request $request)
    {
        $types = LicenceType::with('versions')->where('status', 'published')->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))->orderBy('name')->get()
            ->map(fn (LicenceType $type) => ['code' => $type->code, 'name' => $type->name, 'version' => $type->versions->first()?->version, 'configuration' => $type->versions->first()?->configuration]);
        $accounts = app(AccountLinkService::class)->linksFor($request->user())->map(fn ($link) => ['id' => $link->account?->id, 'number' => $link->account?->account_number, 'name' => $link->account?->primaryParty?->display_name]);
        $applications = LicenceApplication::whereIn('customer_account_id', $accounts->pluck('id'))->with('inspections')->latest()->get()->map(fn ($application) => $this->applicationPayload($application));

        return Inertia::render('Portal/Licensing', ['types' => $types, 'accounts' => $accounts, 'applications' => $applications]);
    }

    public function citizenAssistant(Request $request, PolicyAssistantService $assistant)
    {
        $question = trim((string) $request->query('q', ''));
        $result = $question === '' ? null : $assistant->ask($question, $request->user());

        return Inertia::render('Portal/LicensingAssistant', [
            'question' => $question,
            'result' => $result,
            'disclaimer' => config('manicerp.ai.disclaimer'),
            'enabled' => $assistant->enabled(),
        ]);
    }

    public function saveDraft(Request $request)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'licence_type' => ['required', 'string', 'max:48'], 'application_id' => ['nullable', 'string'], 'licence_id' => ['nullable', 'string'], 'form' => ['required', 'array']]);
        $application = $this->licensing->saveDraft($request->user(), $data['account_id'], $data['licence_type'], $data['form'], $data['application_id'] ?? null, $data['licence_id'] ?? null);

        return back()->with('success', 'Draft '.$application->reference.' saved on the council server.');
    }

    public function submit(Request $request)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'licence_type' => ['required', 'string', 'max:48'], 'application_id' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:191'], 'form' => ['required', 'array']]);
        $application = $this->licensing->submit($request->user(), $data['account_id'], $data['licence_type'], $data['form'], $data['idempotency_key'], $data['application_id'] ?? null);

        return back()->with('success', 'Application '.$application->reference.' submitted.');
    }

    public function respond(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'form' => ['required', 'array']]);
        $this->licensing->respondToInformation($request->user(), $data['account_id'], $application, $data['form']);

        return back()->with('success', 'Response submitted for council review.');
    }

    public function refund(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'payment_id' => ['required', 'string'], 'charge_id' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:191'], 'customer_note' => ['required', 'string', 'max:1000']]);
        $refund = $this->licensing->requestApplicationRefund($request->user(), $application, (int) $data['amount_minor'], $data['payment_id'], $data['idempotency_key'], $data['customer_note'], $data['charge_id'] ?? null);

        return back()->with('success', 'Refund request '.$refund->refund_number.' was submitted for council review.');
    }

    public function cancelFee(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000'], 'charge_id' => ['nullable', 'string']]);
        $this->licensing->cancelApplicationFeeForRefund($application, $data['reason'], $request->user(), $data['charge_id'] ?? null);

        return back()->with('success', 'Licence fee credit note posted; eligible sandbox refunds can now use the existing refund workflow.');
    }

    public function waiveCharge(Request $request, LicenceCharge $charge, LicenceChargeService $charges)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $charges->waive($charge, $data['reason'], $request->user());

        return back()->with('success', 'Licence charge waiver recorded with a linked credit note.');
    }

    public function releaseCharge(Request $request, LicenceApplication $application, LicenceCharge $charge, LicenceChargeService $charges)
    {
        abort_unless($charge->licence_application_id === $application->id, 404);
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'trigger_event' => ['required', 'string', 'max:48'], 'event_key' => ['required', 'string', 'max:191'], 'evidence' => ['required', 'string', 'max:4000']]);
        $charges->releaseDeferred($charge, (int) $data['amount_minor'], $data['trigger_event'], $data['event_key'], $data['evidence'], $request->user());

        return back()->with('success', 'Deferred licence fee release posted to the approved policy accounts.');
    }

    public function upload(Request $request, LicenceApplication $application, DocumentService $documents)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'document_type' => ['required', 'string', 'max:80'], 'file' => ['required', 'file', 'max:10240']]);
        $this->links()->requireAccount($request->user(), $data['account_id']);
        abort_unless($application->customer_account_id === $data['account_id'], 404);
        $document = $documents->store($data['file'], 'confidential', $application, $request->user());
        $this->licensing->attachDocument($request->user(), $data['account_id'], $application, $document, $data['document_type']);

        return back()->with('success', 'Private document uploaded; previous versions remain in history.');
    }

    public function pay(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:191'], 'charge_id' => ['nullable', 'string']]);
        $result = $this->licensing->applicationPaymentIntent($request->user(), $application, $data['idempotency_key'], $data['charge_id'] ?? null);

        return redirect()->route('portal.pay.show', $result['intent']->id);
    }

    public function downloadCertificate(Request $request, LicenceCertificate $certificate)
    {
        $this->licensing->certificateDownload($certificate, $request->user());
        $council = $this->tenants->requireCouncil();
        $verificationUrl = route('licensing.public.verify', [$this->tenants->requireCouncil()->code, $certificate->verification_id]);
        $qrCode = base64_encode((new Writer(new ImageRenderer(new RendererStyle(180, 2), new SvgImageBackEnd)))->writeString($verificationUrl));
        $html = view('licensing.certificate', ['certificate' => $certificate, 'council' => $council, 'verificationUrl' => $verificationUrl, 'qrCode' => $qrCode])->render();
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$certificate->certificate_reference.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function verify(string $verificationId)
    {
        return response()->view('licensing.verification', ['result' => $this->licensing->publicVerification($verificationId)], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate, private', 'Pragma' => 'no-cache']);
    }

    public function publicCertificate(string $verificationId)
    {
        $certificate = LicenceCertificate::where('verification_id', $verificationId)->firstOrFail();
        abort_unless($this->licensing->publicVerification($verificationId)['authentic'], 404);
        $council = $this->tenants->requireCouncil();
        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $verificationUrl = route('licensing.public.verify', [$this->tenants->requireCouncil()->code, $verificationId]);
        $qrCode = base64_encode((new Writer(new ImageRenderer(new RendererStyle(180, 2), new SvgImageBackEnd)))->writeString($verificationUrl));
        $pdf->loadHtml(view('licensing.certificate', ['certificate' => $certificate, 'council' => $council, 'verificationUrl' => $verificationUrl, 'qrCode' => $qrCode])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$certificate->certificate_reference.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function publicVerify(string $councilCode, string $verificationId)
    {
        $council = Council::where('code', $councilCode)->where('status', 'active')->firstOrFail();
        abort_unless(app(ModuleRegistry::class)->enabled($council, 'licensing'), 404);
        $this->tenants->connect($council);

        return response()->view('licensing.verification', ['result' => $this->licensing->publicVerification($verificationId)], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate, private', 'Pragma' => 'no-cache']);
    }

    private function applicationPayload(LicenceApplication $application): array
    {
        return [
            'id' => $application->id, 'reference' => $application->reference, 'licence_id' => $application->licence_id,
            'licence_status' => $application->licence_id ? Licence::find($application->licence_id)?->validity_status : null,
            'account_id' => $application->customer_account_id,
            'type' => LicenceType::find($application->licence_type_id)?->name, 'type_code' => LicenceType::find($application->licence_type_id)?->code, 'attempt_type' => $application->attempt_type,
            'status' => $application->status, 'holder' => $application->holder_name, 'fee_minor' => $application->fee_amount_minor,
            'fee_currency' => $application->fee_currency, 'fee_bill_id' => $application->fee_bill_id,
            'paid' => $application->fee_amount_minor === 0 || ($application->fee_bill_id && Bill::find($application->fee_bill_id)?->balance_minor === 0),
            'submitted_at' => $application->submitted_at?->toIso8601String(), 'reason' => $application->decision_reason,
            'conditions' => $application->conditions, 'configuration' => $application->configuration_snapshot, 'form_data' => $application->form_data,
            'charges' => $application->charges->map(fn ($charge) => app(LicenceChargeService::class)->view($charge)),
            'extractions' => $application->extractions->map(fn ($extraction) => ['id' => $extraction->id, 'document_link_id' => $extraction->licence_application_document_id, 'version' => $extraction->document_version, 'status' => $extraction->status, 'proposed_fields' => $extraction->proposed_fields, 'reviewed_fields' => $extraction->reviewed_fields]),
            'assistant_drafts' => $application->assistantDrafts->map(fn ($draft) => ['id' => $draft->id, 'type' => $draft->draft_type, 'content' => $draft->content, 'status' => $draft->status, 'source_refs' => $draft->source_refs]),
            'documents' => $application->documents?->map(fn ($document) => ['id' => $document->id, 'document_id' => $document->document_id, 'type' => $document->document_type, 'version' => $document->version, 'sha256' => $document->document?->sha256, 'uploaded_at' => $document->created_at?->toIso8601String()]),
            'inspections' => $application->inspections?->map(fn ($inspection) => ['id' => $inspection->id, 'status' => $inspection->status, 'scheduled_at' => $inspection->scheduled_at?->toIso8601String(), 'result' => $inspection->result]),
            'certificates' => $application->licence_id ? LicenceCertificate::where('licence_id', $application->licence_id)->orderByDesc('version')->get()->map(fn ($certificate) => ['id' => $certificate->id, 'reference' => $certificate->certificate_reference, 'version' => $certificate->version, 'expires_on' => $certificate->expires_on?->toDateString()]) : [],
            'refunds' => Refund::where('customer_account_id', $application->customer_account_id)->where('metadata->licence_application_id', $application->id)->latest()->get()->map(fn ($refund) => app(RefundService::class)->status($refund)),
            'refund_eligible_payments' => $application->charges->filter(fn ($charge) => $charge->status === 'cancelled')->flatMap(fn ($charge) => PaymentAllocation::where('bill_id', $charge->bill_id)->whereNotNull('reversed_at')->get()->map(fn ($allocation) => Payment::find($allocation->payment_id))->filter()->unique('id')->map(fn ($payment) => ['id' => $payment->id, 'reference' => $payment->reference, 'amount_minor' => $payment->amount_minor, 'charge_id' => $charge->id]))->unique('id')->values(),
        ];
    }

    private function links(): AccountLinkService
    {
        return app(AccountLinkService::class);
    }
}
