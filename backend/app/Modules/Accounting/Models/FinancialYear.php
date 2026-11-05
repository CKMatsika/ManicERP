<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialYear extends TenantModel
{
    protected $fillable = ['code', 'start_date', 'end_date', 'status', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FinancialPeriod::class);
    }
}
