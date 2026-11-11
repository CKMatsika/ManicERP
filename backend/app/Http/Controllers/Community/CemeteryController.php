<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CemeteryController extends Controller
{
    public function index()
    {
        $conn = DB::connection('tenant');

        $cemeteries = $conn->table('cemeteries')->get();
        $plots = $conn->table('grave_plots')
            ->join('cemeteries', 'grave_plots.cemetery_id', '=', 'cemeteries.id')
            ->select('grave_plots.*', 'cemeteries.name as cemetery_name')
            ->orderBy('grave_plots.section')
            ->orderBy('grave_plots.block')
            ->orderBy('grave_plots.plot_number')
            ->get();

        $interments = $conn->table('cemetery_interments')
            ->join('grave_plots', 'cemetery_interments.grave_plot_id', '=', 'grave_plots.id')
            ->join('cemeteries', 'grave_plots.cemetery_id', '=', 'cemeteries.id')
            ->select(
                'cemetery_interments.*',
                'grave_plots.plot_number',
                'grave_plots.section',
                'grave_plots.depth',
                'cemeteries.name as cemetery_name'
            )
            ->orderByDesc('cemetery_interments.date_of_interment')
            ->get();

        return Inertia::render('Community/Cemeteries', [
            'cemeteries' => $cemeteries,
            'plots' => $plots,
            'interments' => $interments,
        ]);
    }

    public function storeInterment(Request $request)
    {
        $data = $request->validate([
            'grave_plot_id' => ['required', 'string'],
            'deceased_name' => ['required', 'string', 'max:160'],
            'deceased_national_id' => ['nullable', 'string', 'max:48'],
            'date_of_death' => ['required', 'date'],
            'date_of_interment' => ['required', 'date'],
            'burial_permit_number' => ['required', 'string', 'max:80'],
            'cause_of_death' => ['nullable', 'string', 'max:160'],
            'undertaker_name' => ['nullable', 'string', 'max:120'],
            'next_of_kin_name' => ['required', 'string', 'max:120'],
            'next_of_kin_phone' => ['required', 'string', 'max:60'],
            'interment_fee' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $conn = DB::connection('tenant');

        $intermentId = (string) Str::ulid();
        $orderNumber = 'BUR-' . date('Y') . '-' . strtoupper(Str::random(5));

        $conn->table('cemetery_interments')->insert([
            'id' => $intermentId,
            'grave_plot_id' => $data['grave_plot_id'],
            'interment_order_number' => $orderNumber,
            'deceased_name' => $data['deceased_name'],
            'deceased_national_id' => $data['deceased_national_id'],
            'date_of_death' => $data['date_of_death'],
            'date_of_interment' => $data['date_of_interment'],
            'burial_permit_number' => $data['burial_permit_number'],
            'cause_of_death' => $data['cause_of_death'],
            'undertaker_name' => $data['undertaker_name'],
            'next_of_kin_name' => $data['next_of_kin_name'],
            'next_of_kin_phone' => $data['next_of_kin_phone'],
            'interment_fee_minor' => (int) round($data['interment_fee'] * 100),
            'payment_status' => 'paid',
            'status' => 'completed',
            'notes' => $data['notes'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conn->table('grave_plots')->where('id', $data['grave_plot_id'])->update([
            'status' => 'interred',
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Burial order [{$orderNumber}] issued for [{$data['deceased_name']}].");
    }
}

