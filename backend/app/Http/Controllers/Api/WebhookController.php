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
