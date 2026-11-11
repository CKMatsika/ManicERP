<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Platform\Models\Document;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends TenantModel
{
    protected $table = 'procurement_purchase_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime', 'current_version' => 'integer', 'total_minor' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'documentable_id')->where('documentable_type', static::class);
    }
}
