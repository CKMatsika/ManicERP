<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CapitalProjectsController extends Controller
{
    public function index()
    {
        $conn = DB::connection('tenant');

        $projects = $conn->table('capital_projects')->orderByDesc('commencement_date')->get();
        $certificates = $conn->table('capital_project_certificates')
            ->join('capital_projects', 'capital_project_certificates.capital_project_id', '=', 'capital_projects.id')
            ->select('capital_project_certificates.*', 'capital_projects.project_code', 'capital_projects.title as project_title')
            ->orderByDesc('capital_project_certificates.valuation_date')
            ->get();

        return Inertia::render('Projects/CapitalProjects', [
            'projects' => $projects,
            'certificates' => $certificates,
        ]);
    }

    public function issueCertificate(Request $request)
    {
        $data = $request->validate([
            'capital_project_id' => ['required', 'string'],
            'gross_certified' => ['required', 'numeric', 'min:1'],
            'cumulative_progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'certifying_engineer' => ['required', 'string', 'max:120'],
        ]);

        $conn = DB::connection('tenant');
        $project = $conn->table('capital_projects')->where('id', $data['capital_project_id'])->first();

        if (! $project) {
            abort(404, 'Project not found.');
        }

        $grossMinor = (int) round($data['gross_certified'] * 100);
        $retentionRate = (float) $project->retention_rate_percent;
        $retentionMinor = (int) round($grossMinor * ($retentionRate / 100));
        $netMinor = $grossMinor - $retentionMinor;

        $certCount = $conn->table('capital_project_certificates')
            ->where('capital_project_id', $project->id)
            ->count();
        $certNumber = 'IPC-' . str_pad($certCount + 1, 2, '0', STR_PAD_LEFT);

        $conn->table('capital_project_certificates')->insert([
            'id' => (string) Str::ulid(),
            'capital_project_id' => $project->id,
            'certificate_number' => $certNumber,
            'valuation_date' => now()->toDateString(),
            'gross_certified_minor' => $grossMinor,
            'retention_deducted_minor' => $retentionMinor,
            'net_payable_minor' => $netMinor,
            'cumulative_progress_percent' => $data['cumulative_progress_percent'],
            'certifying_engineer' => $data['certifying_engineer'],
            'approval_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update project totals
        $conn->table('capital_projects')->where('id', $project->id)->update([
            'physical_progress_percent' => $data['cumulative_progress_percent'],
            'certified_claims_minor' => $project->certified_claims_minor + $grossMinor,
            'retention_held_minor' => $project->retention_held_minor + $retentionMinor,
            'paid_to_date_minor' => $project->paid_to_date_minor + $netMinor,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Payment certificate [{$certNumber}] certified for [{$project->project_code}] with {$retentionRate}% retention deducted.");
    }
}

