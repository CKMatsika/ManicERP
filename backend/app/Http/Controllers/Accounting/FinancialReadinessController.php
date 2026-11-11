<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\MigrationBatch;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Models\ReportLine;
use App\Modules\Accounting\Models\RevenuePolicy;
use App\Modules\Accounting\Services\AccountingSourceService;
use App\Modules\Accounting\Services\OpeningBalanceService;
use App\Modules\Accounting\Services\ReportMappingService;
use App\Modules\Accounting\Services\RevenuePolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReadinessController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Gate::any(['accounting.requirements.view', 'accounting.opening_balance.manage', 'accounting.opening_balance.approve', 'accounting.report_mapping.manage', 'accounting.report_mapping.approve', 'accounting.revenue_policy.manage', 'accounting.revenue_policy.approve', 'accounting.statements.view']), 403);
        $statement = null;
        if ($request->filled('report_id')) {
            Gate::authorize('accounting.statements.view');
            $data = $request->validate(['report_id' => 'required|string', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d',
                'compare_from' => 'nullable|date_format:Y-m-d', 'compare_to' => 'nullable|date_format:Y-m-d']);
            try {
                $statement = app(ReportMappingService::class)->statement(ReportDefinition::findOrFail($data['report_id']), $data['from'], $data['to'], $data['compare_from'] ?? null, $data['compare_to'] ?? null);
            } catch (\DomainException $e) {
                throw ValidationException::withMessages(['statement' => $e->getMessage()]);
            }
        }

        return Inertia::render('Accounting/Readiness', [
            'sources' => Gate::any(['accounting.requirements.view', 'accounting.revenue_policy.manage', 'accounting.revenue_policy.approve']) ? AccountingSourceDocument::all() : [],
            'requirements' => Gate::allows('accounting.requirements.view') ? app(AccountingSourceService::class)->summary() : [],
            'batches' => Gate::any(['accounting.opening_balance.manage', 'accounting.opening_balance.approve']) ? MigrationBatch::with('rows')->latest()->limit(20)->get() : [],
            'adoption' => Gate::allows('accounting.opening_balance.approve') ? app(OpeningBalanceService::class)->adoption() : null,
            'reports' => Gate::any(['accounting.report_mapping.manage', 'accounting.report_mapping.approve', 'accounting.statements.view']) ? ReportDefinition::with('lines.mappings')->orderBy('code')->orderByDesc('version')->get() : [],
            'policies' => Gate::any(['accounting.revenue_policy.manage', 'accounting.revenue_policy.approve']) ? RevenuePolicy::orderBy('charge_type')->orderByDesc('version')->get() : [],
            'statement' => $statement,
        ]);
    }

    public function sources(Request $request, AccountingSourceService $service): RedirectResponse
    {
        $service->seed();

        return back()->with('success', 'Sources examined and requirements extracted. Applicability and council approvals remain separate.');
    }

    public function guidance(Request $request, AccountingSourceService $service): RedirectResponse
    {
        $service->publishGuidance($request->user());

        return back()->with('success', 'Source excerpts approved for assistant retrieval as guidance. No council policy was approved.');
    }

    public function template(string $type, OpeningBalanceService $service): \Illuminate\Http\Response
    {
        return response($service->template($type), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="opening-template.csv"']);
    }

    public function import(Request $request, OpeningBalanceService $service): RedirectResponse
    {
        $data = $request->validate(['type' => 'required|in:opening_gl,opening_customer', 'file' => 'required|file|mimes:csv,txt|max:5120',
            'cutover_date' => 'required|date_format:Y-m-d', 'currency' => 'required|string|size:3']);

        return $this->attempt(function () use ($request, $service, $data) {
            $service->import($data['type'], $request->file('file')->get(), $request->file('file')->getClientOriginalName(), $data['cutover_date'], strtoupper($data['currency']), $request->user());
        }, 'Opening balance file staged. Review row errors and reconciliation before approval.');
    }

    public function adoption(Request $request, OpeningBalanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'adoption_start_date' => 'required|date_format:Y-m-d',
            'migration_cutover_date' => 'required|date_format:Y-m-d|after_or_equal:adoption_start_date',
        ]);

        return $this->attempt(function () use ($request, $service, $data) {
            $service->recordAdoption($data['adoption_start_date'], $data['migration_cutover_date'], $request->user());
        }, 'Adoption and migration dates recorded by the approving officer. They remain separate dates.');
    }

    public function batch(Request $request, string $batch, string $action, OpeningBalanceService $service): RedirectResponse
    {
        abort_unless(in_array($action, ['approve', 'post'], true), 404);

        return $this->attempt(fn () => $service->{$action}(MigrationBatch::findOrFail($batch), $request->user()), 'Opening balance batch '.$action.' completed.');
    }

    public function templates(Request $request, ReportMappingService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->templates($request->user()), 'Source-backed draft templates configured. Official posting codes remain required.');
    }

    public function version(Request $request, string $report, ReportMappingService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->nextVersion(ReportDefinition::findOrFail($report), $request->user()), 'New draft report version created; configure its mappings.');
    }

    public function mapping(Request $request, ReportMappingService $service): RedirectResponse
    {
        $data = $request->validate(['report_line_id' => 'required|string', 'gl_account_code' => 'required|string|max:32', 'effective_from' => 'required|date_format:Y-m-d',
            'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from', 'dimension_filters' => 'nullable|json']);
        $line = ReportLine::findOrFail($data['report_line_id']);
        unset($data['report_line_id']);
        $data['dimension_filters'] = json_decode($data['dimension_filters'] ?? '{}', true);

        return $this->attempt(fn () => $service->map($line, $data, $request->user()), 'Draft mapping saved.');
    }

    public function reportApprove(Request $request, string $report, ReportMappingService $service): RedirectResponse
    {
        $data = $request->validate(['effective_from' => 'required|date_format:Y-m-d', 'currency_code' => 'required|size:3', 'units' => 'required|in:units,thousands']);

        return $this->attempt(function () use ($request, $report, $data, $service) {
            $definition = ReportDefinition::findOrFail($report);
            if ($definition->status !== 'draft') {
                throw new \DomainException('Only draft report versions may be approved.');
            }
            DB::connection('tenant')->transaction(function () use ($definition, $data, $service, $request) {
                $definition->update($data);
                $service->approve($definition, $request->user());
            });
        }, 'Report mapping version approved. Missing schedules and official-chart blockers still apply.');
    }

    public function policy(Request $request, RevenuePolicyService $service): RedirectResponse
    {
        $data = $request->validate(['charge_type' => 'required|in:rates,water,refuse,rental', 'recognition_method' => 'required|in:immediate,deferred,advance_receipt,refundable_deposit',
            'receivable_account_code' => 'required|string|max:32', 'revenue_account_code' => 'nullable|string|max:32', 'deferred_account_code' => 'nullable|string|max:32',
            'source_document_id' => 'required|string', 'source_ref' => 'required|string|max:96', 'effective_from' => 'required|date_format:Y-m-d',
            'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from', 'notes' => 'required|string|max:10000']);
        AccountingSourceDocument::findOrFail($data['source_document_id']);

        return $this->attempt(fn () => $service->draft($data, $request->user()), 'Revenue policy drafted for independent approval.');
    }

    public function policyApprove(Request $request, string $policy, RevenuePolicyService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->approve(RevenuePolicy::findOrFail($policy), $request->user()), 'Revenue policy approved prospectively. Historical postings preserved.');
    }

    private function attempt(callable $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
