<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FleetController extends Controller
{
    public function index()
    {
        $conn = DB::connection('tenant');

        $vehicles = $conn->table('fleet_vehicles')->orderBy('department')->orderBy('registration_number')->get();
        $coupons = $conn->table('fleet_fuel_coupons')
            ->join('fleet_vehicles', 'fleet_fuel_coupons.vehicle_id', '=', 'fleet_vehicles.id')
            ->select('fleet_fuel_coupons.*', 'fleet_vehicles.registration_number', 'fleet_vehicles.make', 'fleet_vehicles.model')
            ->orderByDesc('fleet_fuel_coupons.issue_date')
            ->get();

        $logbooks = $conn->table('fleet_logbook_entries')
            ->join('fleet_vehicles', 'fleet_logbook_entries.vehicle_id', '=', 'fleet_vehicles.id')
            ->select('fleet_logbook_entries.*', 'fleet_vehicles.registration_number')
            ->orderByDesc('fleet_logbook_entries.trip_date')
            ->get();

        return Inertia::render('Fleet/FleetManagement', [
            'vehicles' => $vehicles,
            'coupons' => $coupons,
            'logbooks' => $logbooks,
        ]);
    }

    public function issueCoupon(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'string'],
            'litres' => ['required', 'numeric', 'min:1'],
            'fuel_type' => ['required', 'string'],
            'purpose' => ['required', 'string', 'max:160'],
            'driver_name' => ['required', 'string', 'max:120'],
            'rate_per_litre' => ['required', 'numeric', 'min:0.5'],
        ]);

        $conn = DB::connection('tenant');
        $couponSerial = 'FL-CPN-' . date('Y') . '-' . strtoupper(Str::random(5));
        $costMinor = (int) round($data['litres'] * $data['rate_per_litre'] * 100);

        $conn->table('fleet_fuel_coupons')->insert([
            'id' => (string) Str::ulid(),
            'vehicle_id' => $data['vehicle_id'],
            'coupon_serial' => $couponSerial,
            'issue_date' => now()->toDateString(),
            'litres' => $data['litres'],
            'fuel_type' => $data['fuel_type'],
            'purpose' => $data['purpose'],
            'authorized_by' => $request->user()?->name ?? 'Fleet Officer',
            'driver_name' => $data['driver_name'],
            'cost_minor' => $costMinor,
            'status' => 'disbursed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Fuel coupon [{$couponSerial}] disbursed for {$data['litres']}L.");
    }
}

