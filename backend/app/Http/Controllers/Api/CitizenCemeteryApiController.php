<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitizenCemeteryApiController extends Controller
{
    public function index()
    {
        $conn = DB::connection('tenant');

        $cemeteries = $conn->table('cemeteries')->where('status', 'active')->get()->map(function ($c) use ($conn) {
            $availableCount = $conn->table('grave_plots')
                ->where('cemetery_id', $c->id)
                ->where('status', 'available')
                ->count();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'location' => $c->location,
                'status' => $c->status,
                'total_plots' => (int) $c->total_plots,
                'available_plots' => $availableCount,
            ];
        });

        $sections = [
            ['id' => 'lawn_a', 'name' => 'Section A - Lawn Cemetery', 'depth' => 'double_8ft', 'fee_usd' => 200.0, 'fee_minor' => 20000, 'description' => 'Perpetual manicured lawn with marble/granite headstones.'],
            ['id' => 'general_b', 'name' => 'Section B - General Municipal', 'depth' => 'single_6ft', 'fee_usd' => 150.0, 'fee_minor' => 15000, 'description' => 'Standard municipal cemetery section.'],
            ['id' => 'infant_c', 'name' => 'Section C - Infant Section', 'depth' => 'single_6ft', 'fee_usd' => 80.0, 'fee_minor' => 8000, 'description' => 'Dedicated infant and children memorial lawn.'],
            ['id' => 'anglican_d', 'name' => 'Section D - Denominational / Memorial', 'depth' => 'double_8ft', 'fee_usd' => 180.0, 'fee_minor' => 18000, 'description' => 'Denominational and family shared plots.'],
        ];

        return response()->json([
            'cemeteries' => $cemeteries,
            'sections' => $sections,
        ]);
    }

    public function myReservations(Request $request)
    {
        $conn = DB::connection('tenant');
        $user = $request->user();

        // Plots reserved by user name or email
        $plots = $conn->table('grave_plots')
            ->join('cemeteries', 'grave_plots.cemetery_id', '=', 'cemeteries.id')
            ->where(function ($q) use ($user) {
                $q->where('grave_plots.reserved_by', 'like', '%' . $user->name . '%')
                  ->orWhere('grave_plots.reserved_contact', 'like', '%' . ($user->phone ?? 'XYZ') . '%');
            })
            ->select('grave_plots.*', 'cemeteries.name as cemetery_name', 'cemeteries.location as cemetery_location')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'cemetery_name' => $p->cemetery_name,
                'cemetery_location' => $p->cemetery_location,
                'plot_number' => $p->plot_number,
                'section' => $p->section,
                'block' => $p->block,
                'depth' => $p->depth,
                'status' => $p->status,
                'reserved_by' => $p->reserved_by,
                'reserved_at' => $p->reserved_at,
                'plot_fee_usd' => number_format($p->plot_fee_minor / 100, 2),
            ]);

        // Burial orders
        $interments = $conn->table('cemetery_interments')
            ->join('grave_plots', 'cemetery_interments.grave_plot_id', '=', 'grave_plots.id')
            ->join('cemeteries', 'grave_plots.cemetery_id', '=', 'cemeteries.id')
            ->where('cemetery_interments.next_of_kin_name', 'like', '%' . $user->name . '%')
            ->select(
                'cemetery_interments.*',
                'grave_plots.plot_number',
                'grave_plots.section',
                'cemeteries.name as cemetery_name'
            )
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'interment_order_number' => $i->interment_order_number,
                'deceased_name' => $i->deceased_name,
                'date_of_interment' => $i->date_of_interment,
                'burial_permit_number' => $i->burial_permit_number,
                'cemetery_name' => $i->cemetery_name,
                'plot_number' => $i->plot_number,
                'status' => $i->status,
            ]);

        return response()->json([
            'reservations' => $plots,
            'interments' => $interments,
        ]);
    }

    public function reserve(Request $request)
    {
        $data = $request->validate([
            'cemetery_id' => ['required', 'string'],
            'section_name' => ['required', 'string'],
            'depth' => ['required', 'string'],
            'purchaser_name' => ['required', 'string', 'max:120'],
            'purchaser_phone' => ['required', 'string', 'max:60'],
            'deceased_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $conn = DB::connection('tenant');
        $deceasedSuffix = !empty($data['deceased_name']) ? " (For: {$data['deceased_name']})" : '';

        // Find or create an available plot in that section
        $availablePlot = $conn->table('grave_plots')
            ->where('cemetery_id', $data['cemetery_id'])
            ->where('status', 'available')
            ->first();

        if (! $availablePlot) {
            // Dynamically allocate next serialized plot
            $count = $conn->table('grave_plots')->where('cemetery_id', $data['cemetery_id'])->count();
            $plotId = (string) Str::ulid();
            $plotNumber = 'PLOT-' . strtoupper(substr($data['section_name'], 0, 1)) . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $feeMinor = str_contains(strtolower($data['section_name']), 'lawn') ? 20000 : 15000;

            $conn->table('grave_plots')->insert([
                'id' => $plotId,
                'cemetery_id' => $data['cemetery_id'],
                'section' => $data['section_name'],
                'block' => 'BLK-' . rand(1, 10),
                'plot_number' => $plotNumber,
                'depth' => $data['depth'],
                'status' => 'reserved',
                'reserved_by' => $data['purchaser_name'] . $deceasedSuffix,
                'reserved_contact' => $data['purchaser_phone'],
                'reserved_at' => now(),
                'plot_fee_minor' => $feeMinor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $plot = $conn->table('grave_plots')->where('id', $plotId)->first();
        } else {
            $conn->table('grave_plots')->where('id', $availablePlot->id)->update([
                'status' => 'reserved',
                'reserved_by' => $data['purchaser_name'] . $deceasedSuffix,
                'reserved_contact' => $data['purchaser_phone'],
                'reserved_at' => now(),
                'depth' => $data['depth'],
                'updated_at' => now(),
            ]);
            $plot = $conn->table('grave_plots')->where('id', $availablePlot->id)->first();
        }

        $cemetery = $conn->table('cemeteries')->where('id', $data['cemetery_id'])->first();

        return response()->json([
            'message' => "Grave plot [{$plot->plot_number}] successfully reserved at {$cemetery->name}.",
            'plot' => [
                'id' => $plot->id,
                'plot_number' => $plot->plot_number,
                'cemetery_name' => $cemetery->name,
                'section' => $plot->section,
                'depth' => $plot->depth,
                'fee_usd' => number_format($plot->plot_fee_minor / 100, 2),
                'status' => 'reserved',
            ],
        ], 201);
    }
}
