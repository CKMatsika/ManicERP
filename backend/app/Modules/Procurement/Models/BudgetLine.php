<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetLine extends TenantModel
{
    protected $table = 'procurement_budget_lines';

    protected $fillable = ['budget_id', 'account_code', 'dimensions', 'approved_minor', 'transferred_in_minor', 'transferred_out_minor', 'committed_minor', 'actual_minor'];

    protected function casts(): array
    {
        return ['dimensions' => 'array'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class, 'budget_line_id');
    }

    public function availableMinor(): int
    {
        return (int) $this->approved_minor + (int) $this->transferred_in_minor - (int) $this->transferred_out_minor - (int) $this->committed_minor - (int) $this->actual_minor;
    }
}
