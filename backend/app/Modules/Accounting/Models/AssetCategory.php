<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'depreciation_method',
        'useful_life_years',
        'gl_asset_account',
        'gl_depreciation_expense_account',
        'gl_accumulated_depreciation_account',
        'description',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }
}

