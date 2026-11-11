<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitizenEmergencyApiController extends Controller
{
    public function hotlines()
    {
        return response()->json([
            'hotlines' => [
                [
                    'service' => 'Council Emergency Control Room (24/7)',
                    'short_code' => '993 / 994',
                    'direct_phone' => '+263 20 64411',
                    'mobile_whatsapp' => '+263 77 219 9900',
                    'description' => '24-hour central dispatch for fire, medical, sewer, and water bursts.',
                    'icon' => 'phone_in_talk',
                    'color' => '0xFFDC2626',
                ],
                [
                    'service' => 'Municipal Fire & Rescue Brigade',
                    'short_code' => '993',
                    'direct_phone' => '+263 20 64411',
                    'description' => 'Structural fires, veld fires, hazardous chemical spills, and vehicle extrication.',
                    'icon' => 'local_fire_department',
                    'color' => '0xFFEA580C',
                ],
                [
                    'service' => 'Municipal Ambulance & Paramedics',
                    'short_code' => '994',
                    'direct_phone' => '+263 20 64412',
                    'description' => 'Emergency medical response, obstetric emergencies, and clinic transfers.',
                    'icon' => 'emergency',
                    'color' => '0xFFDC2626',
                ],
                [
                    'service' => 'Water Bursts & Rapid Leak Response',
                    'short_code' => 'Toll-Free 0800',
                    'direct_phone' => '+263 20 64420',
                    'mobile_whatsapp' => '+263 77 410 9922',
                    'description' => 'Report main water bursts, missing meters, low pressure, and continuous leaks.',
                    'icon' => 'water_drop',
                    'color' => '0xFF0284C7',
                ],
                [
                    'service' => 'Sewer Blockages & Health Hazards',
                    'short_code' => 'Hotline 2',
                    'direct_phone' => '+263 20 64425',
                    'description' => 'Sewer overflows, manhole repairs, and environmental health risks.',
                    'icon' => 'plumbing',
                    'color' => '0xFF0D9488',
                ],
                [
                    'service' => 'Municipal Police & Traffic Enforcement',
                    'short_code' => 'Control 4',
                    'direct_phone' => '+263 20 62200',
                    'description' => 'Traffic accidents, illegal vending, noise pollution, and rank management.',
                    'icon' => 'local_police',
                    'color' => '0xFF4F46E5',
                ],
            ],
            'operating_hours' => '24 Hours / 7 Days a Week',
            'headquarters' => 'Central Fire Station, Aerodrome Road, Mutare',
        ]);
    }

    public function myIncidents(Request $request)
    {
        $conn = DB::connection('tenant');
        $user = $request->user();

        $incidents = $conn->table('emergency_incidents')
            ->where(function ($q) use ($user) {
                $q->where('caller_name', 'like', '%' . $user->name . '%')
                  ->orWhere('caller_phone', 'like', '%' . ($user->phone ?? 'XYZ') . '%');
            })
            ->orderByDesc('dispatched_at')
            ->get()
            ->map(fn ($inc) => [
                'id' => $inc->id,
                'incident_number' => $inc->incident_number,
                'incident_type' => $inc->incident_type,
                'location_address' => $inc->location_address,
                'suburb' => $inc->suburb,
                'dispatched_at' => $inc->dispatched_at,
                'status' => $inc->status,
                'appliances_dispatched' => $inc->appliances_dispatched,
                'incident_report' => $inc->incident_report,
            ]);

        return response()->json(['incidents' => $incidents]);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'incident_type' => ['required', 'string'],
            'location_address' => ['required', 'string', 'max:180'],
            'suburb' => ['nullable', 'string', 'max:80'],
            'caller_name' => ['nullable', 'string', 'max:120'],
            'caller_phone' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:1000'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
        ]);

        $conn = DB::connection('tenant');
        $incNumber = 'SOS-' . date('Y') . '-' . strtoupper(Str::random(4));

        $user = $request->user();
        $callerName = !empty($data['caller_name']) ? $data['caller_name'] : ($user->name ?? 'Citizen Reporter');
        $callerPhone = !empty($data['caller_phone']) ? $data['caller_phone'] : ($user->phone ?? '+263 Unspecified');
        $suburb = !empty($data['suburb']) ? $data['suburb'] : 'Central / Unspecified';

        $appliances = match ($data['incident_type']) {
            'structural_fire' => 'Major Tender 1, Water Bowser 3, Utility 4',
            'veld_fire' => 'Light Quick-Attack Tender 2',
            'ambulance_emergency' => 'Advanced Life Support Ambulance AMB-02',
            'water_burst' => 'Engineering Water Maintenance Crew 1',
            'sewer_overflow' => 'Health & Sanitation Jetting Machine',
            default => 'Emergency Rapid Response Unit',
        };

        $serviceCharge = $data['incident_type'] === 'ambulance_emergency' ? 4500 : 0;

        $conn->table('emergency_incidents')->insert([
            'id' => (string) Str::ulid(),
            'incident_number' => $incNumber,
            'incident_type' => $data['incident_type'],
            'caller_name' => $callerName,
            'caller_phone' => $callerPhone,
            'location_address' => $data['location_address'],
            'suburb' => $suburb,
            'station_code' => 'STN-CENTRAL',
            'appliances_dispatched' => $appliances,
            'dispatched_at' => now(),
            'service_charge_minor' => $serviceCharge,
            'status' => 'dispatched',
            'incident_report' => $data['description'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => "Emergency report [{$incNumber}] received. First responders and appliances dispatched.",
            'incident' => [
                'incident_number' => $incNumber,
                'appliances_dispatched' => $appliances,
                'status' => 'dispatched',
                'dispatched_at' => now()->toIso8601String(),
            ],
        ], 201);
    }
}
