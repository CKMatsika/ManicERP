<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\ChartImportBatch;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Services\ChartImportService;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;

class ChartController extends Controller
{
    public function index()
    {
        $versions = ChartVersion::withCount('accounts')->orderByDesc('created_at')->get();

        $active = ChartVersion::where('status', 'active')->first();

        $accounts = $active
            ? GlAccount::where('chart_version_id', $active->id)->orderBy('code')->get()->map(fn (GlAccount $account) => [
                'code' => $account->code,
                'name' => $account->name,
                'class' => $account->account_class,
                'type' => $account->account_type,
                'normal_balance' => $account->normal_balance,
                'is_control' => $account->is_control,
            ])
            : collect();

        $batches = ChartImportBatch::orderByDesc('created_at')->limit(10)->get()->map(fn (ChartImportBatch $batch) => [
            'id' => $batch->id,
            'file' => $batch->file_name,
            'status' => $batch->status,
            'rows' => $batch->row_count,
            'errors' => $batch->error_count,
            'created_at' => $batch->created_at?->toIso8601String(),
            'chart_version' => $batch->chart_version_id,
        ]);

        return Inertia::render('Accounting/Chart', [
            'versions' => $versions->map(fn (ChartVersion $version) => [
                'id' => $version->id,
                'name' => $version->name,
                'version_no' => $version->version_no,
                'status' => $version->status,
                'source_type' => $version->source_type,
                'production_blocker' => $version->production_blocker,
                'accounts_count' => $version->accounts_count,
                'effective_from' => $version->effective_from?->toDateString(),
            ]),
            'accounts' => $accounts,
            'activeVersion' => $active?->name,
            'batches' => $batches,
            'demoBlocker' => (bool) $active?->production_blocker,
        ]);
    }

    public function import(Request $request, ChartImportService $service)
    {
        $request->validate([
            'file' => ['required', File::types(['csv', 'xlsx'])->max(5120)],
            'name' => ['required', 'string', 'max:120'],
            'source_type' => ['required', 'in:demo,official'],
            'source_authority' => ['nullable', 'string', 'max:191'],
        ]);

        $batch = $service->importFile($request->file('file'), [
            'name' => $request->string('name')->toString(),
            'source_type' => $request->string('source_type')->toString(),
            'source_authority' => $request->input('source_authority'),
        ], $request->user());

        if ($batch->error_count > 0) {
            return redirect()->route('accounting.chart.index')
                ->with('warning', "Import staged with {$batch->error_count} validation errors. Fix the file before approval.");
        }

        return redirect()->route('accounting.chart.index')
            ->with('success', "Import validated: {$batch->valid_count} rows ready for approval.");
    }

    public function approve(Request $request, ChartImportService $service, AuditLogger $audit, string $batch)
    {
        $model = ChartImportBatch::findOrFail($batch);

        try {
            $chart = $service->approve($model, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('accounting.chart.index')
            ->with('success', "Chart [{$chart->name}] approved and active.");
    }
}
