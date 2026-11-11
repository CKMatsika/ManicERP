<?php

namespace App\Http\Controllers\Emergency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmergencyController extends Controller
{
    public function index()
    {
        $conn = DB::connection('tenant');

        $incidents = $conn->table('emergency_incidents')->orderByDesc('dispatched_at')->get();
        $inspections = $conn->table('fire_safety_inspections')->orderByDesc('inspection_date')->get();
        $hydrants = $conn->table('fire_hydrants')->orderBy('hydrant_code')->get();

        return Inertia::render('Emergency/EmergencyServices', [
            'incidents' => $incidents,
            'inspections' => $inspections,
            'hydrants' => $hydrants,
        ]);
    }

    public function storeIncident(Request $request)
    {
        $data = $request->validate([
            'incident_type' => ['required', 'string'],
            'caller_name' => ['required', 'string', 'max:120'],
            'caller_phone' => ['required', 'string', 'max:60'],
            'location_address' => ['required', 'string', 'max:180'],
            'suburb' => ['required', 'string', 'max:80'],
            'appliances_dispatched' => ['required', 'string', 'max:160'],
            'service_charge' => ['required', 'numeric', 'min:0'],
            'incident_report' => ['nullable', 'string'],
        ]);

        $conn = DB::connection('tenant');
        $incNumber = 'INC-' . date('Y') . '-' . strtoupper(Str::random(4));

        $conn->table('emergency_incidents')->insert([
            'id' => (string) Str::ulid(),
            'incident_number' => $incNumber,
            'incident_type' => $data['incident_type'],
            'caller_name' => $data['caller_name'],
            'caller_phone' => $data['caller_phone'],
            'location_address' => $data['location_address'],
            'suburb' => $data['suburb'],
            'station_code' => 'STN-CENTRAL',
            'appliances_dispatched' => $data['appliances_dispatched'],
            'dispatched_at' => now(),
            'service_charge_minor' => (int) round($data['service_charge'] * 100),
            'status' => 'dispatched',
            'incident_report' => $data['incident_report'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Emergency dispatch logged under reference [{$incNumber}].");
    }
}

