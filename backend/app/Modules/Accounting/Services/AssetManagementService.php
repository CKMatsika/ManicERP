<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AssetCategory;
use App\Modules\Accounting\Models\AssetDepreciationLog;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetManagementService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function createAsset(array $data, ?User $user = null): FixedAsset
    {
        return DB::connection('tenant')->transaction(function () use ($data, $user) {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $year = date('Y');
            $count = FixedAsset::count() + 1;
            $assetNumber = 'AST-' . $year . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);

            $purchaseCostMinor = (int) ($data['purchase_cost_minor'] ?? 0);
            $residualValueMinor = (int) ($data['residual_value_minor'] ?? 0);

            $asset = FixedAsset::create([
                'asset_number' => $assetNumber,
                'asset_category_id' => $category->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'serial_or_vin' => $data['serial_or_vin'] ?? null,
                'department' => $data['department'] ?? 'Engineering & Works',
                'custodian_name' => $data['custodian_name'] ?? null,
                'location_name' => $data['location_name'] ?? 'Civic Centre',
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? now()->toDateString(),
                'purchase_cost_minor' => $purchaseCostMinor,
                'residual_value_minor' => $residualValueMinor,
                'current_carrying_amount_minor' => $purchaseCostMinor,
                'accumulated_depreciation_minor' => 0,
                'condition' => $data['condition'] ?? 'good',
                'status' => 'in_service',
                'last_audit_date' => now()->toDateString(),
                'qr_code_data' => "MANIC-ERP:ASSET:{$assetNumber}:" . strtoupper(Str::random(6)),
            ]);

            $this->audit->record(
                action: 'accounting.fixed_asset.created',
                subject: $asset,
                after: ['asset_number' => $assetNumber, 'cost_minor' => $purchaseCostMinor],
                module: 'accounting',
            );

            return $asset;
        });
    }

    public function runDepreciationForPeriod(string $period, ?User $user = null): array
    {
        return DB::connection('tenant')->transaction(function () use ($period) {
            $assets = FixedAsset::with('category')
                ->where('status', 'in_service')
                ->where('current_carrying_amount_minor', '>', 0)
                ->get();

            $processedCount = 0;
            $totalDepreciatedMinor = 0;

            foreach ($assets as $asset) {
                $category = $asset->category;
                if (! $category || $category->depreciation_method === 'none') {
                    continue;
                }

                // Check if already processed for this period
                $alreadyLogged = AssetDepreciationLog::where('fixed_asset_id', $asset->id)
                    ->where('financial_period', $period)
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $usefulLifeMonths = max(1, $category->useful_life_years * 12);
                $depreciableBase = max(0, $asset->purchase_cost_minor - $asset->residual_value_minor);
                $monthlyDepreciation = (int) round($depreciableBase / $usefulLifeMonths);

                // Cap depreciation so carrying amount doesn't go below residual value
                $maxPossible = max(0, $asset->current_carrying_amount_minor - $asset->residual_value_minor);
                $monthlyDepreciation = min($monthlyDepreciation, $maxPossible);

                if ($monthlyDepreciation <= 0) {
                    continue;
                }

                $nbvBefore = $asset->current_carrying_amount_minor;
                $nbvAfter = $nbvBefore - $monthlyDepreciation;

                AssetDepreciationLog::create([
                    'fixed_asset_id' => $asset->id,
                    'financial_period' => $period,
                    'depreciation_amount_minor' => $monthlyDepreciation,
                    'net_book_value_before_minor' => $nbvBefore,
                    'net_book_value_after_minor' => $nbvAfter,
                ]);

                $asset->update([
                    'current_carrying_amount_minor' => $nbvAfter,
                    'accumulated_depreciation_minor' => $asset->accumulated_depreciation_minor + $monthlyDepreciation,
                ]);

                $processedCount++;
                $totalDepreciatedMinor += $monthlyDepreciation;
            }

            return [
                'period' => $period,
                'assets_processed' => $processedCount,
                'total_depreciation_usd' => number_format($totalDepreciatedMinor / 100, 2),
                'total_depreciation_minor' => $totalDepreciatedMinor,
            ];
        });
    }

    public function performAudit(FixedAsset $asset, string $condition, ?float $gpsLat, ?float $gpsLng, ?string $notes = null): FixedAsset
    {
        $asset->update([
            'condition' => $condition,
            'gps_lat' => $gpsLat ?? $asset->gps_lat,
            'gps_lng' => $gpsLng ?? $asset->gps_lng,
            'last_audit_date' => now()->toDateString(),
        ]);

        $this->audit->record(
            action: 'accounting.fixed_asset.audited',
            subject: $asset,
            after: ['condition' => $condition, 'last_audit_date' => now()->toDateString()],
            module: 'accounting',
        );

        return $asset;
    }

    public function getAssetRegisterSummary(): array
    {
        $assets = FixedAsset::with('category')->orderByDesc('created_at')->get();

        $totalHistoricalCost = $assets->sum('purchase_cost_minor');
        $totalCarryingAmount = $assets->sum('current_carrying_amount_minor');
        $totalAccumulatedDepreciation = $assets->sum('accumulated_depreciation_minor');

        $byCategory = $assets->groupBy('asset_category_id')->map(function ($group) {
            $cat = $group->first()->category;
            return [
                'category_name' => $cat?->name ?? 'Unclassified',
                'category_code' => $cat?->code ?? 'N/A',
                'count' => $group->count(),
                'carrying_amount_usd' => number_format($group->sum('current_carrying_amount_minor') / 100, 2),
            ];
        })->values();

        $byDepartment = $assets->groupBy('department')->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'count' => $group->count(),
                'carrying_amount_usd' => number_format($group->sum('current_carrying_amount_minor') / 100, 2),
            ];
        })->values();

        return [
            'total_assets_count' => $assets->count(),
            'total_historical_cost_usd' => number_format($totalHistoricalCost / 100, 2),
            'total_carrying_amount_usd' => number_format($totalCarryingAmount / 100, 2),
            'total_accumulated_depreciation_usd' => number_format($totalAccumulatedDepreciation / 100, 2),
            'by_category' => $byCategory,
            'by_department' => $byDepartment,
            'recent_assets' => $assets->take(15)->map(function ($a) {
                return [
                    'id' => $a->id,
                    'asset_number' => $a->asset_number,
                    'name' => $a->name,
                    'category' => $a->category?->name,
                    'department' => $a->department,
                    'location' => $a->location_name,
                    'cost_usd' => number_format($a->purchase_cost_minor / 100, 2),
                    'nbv_usd' => number_format($a->current_carrying_amount_minor / 100, 2),
                    'condition' => $a->condition,
                    'status' => $a->status,
                    'last_audit' => $a->last_audit_date?->toDateString(),
                    'qr_code' => $a->qr_code_data,
                ];
            })->all(),
        ];
    }
}

