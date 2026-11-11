<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends TenantModel
{
    protected $fillable = [
        'asset_number',
        'asset_category_id',
        'name',
        'description',
        'serial_or_vin',
        'department',
        'custodian_name',
        'location_name',
        'gps_lat',
        'gps_lng',
        'acquisition_date',
        'purchase_cost_minor',
        'residual_value_minor',
        'current_carrying_amount_minor',
        'accumulated_depreciation_minor',
        'condition',
        'status',
        'last_audit_date',
        'qr_code_data',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'last_audit_date' => 'date',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'purchase_cost_minor' => 'integer',
        'residual_value_minor' => 'integer',
        'current_carrying_amount_minor' => 'integer',
        'accumulated_depreciation_minor' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function depreciationLogs(): HasMany
    {
        return $this->hasMany(AssetDepreciationLog::class);
    }
}

