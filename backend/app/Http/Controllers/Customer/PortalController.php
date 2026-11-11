<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Customer\Models\ServiceRequest;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentIntent;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\PaymentService;
use App\Modules\Revenue\Services\RefundService;
use App\Support\Money;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function __construct(
        private readonly AccountLinkService $links,
        private readonly TenantManager $tenants,
        private readonly PaymentService $payments,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $links = $this->links->linksFor($user);

        $accounts = $links->map(function (PortalAccountLink $link) {
            $account = $link->account;

            return [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'type' => $account->account_type,
                'property' => $account->property?->parcel_number,
                'address' => $account->property?->address_line,
                'outstanding_minor' => $account->outstandingBalanceMinor(),
                'currency' => $account->currency_code,
            ];
        });

        $payments = Payment::with(['receipt', 'account'])
            ->whereIn('customer_account_id', $links->pluck('customer_account_id'))
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment) => [
                'number' => $payment->payment_number,
                'receipt' => $payment->receipt?->receipt_number,
                'account' => $payment->account?->account_number,
                'amount_minor' => $payment->amount_minor,
                'method' => $payment->method,
                'status' => $payment->status,
                'received_at' => $payment->received_at?->toIso8601String(),
            ]);

        $pendingLinks = PortalAccountLink::where('user_id', $user->getKey())
            ->where('status', 'pending')
            ->whereNull('revoked_at')
            ->with('account')
            ->get()
            ->map(fn (PortalAccountLink $link) => [
                'id' => $link->id,
                'account' => $link->account?->account_number,
                'requested_at' => $link->requested_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Dashboard', [
            'accounts' => $accounts,
            'payments' => $payments,
            'pendingLinks' => $pendingLinks,
        ]);
    }

    public function account(Request $request, string $account)
    {
        $model = $this->links->requireAccount($request->user(), $account);

        $bills = Bill::where('customer_account_id', $model->id)
            ->orderByDesc('issue_date')
            ->limit(24)
            ->get()
            ->map(fn (Bill $bill) => [
                'id' => $bill->id,
                'number' => $bill->bill_number,
                'service' => $bill->service_type,
                'period' => $bill->period_start?->toDateString().' to '.$bill->period_end?->toDateString(),
                'total_minor' => $bill->total_minor,
                'balance_minor' => $bill->balance_minor,
                'status' => $bill->status,
                'due_date' => $bill->due_date?->toDateString(),
                'explanation' => $bill->calculation_snapshot['lines'] ?? [],
            ]);

        $payments = Payment::with('receipt')
            ->where('customer_account_id', $model->id)
            ->where('status', 'verified')
            ->orderByDesc('received_at')
            ->limit(24)
            ->get()
            ->map(fn (Payment $payment) => [
                'number' => $payment->payment_number,
                'receipt' => $payment->receipt?->receipt_number,
                'amount_minor' => $payment->amount_minor,
                'method' => $payment->method,
                'received_at' => $payment->received_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Account', [
            'account' => [
                'id' => $model->id,
                'number' => $model->account_number,
                'type' => $model->account_type,
                'property' => $model->property?->parcel_number,
                'address' => $model->property?->address_line,
                'outstanding_minor' => $model->outstandingBalanceMinor(),
                'currency' => $model->currency_code,
            ],
            'bills' => $bills,
            'payments' => $payments,
            'refunds' => Refund::where('customer_account_id', $model->id)->latest()->limit(24)->get()
                ->map(fn ($refund) => app(RefundService::class)->status($refund)),
        ]);
    }

    public function statement(Request $request, string $account)
    {
        $model = $this->links->requireAccount($request->user(), $account);
        $council = $this->tenants->requireCouncil();

        $bills = Bill::where('customer_account_id', $model->id)->orderBy('issue_date')->get();
        $payments = Payment::with('receipt')->where('customer_account_id', $model->id)->where('status', 'verified')->orderBy('received_at')->get();

        $html = view('statements.account', [
            'council' => $council,
            'account' => $model,
            'party' => $model->primaryParty,
            'property' => $model->property,
            'bills' => $bills,
            'payments' => $payments,
            'generatedAt' => now(),
            'outstanding' => $model->outstandingBalanceMinor(),
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="statement-'.$model->account_number.'.pdf"',
        ]);
    }

    public function payForm(Request $request)
    {
        $links = $this->links->linksFor($request->user());

        $accounts = $links->map(function (PortalAccountLink $link) {
            $account = $link->account;

            return [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'currency' => $account->currency_code,
                'outstanding_minor' => $account->outstandingBalanceMinor(),
                'bills' => Bill::where('customer_account_id', $account->id)
                    ->whereIn('status', ['issued', 'partially_paid'])
                    ->where('balance_minor', '>', 0)
                    ->get()
                    ->map(fn (Bill $bill) => [
                        'id' => $bill->id,
                        'number' => $bill->bill_number,
                        'service' => $bill->service_type,
                        'balance_minor' => $bill->balance_minor,
                    ]),
            ];
        });

        return Inertia::render('Portal/Pay', [
            'accounts' => $accounts,
            'selectedAccount' => $request->query('account'),
            'sandbox' => true,
        ]);
    }

    public function pay(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['required', 'string'],
            'bill_id' => ['nullable', 'string'],
            'amount' => ['nullable', 'string', 'max:20'],
        ]);

        $account = $this->links->requireAccount($request->user(), $data['account_id']);

        if (! empty($data['bill_id'])) {
            $bill = Bill::where('customer_account_id', $account->id)->findOrFail($data['bill_id']);

            if (! $bill->isPayable()) {
                return back()->with('error', 'That bill is no longer payable.');
            }

            $amount = Money::fromMinor($bill->balance_minor, $account->currency_code);
        } else {
            $amount = Money::fromDecimal($data['amount'] ?? '0', $account->currency_code);
        }

        if (! $amount->isPositive()) {
            return back()->with('error', 'Enter an amount greater than zero.');
        }

        $intent = $this->payments->createIntent(
            $account,
            $amount->minor,
            'web',
            'sandbox',
            (string) Str::uuid(),
            $request->user(),
            ['channel' => 'citizen_portal'],
        );

        return redirect()->route('portal.pay.show', $intent->id);
    }

    public function payShow(Request $request, string $intent)
    {
        $model = PaymentIntent::findOrFail($intent);
        $account = $this->links->requireAccount($request->user(), $model->customer_account_id);

        $checkout = $this->payments->gateway($model->gateway)->createCheckout($model);

        return Inertia::render('Portal/PayShow', [
            'intent' => [
                'id' => $model->id,
                'reference' => $model->reference,
                'amount_minor' => $model->amount_minor,
                'currency' => $model->currency_code,
                'status' => $model->status,
                'account' => $account->account_number,
            ],
            'checkout' => $checkout,
        ]);
    }

    public function sandboxConfirm(Request $request, string $intent)
    {
        $model = PaymentIntent::findOrFail($intent);
        $this->links->requireAccount($request->user(), $model->customer_account_id);

        if ($model->status !== 'verified') {
            $payment = $this->payments->settle($model, 'succeeded', 'SBX-PORTAL-'.strtoupper((string) Str::ulid()), [
                'amount_minor' => $model->amount_minor,
                'channel' => 'sandbox_portal',
            ]);

            if ($payment) {
                return redirect()->route('portal.dashboard')
                    ->with('success', "Sandbox payment verified. Receipt {$payment->receipt?->receipt_number} issued for ".Money::fromMinor($payment->amount_minor)->format().'.');
            }
        }

        return redirect()->route('portal.dashboard')->with('info', 'This payment has already been verified.');
    }

    public function linkForm(Request $request)
    {
        $pending = PortalAccountLink::where('user_id', $request->user()->getKey())
            ->where('status', 'pending')
            ->with('account')
            ->get()
            ->map(fn (PortalAccountLink $link) => ['id' => $link->id, 'account' => $link->account?->account_number]);

        return Inertia::render('Portal/Link', [
            'pending' => $pending,
            'verified' => $this->links->linksFor($request->user())->map(fn ($link) => [
                'id' => $link->id,
                'account' => $link->account?->account_number,
                'since' => $link->verified_at?->toDateString(),
            ]),
        ]);
    }

    public function requestLink(Request $request)
    {
        $data = $request->validate([
            'account_number' => ['required', 'string', 'max:24'],
            'relationship' => ['required', 'in:owner,representative'],
        ]);

        $result = $this->links->request($request->user(), $data['account_number'], $data['relationship']);

        return back()->with('info', $result['message'])
            ->with('link_id', $result['link']?->id)
            ->with('masked', $result['masked']);
    }

    public function verifyLink(Request $request, string $link)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $model = PortalAccountLink::findOrFail($link);

        try {
            $this->links->verify($request->user(), $model, $data['code']);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('portal.link')->with('success', 'Account linked. You can now view bills and make payments.');
    }

    public function revokeLink(Request $request, string $link)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']]);
        $model = PortalAccountLink::findOrFail($link);

        $this->links->revoke($model, $request->user(), $data['reason']);

        return back()->with('success', 'Account link revoked.');
    }

    public function requests(Request $request)
    {
        $user = $request->user();
        $accountIds = $this->links->linksFor($user)->pluck('customer_account_id');

        $requests = ServiceRequest::where(function ($query) use ($user, $accountIds) {
            $query->where('submitted_by', $user->getKey())
                ->orWhereIn('customer_account_id', $accountIds);
        })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServiceRequest $serviceRequest) => [
                'reference' => $serviceRequest->reference,
                'category' => $serviceRequest->category,
                'subject' => $serviceRequest->subject,
                'status' => $serviceRequest->status,
                'priority' => $serviceRequest->priority,
                'created_at' => $serviceRequest->created_at?->toIso8601String(),
                'resolution' => $serviceRequest->resolution,
            ]);

        return Inertia::render('Portal/Requests', [
            'requests' => $requests,
            'accounts' => $this->links->linksFor($user)->map(fn ($link) => [
                'id' => $link->account?->id,
                'number' => $link->account?->account_number,
            ]),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'string'],
            'category' => ['required', 'in:billing_dispute,leak,water_interruption,refuse,complaint,other'],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:4000'],
        ]);

        $account = null;

        if (! empty($data['account_id'])) {
            $account = $this->links->requireAccount($request->user(), $data['account_id']);
        }

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

        return back()->with('success', "Service request {$reference} submitted. You can track it here.");
    }
}
