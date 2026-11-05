<?php

namespace App\Modules\Revenue\Contracts;

use App\Modules\Revenue\Models\PaymentIntent;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    public function isSandbox(): bool;

    /** @return array<string, mixed> */
    public function createCheckout(PaymentIntent $intent): array;

    /** @return array<string, mixed> */
    public function verifyWebhook(Request $request): array;
}
