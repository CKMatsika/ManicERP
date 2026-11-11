<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceTypeVersion extends TenantModel
{
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('approval_status') === 'approved') {
                throw new \DomainException('Approved licence type versions are immutable. Create a successor version.');
            }
        });
        static::deleting(function (self $version): void {
            if ($version->approval_status === 'approved') {
                throw new \DomainException('Approved licence type versions cannot be deleted.');
            }
        });
    }

    protected $fillable = ['licence_type_id', 'version', 'configuration', 'approval_status', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'approved_at' => 'datetime'];
    }

    public function licenceType(): BelongsTo
    {
        return $this->belongsTo(LicenceType::class);
    }
}
