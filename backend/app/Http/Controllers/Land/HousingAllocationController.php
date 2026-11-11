<?php

namespace App\Http\Controllers\Land;

use App\Http\Controllers\Controller;
use App\Modules\Land\Models\HousingAllocation;
use App\Modules\Land\Models\HousingWaitingListEntry;
use App\Modules\Land\Models\LandParcel;
use App\Modules\Land\Services\HousingAllocationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HousingAllocationController extends Controller
{
    public function __construct(
        private readonly HousingAllocationService $allocations,
    ) {}

    public function index(Request $request)
    {
        $category = $request->query('category');

        $applicantsQuery = HousingWaitingListEntry::query()->where('status', 'active');
        if ($category) {
            $applicantsQuery->where('category', $category);
        }

        $applicants = $applicantsQuery
            ->orderByDesc('priority_points')
            ->orderBy('application_date')
            ->get();

        $parcels = $this->allocations->getAvailableParcels($category);

        $allocations = HousingAllocation::with(['waitingListEntry', 'landParcel'])
            ->latest('allocated_at')
            ->get()
            ->map(fn ($item) => $this->allocations->transformAllocation($item));

        return Inertia::render('Land/HousingAllocations', [
            'applicants' => $applicants,
            'availableParcels' => $parcels,
            'allocations' => $allocations,
            'selectedCategory' => $category,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'waiting_list_entry_id' => ['required', 'string'],
            'land_parcel_id' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allocation = $this->allocations->allocate(
            $data['waiting_list_entry_id'],
            $data['land_parcel_id'],
            ['notes' => $data['notes'] ?? 'Allocated via Council Staff Housing Desk.'],
            $request->user()
        );

        return redirect()->back()->with('success', "Stand [{$allocation->stand_number}] allocated under reference [{$allocation->allocation_reference}].");
    }
}

