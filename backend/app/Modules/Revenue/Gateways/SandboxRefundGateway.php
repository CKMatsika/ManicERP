<?php

namespace App\Modules\Revenue\Gateways;

use App\Modules\Revenue\Contracts\RefundGateway;
use App\Modules\Revenue\Models\Refund;

/**
 * Clearly labelled sandbox refund adapter. No real money leaves the council.
 * A production adapter must be implemented only after a verified provider
 * integration exists.
 */
class SandboxRefundGateway implements RefundGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function isSandbox(): bool
    {
        return true;
    }

    public function settle(Refund $refund): array
    {
        return [
            'status' => 'settled',
            'reference' => 'SBX-REFUND-'.strtoupper(substr(hash('sha256', $refund->id.$refund->refund_number), 0, 16)),
            'message' => 'Sandbox refund settled; no real funds moved.',
        ];
    }
}
