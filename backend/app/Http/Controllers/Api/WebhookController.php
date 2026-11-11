<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\PaymentIntent;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function sandbox(Request $request, PaymentService $payments)
    {
        try {
            $payload = $payments->gateway('sandbox')->verifyWebhook($request);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->settleIntent($payload, $payments);
    }

    public function paynow(Request $request, PaymentService $payments)
    {
        try {
            $payload = $payments->gateway('paynow')->verifyWebhook($request);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->settleIntent($payload, $payments);
    }

    /**
     * For development and testing: simulate instant EcoCash/OneMoney push completion.
     */
    public function simulatePaynowSuccess(string $reference, PaymentService $payments)
    {
        $intent = PaymentIntent::where('reference', $reference)->firstOrFail();

        $payload = [
            'reference' => $intent->reference,
            'provider_reference' => 'PN-SIM-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'status' => 'succeeded',
            'amount_minor' => $intent->amount_minor,
            'payload' => ['simulated' => true, 'channel' => 'ecocash_push'],
        ];

        return $this->settleIntent($payload, $payments);
    }

    private function settleIntent(array $payload, PaymentService $payments)
    {
        $intent = PaymentIntent::where('reference', $payload['reference'] ?? '')->first();

        if (! $intent) {
            return response()->json(['message' => 'Unknown payment reference.'], 404);
        }

        $status = (string) ($payload['status'] ?? '');

        if (! in_array($status, ['succeeded', 'failed', 'cancelled'], true)) {
            return response()->json(['message' => 'Unknown settlement status.'], 422);
        }

        try {
            $payment = $payments->settle($intent, $status, (string) ($payload['provider_reference'] ?? ''), $payload);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Processed.',
            'status' => $intent->fresh()->status,
            'payment_number' => $payment?->payment_number,
            'receipt_number' => $payment?->receipt?->receipt_number,
        ]);
    }
}
