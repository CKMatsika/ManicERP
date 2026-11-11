<?php

namespace App\Http\Controllers\Land;

use App\Http\Controllers\Controller;
use App\Modules\Land\Models\LandParcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GisCadastreController extends Controller
{
    public function index(Request $request): Response
    {
        $conn = DB::connection('tenant');

        $query = $conn->table('land_parcels');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('parcel_number', 'like', "%{$search}%")
                  ->orWhere('suburb', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ward', 'like', "%{$search}%");
            });
        }

        if ($ward = $request->input('ward')) {
            $query->where('ward', $ward);
        }

        if ($category = $request->input('category')) {
            $query->where('stand_category', $category);
        }

        if ($allocationStatus = $request->input('status')) {
            $query->where('allocation_status', $allocationStatus);
        }

        if ($servicing = $request->input('servicing')) {
            $query->where('servicing_status', $servicing);
        }

        $parcels = $query->orderBy('parcel_number')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'parcel_number' => $p->parcel_number,
                'description' => $p->description,
                'ward' => $p->ward ?? 'W01',
                'suburb' => $p->suburb ?? 'Central',
                'stand_category' => $p->stand_category ?? 'high_density',
                'area_sqm' => (float) ($p->area_sqm ?? 300),
                'servicing_status' => $p->servicing_status ?? 'serviced',
                'purchase_price_minor' => (int) ($p->purchase_price_minor ?? 200000),
                'deposit_required_minor' => (int) ($p->deposit_required_minor ?? 35000),
                'currency_code' => $p->currency_code ?? 'USD',
                'allocation_status' => $p->allocation_status ?? 'available',
                'latitude' => $p->latitude ? (float) $p->latitude : -18.9654,
                'longitude' => $p->longitude ? (float) $p->longitude : 32.6412,
                'coordinate_source' => $p->coordinate_source ?? 'Surveyor General Cadastral Diagram',
                'coordinate_accuracy' => $p->coordinate_accuracy ?? 'GPS RTK 0.05m',
                'status' => $p->status,
                'updated_at' => $p->updated_at,
            ];
        });

        // Summary metrics
        $allParcels = $conn->table('land_parcels')->get();
        $stats = [
            'total_stands' => $allParcels->count(),
            'available_stands' => $allParcels->where('allocation_status', 'available')->count(),
            'allocated_stands' => $allParcels->where('allocation_status', 'allocated')->count(),
            'reserved_stands' => $allParcels->where('allocation_status', 'reserved')->count(),
            'total_area_sqm' => (float) $allParcels->sum('area_sqm'),
            'total_valuation_minor' => (int) $allParcels->sum('purchase_price_minor'),
            'high_density_count' => $allParcels->where('stand_category', 'high_density')->count(),
            'medium_density_count' => $allParcels->where('stand_category', 'medium_density')->count(),
            'low_density_count' => $allParcels->where('stand_category', 'low_density')->count(),
            'commercial_count' => $allParcels->where('stand_category', 'commercial')->count(),
            'industrial_count' => $allParcels->where('stand_category', 'industrial')->count(),
        ];

        $suburbs = $conn->table('land_parcels')->select('suburb')->distinct()->pluck('suburb')->filter()->values();
        $wards = $conn->table('land_parcels')->select('ward')->distinct()->pluck('ward')->filter()->values();

        return Inertia::render('Land/GisCadastre', [
            'parcels' => $parcels,
            'stats' => $stats,
            'suburbs' => $suburbs,
            'wards' => $wards,
            'filters' => $request->only(['search', 'ward', 'category', 'status', 'servicing']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parcel_number' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:240'],
            'ward' => ['required', 'string', 'max:64'],
            'suburb' => ['required', 'string', 'max:80'],
            'stand_category' => ['required', 'string'],
            'area_sqm' => ['required', 'numeric', 'min:50'],
            'servicing_status' => ['required', 'string'],
            'purchase_price_usd' => ['required', 'numeric', 'min:0'],
            'deposit_usd' => ['required', 'numeric', 'min:0'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'coordinate_source' => ['nullable', 'string', 'max:120'],
            'coordinate_accuracy' => ['nullable', 'string', 'max:60'],
        ]);

        $conn = DB::connection('tenant');

        $conn->table('land_parcels')->insert([
            'id' => (string) Str::ulid(),
            'parcel_number' => strtoupper(trim($data['parcel_number'])),
            'description' => $data['description'],
            'ward' => $data['ward'],
            'suburb' => $data['suburb'],
            'stand_category' => $data['stand_category'],
            'area_sqm' => $data['area_sqm'],
            'servicing_status' => $data['servicing_status'],
            'purchase_price_minor' => (int) round($data['purchase_price_usd'] * 100),
            'deposit_required_minor' => (int) round($data['deposit_usd'] * 100),
            'currency_code' => 'USD',
            'allocation_status' => 'available',
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'coordinate_source' => $data['coordinate_source'] ?? 'Surveyor General Cadastral Diagram',
            'coordinate_accuracy' => $data['coordinate_accuracy'] ?? 'GPS RTK 0.05m',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Cadastral stand [{$data['parcel_number']}] successfully captured into GIS property database.");
    }
}

