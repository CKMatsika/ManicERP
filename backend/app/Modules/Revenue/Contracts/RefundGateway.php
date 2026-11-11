<?php

namespace App\Modules\Revenue\Contracts;

use App\Modules\Revenue\Models\Refund;

interface RefundGateway
{
    public function name(): string;

    public function isSandbox(): bool;

    /**
     * @return array{status: string, reference: string, message?: string}
     */
    public function settle(Refund $refund): array;
}
