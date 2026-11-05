<?php

namespace App\Modules\Revenue\Gateways;

use App\Modules\Revenue\Contracts\PaymentGateway;
use App\Modules\Revenue\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Clearly labelled sandbox gateway. It verifies signed, timestamped,
 * replay-protected webhooks but performs no real money movement. No Zimbabwean
 * gateway is asserted to be integrated without verified API access.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function isSandbox(): bool
    {
        return true;
    }

    public function createCheckout(PaymentIntent $intent): array
    {
        return [
            'gateway' => $this->name(),
            'sandbox' => true,
            'reference' => $intent->reference,
            'amount_minor' => $intent->amount_minor,
            'currency' => $intent->currency_code,
            'message' => 'Sandbox only: no card or mobile-money details are collected. Confirm through the signed sandbox webhook.',
            'webhook_endpoint' => '/api/v1/webhooks/payments/sandbox',
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $secret = (string) config('manicerp.payments.sandbox.webhook_secret');

        if ($secret === '') {
            throw new \DomainException('Sandbox webhook secret is not configured.');
        }

        $timestamp = (string) $request->header('X-Sandbox-Timestamp', '');
        $signature = (string) $request->header('X-Sandbox-Signature', '');
        $body = $request->getContent();

        if ($timestamp === '' || $signature === '') {
            throw new \DomainException('Missing sandbox webhook signature headers.');
        }

        if (abs(time() - (int) $timestamp) > (int) config('manicerp.payments.webhook_tolerance_seconds', 300)) {
            throw new \DomainException('Webhook timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new \DomainException('Invalid webhook signature.');
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw new \DomainException('Webhook body is not valid JSON.');
        }

        $nonce = (string) ($payload['nonce'] ?? '');

        if ($nonce === '') {
            throw new \DomainException('Webhook nonce is required for replay protection.');
        }

        if (Cache::has('payment.webhook.nonce.'.$nonce)) {
            throw new \DomainException('Webhook replay detected.');
        }

        Cache::put('payment.webhook.nonce.'.$nonce, true, 900);

        return $payload;
    }
}
