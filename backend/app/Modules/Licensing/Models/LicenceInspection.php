<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceInspection extends TenantModel
{
    protected $fillable = ['licence_application_id', 'assigned_to', 'scheduled_at', 'status', 'findings', 'result', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'findings' => 'array', 'completed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenceApplication::class, 'licence_application_id');
    }
}
