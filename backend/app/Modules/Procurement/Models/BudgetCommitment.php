<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCommitment extends TenantModel
{
    protected $table = 'procurement_commitments';

    protected $fillable = ['budget_line_id', 'source_type', 'source_id', 'status', 'reserved_minor', 'converted_minor', 'idempotency_key', 'reason', 'created_by', 'released_at'];

    public function line(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}
