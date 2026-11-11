<?php

namespace App\Modules\Revenue\Gateways;

use App\Modules\Revenue\Contracts\PaymentGateway;
use App\Modules\Revenue\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paynow Gateway Adapter for Zimbabwean Council Collections.
 * Supports EcoCash, OneMoney, InnBucks, and Zimswitch/Visa/Mastercard.
 * If credentials are not yet configured in production, operates in structured
 * sandbox mode to allow mobile development and testing without disruptions.
 */
class PaynowGateway implements PaymentGateway
{
    private string $integrationId;
    private string $integrationKey;
    private string $resultUrl;
    private string $returnUrl;
    private bool $isTest;

    public function __construct()
    {
        $this->integrationId = (string) config('services.paynow.integration_id', env('PAYNOW_INTEGRATION_ID', ''));
        $this->integrationKey = (string) config('services.paynow.integration_key', env('PAYNOW_INTEGRATION_KEY', ''));
        $this->resultUrl = (string) config('services.paynow.result_url', env('PAYNOW_RESULT_URL', url('/api/v1/webhooks/payments/paynow')));
        $this->returnUrl = (string) config('services.paynow.return_url', env('PAYNOW_RETURN_URL', url('/portal/payment/complete')));
        $this->isTest = empty($this->integrationId) || empty($this->integrationKey) || env('PAYNOW_SANDBOX', true);
    }

    public function name(): string
    {
        return 'paynow';
    }

    public function isSandbox(): bool
    {
        return $this->isTest;
    }

    /**
     * Create checkout or initiate mobile express checkout (EcoCash / OneMoney).
     */
    public function createCheckout(PaymentIntent $intent): array
    {
        $amount = number_format($intent->amount_minor / 100, 2, '.', '');
        $metadata = $intent->metadata ?? [];
        $paymentMethod = $metadata['payment_method'] ?? 'ecocash';
        $phone = $metadata['phone'] ?? '';

        if ($this->isSandbox()) {
            return [
                'gateway' => $this->name(),
                'sandbox' => true,
                'reference' => $intent->reference,
                'paynow_reference' => 'PN-TEST-' . strtoupper(substr(md5($intent->reference), 0, 8)),
                'amount' => $amount,
                'currency' => $intent->currency_code,
                'method' => $paymentMethod,
                'phone' => $phone,
                'instructions' => match ($paymentMethod) {
                    'ecocash' => "EcoCash prompt sent to {$phone}. Enter your EcoCash PIN on your phone handset to authorize \${$amount}.",
                    'onemoney' => "OneMoney prompt sent to {$phone}. Enter your OneMoney PIN on your phone handset to authorize \${$amount}.",
                    default => "Click the Paynow checkout link to complete payment with Visa, Mastercard, or Zimswitch.",
                },
                'checkout_url' => url("/api/v1/sandbox/paynow/checkout/{$intent->reference}"),
                'poll_url' => url("/api/v1/payments/intent/{$intent->id}/poll"),
            ];
        }

        // Live Paynow API execution
        $endpoint = !empty($phone)
            ? 'https://www.paynow.co.zw/interface/remotetransaction'
            : 'https://www.paynow.co.zw/interface/initiatetransaction';

        $values = [
            'id' => $this->integrationId,
            'reference' => $intent->reference,
            'amount' => $amount,
            'additionalinfo' => "Council Payment: {$intent->reference}",
            'returnurl' => $this->returnUrl,
            'resulturl' => $this->resultUrl,
            'authemail' => $intent->user?->email ?? 'citizen@council.gov.zw',
            'status' => 'Message',
        ];

        if (!empty($phone)) {
            $values['phone'] = $phone;
            $values['method'] = $paymentMethod;
        }

        $values['hash'] = $this->generateHash($values, $this->integrationKey);

        try {
            $response = Http::asForm()->timeout(15)->post($endpoint, $values);
            parse_str($response->body(), $result);

            if (($result['status'] ?? '') === 'Error') {
                throw new \DomainException('Paynow error: ' . ($result['error'] ?? 'Unknown error'));
            }

            return [
                'gateway' => $this->name(),
                'sandbox' => false,
                'reference' => $intent->reference,
                'paynow_reference' => $result['paynowreference'] ?? null,
                'instructions' => $result['instructions'] ?? 'Follow the prompts on your mobile device.',
                'checkout_url' => $result['browserurl'] ?? null,
                'poll_url' => $result['pollurl'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Paynow initiation failed: ' . $e->getMessage());
            throw new \DomainException('Failed to communicate with Paynow gateway: ' . $e->getMessage());
        }
    }

    /**
     * Verify inbound Paynow Instant Payment Notification (IPN).
     */
    public function verifyWebhook(Request $request): array
    {
        $data = $request->all();

        // In sandbox simulation
        if ($this->isSandbox()) {
            return [
                'reference' => $data['reference'] ?? '',
                'provider_reference' => $data['paynow_reference'] ?? ('PN-' . strtoupper(uniqid())),
                'status' => ($data['status'] ?? 'succeeded') === 'succeeded' ? 'succeeded' : 'failed',
                'amount_minor' => isset($data['amount']) ? (int) (floatval($data['amount']) * 100) : 0,
                'payload' => $data,
            ];
        }

        $hash = $data['hash'] ?? '';
        unset($data['hash']);

        $expectedHash = $this->generateHash($data, $this->integrationKey);
        if (!hash_equals(strtolower($expectedHash), strtolower($hash))) {
            throw new \DomainException('Invalid Paynow IPN signature.');
        }

        $status = match (strtolower($data['status'] ?? '')) {
            'paid', 'awaiting delivery' => 'succeeded',
            'cancelled' => 'cancelled',
            default => 'failed',
        };

        return [
            'reference' => $data['reference'] ?? '',
            'provider_reference' => $data['paynowreference'] ?? '',
            'status' => $status,
            'amount_minor' => (int) (floatval($data['amount'] ?? 0) * 100),
            'payload' => $data,
        ];
    }

    private function generateHash(array $values, string $integrationKey): string
    {
        $string = '';
        foreach ($values as $key => $value) {
            if ($key !== 'hash') {
                $string .= $value;
            }
        }
        $string .= $integrationKey;

        return strtoupper(hash('sha512', $string));
    }
}

