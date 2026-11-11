<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Licensing\LicensingController;
use App\Modules\Ai\Services\PolicyAssistantService;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceCertificate;
use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Licensing\Services\LicenceChargeService;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\RefundService;
use Illuminate\Http\Request;

class LicensingApiController extends Controller
{
    public function __construct(private readonly AccountLinkService $links, private readonly LicensingService $licensing) {}

    public function catalog()
    {
        return response()->json(['data' => LicenceType::with('versions')->where('status', 'published')->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))->orderBy('name')->get()
            ->map(fn ($type) => ['code' => $type->code, 'name' => $type->name, 'version' => $type->versions->first()?->version, 'configuration' => $type->versions->first()?->configuration])]);
    }

    public function guidance(Request $request, PolicyAssistantService $assistant)
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:500']]);

        return response()->json(['data' => $assistant->ask($data['q'], $request->user())]);
    }

    public function applications(Request $request)
    {
        $accountIds = $this->links->linksFor($request->user())->pluck('customer_account_id');

        return response()->json(['data' => LicenceApplication::whereIn('customer_account_id', $accountIds)->with(['documents.document', 'inspections'])->latest()->get()->map(fn ($application) => $this->payload($application))]);
    }

    public function draft(Request $request)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'licence_type' => ['required', 'string', 'max:48'], 'application_id' => ['nullable', 'string'], 'licence_id' => ['nullable', 'string'], 'form' => ['required', 'array']]);
        $application = $this->licensing->saveDraft($request->user(), $data['account_id'], $data['licence_type'], $data['form'], $data['application_id'] ?? null, $data['licence_id'] ?? null);

        return response()->json(['data' => ['id' => $application->id, 'reference' => $application->reference, 'status' => $application->status, 'sent' => false]], 201);
    }

    public function submit(Request $request)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'licence_type' => ['required', 'string', 'max:48'], 'application_id' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:191'], 'form' => ['required', 'array']]);
        $application = $this->licensing->submit($request->user(), $data['account_id'], $data['licence_type'], $data['form'], $data['idempotency_key'], $data['application_id'] ?? null);

        return response()->json(['data' => $this->payload($application), 'sent' => true], 201);
    }

    public function respond(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'form' => ['required', 'array']]);
        $application = $this->licensing->respondToInformation($request->user(), $data['account_id'], $application, $data['form']);

        return response()->json(['data' => $this->payload($application), 'sent' => true]);
    }

    public function refund(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:1'], 'payment_id' => ['required', 'string'], 'charge_id' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:191'], 'customer_note' => ['required', 'string', 'max:1000']]);
        $refund = $this->licensing->requestApplicationRefund($request->user(), $application, (int) $data['amount_minor'], $data['payment_id'], $data['idempotency_key'], $data['customer_note'], $data['charge_id'] ?? null);

        return response()->json(['data' => app(RefundService::class)->status($refund)], 201);
    }

    public function upload(Request $request, LicenceApplication $application, DocumentService $documents)
    {
        $data = $request->validate(['account_id' => ['required', 'string'], 'document_type' => ['required', 'string', 'max:80'], 'file' => ['required', 'file', 'max:10240']]);
        $this->links->requireAccount($request->user(), $data['account_id']);
        abort_unless($application->customer_account_id === $data['account_id'], 404);
        $document = $documents->store($data['file'], 'confidential', $application, $request->user());
        $link = $this->licensing->attachDocument($request->user(), $data['account_id'], $application, $document, $data['document_type']);

        return response()->json(['data' => ['id' => $link->id, 'document_type' => $link->document_type, 'version' => $link->version, 'sha256' => $document->sha256]], 201);
    }

    public function payment(Request $request, LicenceApplication $application)
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:191'], 'charge_id' => ['nullable', 'string']]);
        $result = $this->licensing->applicationPaymentIntent($request->user(), $application, $data['idempotency_key'], $data['charge_id'] ?? null);

        return response()->json(['data' => ['id' => $result['intent']->id, 'reference' => $result['intent']->reference, 'status' => $result['intent']->status, 'amount_minor' => $result['intent']->amount_minor, 'currency' => $result['intent']->currency_code, 'checkout' => $result['checkout'], 'verified' => false]], 201);
    }

    public function certificate(Request $request, LicenceCertificate $certificate)
    {
        $licence = Licence::findOrFail($certificate->licence_id);
        $this->links->requireAccount($request->user(), $licence->customer_account_id);

        return response()->json(['data' => ['id' => $certificate->id, 'reference' => $certificate->certificate_reference, 'version' => $certificate->version, 'download_url' => route('api.v1.licensing.certificate.download', $certificate->id), 'authentic' => true]]);
    }

    public function downloadCertificate(Request $request, LicenceCertificate $certificate, LicensingController $controller)
    {
        return $controller->downloadCertificate($request, $certificate);
    }

    private function payload(LicenceApplication $application): array
    {
        $bill = $application->fee_bill_id ? Bill::find($application->fee_bill_id) : null;

        return [
            'id' => $application->id, 'reference' => $application->reference, 'account_id' => $application->customer_account_id, 'licence_id' => $application->licence_id, 'type' => LicenceType::find($application->licence_type_id)?->name, 'type_code' => LicenceType::find($application->licence_type_id)?->code,
            'attempt_type' => $application->attempt_type, 'status' => $application->status, 'submitted_at' => $application->submitted_at?->toIso8601String(),
            'fee_amount_minor' => $application->fee_amount_minor, 'fee_currency' => $application->fee_currency,
            'fee_paid' => $application->fee_amount_minor === 0 || $bill?->balance_minor === 0,
            'charges' => $application->charges->map(fn ($charge) => app(LicenceChargeService::class)->view($charge)),
            'decision_reason' => $application->decision_reason, 'conditions' => $application->conditions, 'form_data' => $application->form_data,
            'documents' => $application->documents->map(fn ($document) => ['type' => $document->document_type, 'version' => $document->version, 'sha256' => $document->document?->sha256]),
            'inspections' => $application->inspections->map(fn ($inspection) => ['status' => $inspection->status, 'result' => $inspection->result]),
            'certificates' => $application->licence_id ? LicenceCertificate::where('licence_id', $application->licence_id)->get()->map(fn ($certificate) => ['id' => $certificate->id, 'reference' => $certificate->certificate_reference, 'version' => $certificate->version]) : [],
            'refunds' => Refund::where('customer_account_id', $application->customer_account_id)->where('metadata->licence_application_id', $application->id)->latest()->get()->map(fn ($refund) => app(RefundService::class)->status($refund)),
            'refund_eligible_payments' => $application->charges->filter(fn ($charge) => $charge->status === 'cancelled')->flatMap(fn ($charge) => PaymentAllocation::where('bill_id', $charge->bill_id)->whereNotNull('reversed_at')->get()->map(fn ($allocation) => Payment::find($allocation->payment_id))->filter()->unique('id')->map(fn ($payment) => ['id' => $payment->id, 'reference' => $payment->reference, 'amount_minor' => $payment->amount_minor, 'charge_id' => $charge->id]))->unique('id')->values(),
        ];
    }
}
