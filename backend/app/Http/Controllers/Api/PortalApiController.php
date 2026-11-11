<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Customer\Models\ServiceRequest;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;

class PortalApiController extends Controller
{
    public function __construct(
        private readonly AccountLinkService $links,
        private readonly PaymentService $payments,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function accounts(Request $request)
    {
        $accounts = $this->links->linksFor($request->user())->map(fn (PortalAccountLink $link) => [
            'id' => $link->account?->id,
            'account_number' => $link->account?->account_number,
            'type' => $link->account?->account_type,
            'property' => $link->account?->property?->parcel_number,
            'outstanding_minor' => $link->account?->outstandingBalanceMinor() ?? 0,
            'currency' => $link->account?->currency_code,
            'relationship' => $link->relationship,
        ]);

        return response()->json(['data' => $accounts]);
    }

    public function account(Request $request, string $account)
    {
        $model = $this->links->requireAccount($request->user(), $account);

        return response()->json([
            'data' => [
                'id' => $model->id,
                'account_number' => $model->account_number,
                'type' => $model->account_type,
                'currency' => $model->currency_code,
                'outstanding_minor' => $model->outstandingBalanceMinor(),
                'property' => $model->property ? [
                    'parcel_number' => $model->property->parcel_number,
                    'address' => $model->property->address_line,
                ] : null,
            ],
        ]);
    }

    public function bills(Request $request, string $account)
    {
        $model = $this->links->requireAccount($request->user(), $account);

        $bills = Bill::where('customer_account_id', $model->id)
            ->orderByDesc('issue_date')
            ->paginate(20)
            ->through(fn (Bill $bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'service_type' => $bill->service_type,
                'issue_date' => $bill->issue_date?->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'total_minor' => $bill->total_minor,
                'balance_minor' => $bill->balance_minor,
                'status' => $bill->status,
                'explanation' => $bill->calculation_snapshot['lines'] ?? [],
            ]);

        return response()->json($bills);
    }

    public function payments(Request $request, string $account)
    {
        $model = $this->links->requireAccount($request->user(), $account);

        return response()->json([
            'data' => Payment::with('receipt')
                ->where('customer_account_id', $model->id)
                ->where('status', 'verified')
                ->orderByDesc('received_at')
                ->limit(50)
                ->get()
                ->map(fn (Payment $payment) => [
                    'payment_number' => $payment->payment_number,
                    'receipt_number' => $payment->receipt?->receipt_number,
                    'amount_minor' => $payment->amount_minor,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at?->toIso8601String(),
                ]),
        ]);
    }

    public function allPayments(Request $request)
    {
        $accountIds = $this->links->linksFor($request->user())->pluck('customer_account_id');

        return response()->json([
            'data' => Payment::with('receipt')
                ->whereIn('customer_account_id', $accountIds)
                ->where('status', 'verified')
                ->orderByDesc('received_at')
                ->limit(50)
                ->get()
                ->map(fn (Payment $payment) => [
                    'payment_number' => $payment->payment_number,
                    'receipt_number' => $payment->receipt?->receipt_number,
                    'amount_minor' => $payment->amount_minor,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at?->toIso8601String(),
                ]),
        ]);
    }

    public function createIntent(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['required', 'string'],
            'amount' => ['nullable', 'string', 'max:20'],
            'bill_id' => ['nullable', 'string'],
            'gateway' => ['nullable', 'string', 'in:sandbox,paynow'],
            'payment_method' => ['nullable', 'string', 'in:ecocash,onemoney,card'],
            'phone' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        $account = $this->links->requireAccount($request->user(), $data['account_id']);

        if (! empty($data['bill_id'])) {
            $bill = Bill::where('customer_account_id', $account->id)->findOrFail($data['bill_id']);

            if (! $bill->isPayable()) {
                return response()->json(['message' => 'That bill is not payable.'], 422);
            }

            $amount = Money::fromMinor($bill->balance_minor, $account->currency_code);
        } else {
            $amount = Money::fromDecimal($data['amount'] ?? '0', $account->currency_code);
        }

        if (! $amount->isPositive()) {
            return response()->json(['message' => 'Amount must be greater than zero.'], 422);
        }

        $gateway = $data['gateway'] ?? 'paynow';
        $metadata = [
            'payment_method' => $data['payment_method'] ?? 'ecocash',
            'phone' => $data['phone'] ?? '',
            'bill_id' => $data['bill_id'] ?? null,
        ];

        $intent = $this->payments->createIntent(
            $account,
            $amount->minor,
            'mobile',
            $gateway,
            $data['idempotency_key'],
            $request->user(),
            $metadata
        );

        return response()->json([
            'data' => [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency_code,
                'status' => $intent->status,
                'checkout' => $this->payments->gateway($intent->gateway)->createCheckout($intent),
            ],
        ], 201);
    }

    public function requestLink(Request $request)
    {
        $data = $request->validate([
            'account_number' => ['required', 'string', 'max:24'],
            'relationship' => ['required', 'in:owner,representative'],
        ]);

        $result = $this->links->request($request->user(), $data['account_number'], $data['relationship']);

        return response()->json([
            'message' => $result['message'],
            'link_id' => $result['link']?->id,
            'masked' => $result['masked'],
        ]);
    }

    public function verifyLink(Request $request, string $link)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $model = PortalAccountLink::findOrFail($link);

        try {
            $this->links->verify($request->user(), $model, $data['code']);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Account linked.', 'status' => 'verified']);
    }

    public function revokeLink(Request $request, string $link)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']]);
        $model = PortalAccountLink::findOrFail($link);
        $this->links->revoke($model, $request->user(), $data['reason']);

        return response()->json(['message' => 'Account link revoked.']);
    }

    public function serviceRequests(Request $request)
    {
        $accountIds = $this->links->linksFor($request->user())->pluck('customer_account_id');

        return response()->json([
            'data' => ServiceRequest::where(function ($query) use ($request, $accountIds) {
                $query->where('submitted_by', $request->user()->getKey())->orWhereIn('customer_account_id', $accountIds);
            })->orderByDesc('created_at')->get()->map(fn (ServiceRequest $serviceRequest) => [
                'reference' => $serviceRequest->reference,
                'category' => $serviceRequest->category,
                'subject' => $serviceRequest->subject,
                'status' => $serviceRequest->status,
                'created_at' => $serviceRequest->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeServiceRequest(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'string'],
            'category' => ['required', 'in:billing_dispute,leak,water_interruption,refuse,complaint,other'],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:4000'],
        ]);

        $account = ! empty($data['account_id'])
            ? $this->links->requireAccount($request->user(), $data['account_id'])
            : null;

        $reference = $this->sequences->next('service_request', 'SR', 6);

        $serviceRequest = ServiceRequest::create([
            'reference' => $reference,
            'customer_account_id' => $account?->id,
            'party_id' => $account?->primary_party_id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
            'priority' => in_array($data['category'], ['leak', 'water_interruption'], true) ? 'high' : 'normal',
            'submitted_by' => $request->user()->getKey(),
            'sla_due_at' => now()->addDays(3),
        ]);

        $this->audit->record(
            action: 'customer.service_request.created',
            subject: $serviceRequest,
            after: ['reference' => $reference, 'category' => $data['category']],
            module: 'customer',
        );

        return response()->json(['data' => ['reference' => $reference, 'status' => 'open']], 201);
    }
}
