<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\BillingRun;
use App\Modules\Revenue\Models\TariffVersion;
use App\Modules\Revenue\Services\BillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index()
    {
        $runs = BillingRun::orderByDesc('created_at')->limit(30)->get()->map(fn (BillingRun $run) => [
            'id' => $run->id,
            'number' => $run->run_number,
            'service' => $run->service_type,
            'period' => $run->period_start?->toDateString().' to '.$run->period_end?->toDateString(),
            'status' => $run->status,
            'totals' => $run->control_totals,
        ]);

        $tariffs = TariffVersion::with('bands')->orderByDesc('effective_from')->limit(20)->get()->map(fn (TariffVersion $tariff) => [
            'code' => $tariff->code,
            'name' => $tariff->name,
            'service' => $tariff->service_type,
            'basis' => $tariff->charge_basis,
            'status' => $tariff->status,
            'effective_from' => $tariff->effective_from?->toDateString(),
            'version' => $tariff->version_no,
            'bands' => $tariff->bands->map(fn ($band) => [
                'band' => $band->band_no,
                'from' => (string) $band->from_units,
                'to' => $band->to_units !== null ? (string) $band->to_units : null,
                'rate_minor' => $band->rate_minor,
                'fixed_minor' => $band->fixed_charge_minor,
            ]),
        ]);

        return Inertia::render('Revenue/Billing', [
            'runs' => $runs,
            'tariffs' => $tariffs,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'service_type' => ['required', 'in:rates,water,refuse,rental'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        try {
            $run = $this->billing->preview(
                $data['service_type'],
                $data['period_start'],
                $data['period_end'],
                [],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Billing run {$run->run_number} previewed. Review control totals before approval.");
    }

    public function approve(Request $request, string $run)
    {
        $model = BillingRun::findOrFail($run);

        try {
            $this->billing->approve($model, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Billing run {$model->run_number} approved.");
    }

    public function post(Request $request, string $run)
    {
        $model = BillingRun::findOrFail($run);

        try {
            $posted = $this->billing->post($model, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Billing run {$posted->run_number} posted. Bills and journals created.");
    }
}
