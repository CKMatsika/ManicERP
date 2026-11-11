<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\AllocationService;
use App\Modules\Revenue\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Revenue/Refunds', ['refunds' => Refund::with('account', 'payment.receipt')->latest()->limit(50)->get(),
            'allocations' => Gate::allows('revenue.payment.reverse') ? PaymentAllocation::with('bill', 'payment')->whereNull('reversed_at')->latest()->limit(100)->get() : [],
            'accounts' => CustomerAccount::where('status', 'active')->orderBy('account_number')->get(['id', 'account_number'])]);
    }

    public function store(Request $request, RefundService $service): RedirectResponse
    {
        $data = $request->validate(['customer_account_id' => 'required|string', 'payment_id' => 'nullable|string', 'amount_minor' => 'required|integer|min:1',
            'reason' => 'required|string|max:2000', 'idempotency_key' => 'required|string|max:191']);

        return $this->attempt(fn () => $service->request($data['customer_account_id'], (int) $data['amount_minor'], paymentId: $data['payment_id'] ?? null,
            reason: $data['reason'], requester: $request->user(), idempotencyKey: $data['idempotency_key']));
    }

    public function action(Request $request, string $refund, string $action, RefundService $service): RedirectResponse
    {
        abort_unless(in_array($action, ['approve', 'reject', 'settle'], true), 404);
        if ($action === 'reject') {
            $request->validate(['reason' => 'required|string|max:2000']);
        }

        return $this->attempt(fn () => $action === 'reject' ? $service->reject(Refund::findOrFail($refund), $request->user(), $request->input('reason'))
            : $service->{$action}(Refund::findOrFail($refund), $request->user()));
    }

    public function reverseAllocation(Request $request, string $allocation, AllocationService $service): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        return $this->attempt(fn () => $service->reverseAllocation(PaymentAllocation::findOrFail($allocation), $request->input('reason'), $request->user()));
    }

    private function attempt(callable $operation): RedirectResponse
    {
        try {
            $result = $operation();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result instanceof Refund && $result->status === 'failed' ? 'Sandbox settlement failed; reservation retained for retry.' : 'Refund workflow updated. Sandbox only; no real money moved.');
    }
}
