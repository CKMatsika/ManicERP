<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Dimension;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function index(Request $request, ReportingService $reporting)
    {
        $entries = JournalEntry::with('period')
            ->orderByDesc('entry_date')
            ->orderByDesc('entry_number')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_date' => $entry->entry_date?->toDateString(),
                'description' => $entry->description,
                'journal_type' => $entry->journal_type,
                'status' => $entry->status,
                'total_debit_minor' => $entry->total_debit_minor,
                'currency' => $entry->currency_code,
                'period' => $entry->period?->code,
                'reversed' => $entry->reversed_by_id !== null,
            ]);

        $accounts = $reporting->accounts()->map(fn (GlAccount $account) => [
            'code' => $account->code,
            'name' => $account->name,
            'class' => $account->account_class,
        ]);

        $dimensions = Dimension::with(['values' => fn ($query) => $query->where('active_to', null)->select('id', 'dimension_id', 'code', 'name')])
            ->get()
            ->map(fn (Dimension $dimension) => [
                'code' => $dimension->code,
                'name' => $dimension->name,
                'values' => $dimension->values->map(fn ($value) => ['code' => $value->code, 'name' => $value->name]),
            ]);

        return Inertia::render('Accounting/Journals', [
            'entries' => $entries,
            'accounts' => $accounts,
            'dimensions' => $dimensions,
            'periods' => FinancialPeriod::orderByDesc('start_date')->limit(12)->get(['code', 'status', 'start_date', 'end_date']),
        ]);
    }

    public function store(Request $request, PostingService $posting)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:32'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:191'],
        ]);

        $lines = [];

        foreach ($data['lines'] as $line) {
            $debit = (int) round(((float) ($line['debit'] ?? 0)) * 100);
            $credit = (int) round(((float) ($line['credit'] ?? 0)) * 100);

            if ($debit === 0 && $credit === 0) {
                continue;
            }

            $lines[] = [
                'account_code' => $line['account_code'],
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        try {
            $entry = $posting->post([
                'journal_type' => 'manual',
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'lines' => $lines,
                'idempotency_key' => $request->input('idempotency_key'),
                'created_by' => $request->user()->getKey(),
            ]);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return redirect()->route('accounting.journals.index')
            ->with('success', "Journal {$entry->entry_number} posted.");
    }

    public function reverse(Request $request, PostingService $posting, string $journal)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'reversal_date' => ['required', 'date'],
        ]);

        $entry = JournalEntry::findOrFail($journal);

        try {
            $reversal = $posting->reverse($entry, $data['reason'], $data['reversal_date'], $request->user()->getKey());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Reversal {$reversal->entry_number} posted and linked.");
    }
}
