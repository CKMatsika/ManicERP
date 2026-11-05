<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $accounts = CustomerAccount::with(['primaryParty', 'property.ward'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('account_number', 'like', "%{$search}%")
                        ->orWhereHas('primaryParty', fn ($party) => $party->where('display_name', 'like', "%{$search}%"))
                        ->orWhereHas('property', fn ($property) => $property->where('parcel_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('account_number')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CustomerAccount $account) => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'party' => $account->primaryParty?->display_name,
                'property' => $account->property?->parcel_number,
                'ward' => $account->property?->ward?->name,
                'type' => $account->account_type,
                'status' => $account->status,
                'outstanding_minor' => $account->outstandingBalanceMinor(),
            ]);

        return Inertia::render('Revenue/Accounts', [
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    public function show(Request $request, string $account)
    {
        $model = CustomerAccount::with(['primaryParty.contacts', 'property.ward', 'connections.meters', 'liabilities.party'])
            ->findOrFail($account);

        $bills = Bill::where('customer_account_id', $model->id)
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get()
            ->map(fn (Bill $bill) => [
                'id' => $bill->id,
                'number' => $bill->bill_number,
                'service' => $bill->service_type,
                'period' => $bill->period_start?->toDateString().' to '.$bill->period_end?->toDateString(),
                'total_minor' => $bill->total_minor,
                'paid_minor' => $bill->paid_minor,
                'balance_minor' => $bill->balance_minor,
                'status' => $bill->status,
                'due_date' => $bill->due_date?->toDateString(),
                'snapshot' => $bill->calculation_snapshot,
            ]);

        $payments = Payment::where('customer_account_id', $model->id)
            ->with('receipt')
            ->orderByDesc('received_at')
            ->limit(50)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'number' => $payment->payment_number,
                'receipt' => $payment->receipt?->receipt_number,
                'amount_minor' => $payment->amount_minor,
                'method' => $payment->method,
                'status' => $payment->status,
                'source' => $payment->verification_source,
                'received_at' => $payment->received_at?->toIso8601String(),
            ]);

        return Inertia::render('Revenue/AccountShow', [
            'account' => [
                'id' => $model->id,
                'account_number' => $model->account_number,
                'type' => $model->account_type,
                'status' => $model->status,
                'opened_on' => $model->opened_on?->toDateString(),
                'currency' => $model->currency_code,
                'party' => [
                    'name' => $model->primaryParty?->display_name,
                    'code' => $model->primaryParty?->code,
                    'type' => $model->primaryParty?->party_type,
                    'contacts' => $model->primaryParty?->contacts->map(fn ($contact) => [
                        'type' => $contact->type,
                        'value' => $contact->value,
                        'verified' => $contact->verified_at !== null,
                    ]),
                ],
                'property' => $model->property ? [
                    'parcel' => $model->property->parcel_number,
                    'address' => $model->property->address_line,
                    'suburb' => $model->property->suburb,
                    'ward' => $model->property->ward?->name,
                ] : null,
                'connections' => $model->connections->map(fn ($connection) => [
                    'number' => $connection->connection_number,
                    'service' => $connection->service_type,
                    'status' => $connection->status,
                    'meters' => $connection->meters->map(fn ($meter) => [
                        'serial' => $meter->serial_number,
                        'status' => $meter->status,
                        'installed_on' => $meter->installed_on?->toDateString(),
                    ]),
                ]),
                'outstanding_minor' => $model->outstandingBalanceMinor(),
            ],
            'bills' => $bills,
            'payments' => $payments,
        ]);
    }
}
