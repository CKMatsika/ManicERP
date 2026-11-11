<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends TenantModel
{
    protected $table = 'procurement_budgets';

    protected $fillable = ['code', 'name', 'currency_code', 'period_start', 'period_end', 'status', 'version', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }
}
