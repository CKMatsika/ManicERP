<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\BankStatement;
use App\Modules\Revenue\Models\BankStatementLine;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Services\BankReconciliationService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::orderBy('code')->get()->map(fn (BankAccount $account) => [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'bank' => $account->bank_name,
            'gl_account' => $account->gl_account_code,
            'currency' => $account->currency_code,
        ]);

        $statements = BankStatement::with('bankAccount')
            ->withCount(['lines' => fn ($query) => $query->where('status', 'unmatched')])
            ->orderByDesc('statement_date')
            ->limit(20)
            ->get()
            ->map(fn (BankStatement $statement) => [
                'id' => $statement->id,
                'number' => $statement->statement_number,
                'bank' => $statement->bankAccount?->name,
                'date' => $statement->statement_date?->toDateString(),
                'closing_minor' => $statement->closing_balance_minor,
                'status' => $statement->status,
                'unmatched' => $statement->lines_count,
            ]);

        return Inertia::render('Revenue/Bank', [
            'accounts' => $accounts,
            'statements' => $statements,
        ]);
    }

    public function import(Request $request, BankReconciliationService $service)
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'string'],
            'statement_number' => ['required', 'string', 'max:64'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'string', 'max:24'],
            'closing_balance' => ['required', 'string', 'max:24'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $account = BankAccount::findOrFail($data['bank_account_id']);
        $contents = file_get_contents($request->file('file')->getRealPath());

        try {
            $statement = $service->importCsv(
                $account,
                $data['statement_number'],
                $data['statement_date'],
                Money::fromDecimal($data['opening_balance'])->minor,
                Money::fromDecimal($data['closing_balance'])->minor,
                $contents,
                $request->user(),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('revenue.bank.show', $statement->id)
            ->with('success', "Statement {$statement->statement_number} staged with {$statement->lines->count()} lines.");
    }

    public function show(string $statement, BankReconciliationService $service)
    {
        $model = BankStatement::with(['bankAccount', 'lines.payment'])->findOrFail($statement);

        $suggestions = collect($service->suggestions($model))->map(fn ($suggestion) => [
            'line_id' => $suggestion['line']->id,
            'line_number' => $suggestion['line']->line_number,
            'value_date' => $suggestion['line']->value_date?->toDateString(),
            'amount_minor' => $suggestion['line']->amount_minor,
            'reference' => $suggestion['line']->reference,
            'narrative' => $suggestion['line']->narrative,
            'status' => $suggestion['line']->status,
            'payment_id' => $suggestion['payment']?->id,
            'payment_number' => $suggestion['payment']?->payment_number,
            'confidence' => $suggestion['confidence'],
            'reason' => $suggestion['reason'],
        ]);

        return Inertia::render('Revenue/BankStatement', [
            'statement' => [
                'id' => $model->id,
                'number' => $model->statement_number,
                'date' => $model->statement_date?->toDateString(),
                'opening_minor' => $model->opening_balance_minor,
                'closing_minor' => $model->closing_balance_minor,
                'bank' => $model->bankAccount?->name,
                'status' => $model->status,
            ],
            'suggestions' => $suggestions,
            'payments' => Payment::where('status', 'verified')->orderByDesc('received_at')->limit(100)->get(['id', 'payment_number', 'amount_minor', 'received_at']),
        ]);
    }

    public function match(Request $request, BankReconciliationService $service, string $line)
    {
        $data = $request->validate(['payment_id' => ['required', 'string']]);

        $lineModel = BankStatementLine::findOrFail($line);
        $payment = Payment::findOrFail($data['payment_id']);

        try {
            $service->confirmMatch($lineModel, $payment, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Statement line matched and bank confirmation recorded.');
    }

    public function reconcile(Request $request, BankReconciliationService $service, string $statement)
    {
        $model = BankStatement::with('bankAccount')->findOrFail($statement);

        $result = $service->reconcile($model->bankAccount, $model, $request->user());

        return back()->with(
            $result->variance_minor === 0 ? 'success' : 'warning',
            'Reconciliation '.$result->status.'. Cashbook '.Money::fromMinor($result->cashbook_balance_minor)->format()
            .' vs statement '.Money::fromMinor($result->statement_closing_minor)->format()
            .', variance '.Money::fromMinor($result->variance_minor)->format().'.',
        );
    }
}
