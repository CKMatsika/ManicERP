<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends TenantModel
{
    protected $fillable = [
        'payment_intent_id', 'provider_reference', 'status', 'request_meta',
        'response_meta', 'attempted_at',
    ];

    protected function casts(): array
    {
        return ['request_meta' => 'array', 'response_meta' => 'array', 'attempted_at' => 'datetime'];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
