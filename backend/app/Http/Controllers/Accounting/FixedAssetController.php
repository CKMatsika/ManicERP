<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AssetCategory;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Services\AssetManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(
        private readonly AssetManagementService $assets,
    ) {}

    public function index(): Response
    {
        $summary = $this->assets->getAssetRegisterSummary();
        $categories = AssetCategory::orderBy('name')->get();

        return Inertia::render('Assets/FixedAssets', [
            'summary' => $summary,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_category_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'serial_or_vin' => ['nullable', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:80'],
            'custodian_name' => ['nullable', 'string', 'max:120'],
            'location_name' => ['required', 'string', 'max:150'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'acquisition_date' => ['required', 'date'],
            'purchase_cost_minor' => ['required', 'integer', 'min:1'],
            'residual_value_minor' => ['nullable', 'integer', 'min:0'],
            'condition' => ['required', 'string', 'in:excellent,good,fair,poor,scrap'],
        ]);

        $this->assets->createAsset($data, $request->user());

        return redirect()->back()->with('success', 'Fixed asset registered successfully in municipal register.');
    }

    public function depreciate(Request $request)
    {
        $data = $request->validate([
            'financial_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $result = $this->assets->runDepreciationForPeriod($data['financial_period'], $request->user());

        return redirect()->back()->with('success', "Depreciation executed for {$result['period']}: {$result['assets_processed']} assets depreciated (Total: USD {$result['total_depreciation_usd']}).");
    }

    public function audit(Request $request, string $id)
    {
        $data = $request->validate([
            'condition' => ['required', 'string', 'in:excellent,good,fair,poor,scrap'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = FixedAsset::findOrFail($id);
        $this->assets->performAudit($asset, $data['condition'], $data['gps_lat'] ?? null, $data['gps_lng'] ?? null, $data['notes'] ?? null);

        return redirect()->back()->with('success', "Physical audit verified for Asset {$asset->asset_number}.");
    }
}

