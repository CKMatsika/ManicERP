<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceCertificate extends TenantModel
{
    protected $fillable = ['licence_id', 'licence_application_id', 'version', 'certificate_reference', 'verification_id', 'template_code', 'holder_name', 'licence_type_name', 'issued_on', 'expires_on', 'conditions', 'replaces_certificate_id', 'issued_by', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'conditions' => 'array', 'issued_at' => 'datetime'];
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }
}
