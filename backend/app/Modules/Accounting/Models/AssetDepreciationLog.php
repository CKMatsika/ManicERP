<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationLog extends TenantModel
{
    protected $fillable = [
        'fixed_asset_id',
        'financial_period',
        'depreciation_amount_minor',
        'net_book_value_before_minor',
        'net_book_value_after_minor',
        'journal_entry_id',
    ];

    protected $casts = [
        'depreciation_amount_minor' => 'integer',
        'net_book_value_before_minor' => 'integer',
        'net_book_value_after_minor' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}

