<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Transaction-safe, gap-free-per-period reference numbers (receipts, bills,
 * journal entries). Locking the sequence row prevents duplicate numbers under
 * concurrency.
 */
class NumberSequenceService
{
    public function next(string $key, string $prefix = '', int $padding = 6, ?string $periodKey = null): string
    {
        return DB::connection('tenant')->transaction(function () use ($key, $prefix, $padding, $periodKey) {
            $sequence = NumberSequence::query()->lockForUpdate()->find($key);

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'key' => $key,
                    'prefix' => $prefix,
                    'next_value' => 1,
                    'padding' => $padding,
                    'period_key' => $periodKey,
                ]);
                $sequence->refresh();
            }

            if ($periodKey !== null && $sequence->period_key !== $periodKey) {
                $sequence->next_value = 1;
                $sequence->period_key = $periodKey;
            }

            $value = (int) $sequence->next_value;
            $sequence->next_value = $value + 1;
            $sequence->prefix = $prefix !== '' ? $prefix : $sequence->prefix;
            $sequence->padding = $padding;
            $sequence->updated_at = now();
            $sequence->save();

            $number = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

            return $sequence->prefix !== '' ? "{$sequence->prefix}-{$number}" : $number;
        });
    }
}
