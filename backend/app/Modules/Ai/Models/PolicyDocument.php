<?php

namespace App\Modules\Ai\Models;

use App\Support\TenantModel;

class PolicyDocument extends TenantModel
{
    protected $fillable = [
        'title', 'version', 'status', 'effective_from', 'effective_to',
        'source_url', 'source_authority', 'content', 'checksum',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
