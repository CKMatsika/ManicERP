<?php

namespace App\Modules\Revenue\Services;

use App\Modules\Revenue\Models\AccountTariff;
use App\Modules\Revenue\Models\TariffBand;
use App\Modules\Revenue\Models\TariffVersion;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * Versioned, effective-dated tariff calculation with an explicit explanation
 * for every figure. Formulae are configuration-driven, never arbitrary code.
 */
class TariffCalculator
{
    public function effectiveTariff(string $accountId, string $serviceType, string $onDate): ?TariffVersion
    {
        $assignment = AccountTariff::query()
            ->where('customer_account_id', $accountId)
            ->where('service_type', $serviceType)
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($query) use ($onDate) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($assignment) {
            $assigned = TariffVersion::find($assignment->tariff_version_id);
            if ($assigned && $assigned->isApproved()) {
                return $assigned;
            }
        }

        return TariffVersion::query()
            ->where('service_type', $serviceType)
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($query) use ($onDate) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version_no')
            ->first();
    }

    /**
     * @return array{amount_minor: int, breakdown: array<int, array<string, mixed>>, basis: string, tariff_code: string, tariff_version_id: string}
     */
    public function calculate(
        TariffVersion $tariff,
        ?string $consumptionUnits = null,
        ?int $propertyValueMinor = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): array {
        $bands = $tariff->bands()->get();

        if ($bands->isEmpty()) {
            throw new \DomainException("Tariff [{$tariff->code}] has no approved bands.");
        }

        return match ($tariff->charge_basis) {
            'metered' => $this->metered($tariff, $bands, $consumptionUnits ?? '0', $periodStart, $periodEnd),
            'valuation' => $this->valuation($tariff, $bands, $propertyValueMinor ?? 0, $periodStart, $periodEnd),
            'fixed' => $this->fixed($tariff, $bands, $periodStart, $periodEnd),
            default => throw new \DomainException("Unsupported tariff basis [{$tariff->charge_basis}]."),
        };
    }

    private function metered(TariffVersion $tariff, $bands, string $consumption, ?string $start, ?string $end): array
    {
        $consumptionMilli = Decimal::toScaled($consumption, 3);

        if ($consumptionMilli < 0) {
            throw new \DomainException('Consumption cannot be negative.');
        }

        $amount = 0;
        $breakdown = [];

        foreach ($bands as $band) {
            /** @var TariffBand $band */
            $fromMilli = Decimal::toScaled((string) $band->from_units, 3);
            $toMilli = $band->to_units === null ? null : Decimal::toScaled((string) $band->to_units, 3);

            $upper = $toMilli === null ? $consumptionMilli : min($consumptionMilli, $toMilli);
            $unitsMilli = max(0, $upper - $fromMilli);

            if ($unitsMilli === 0) {
                continue;
            }

            $bandAmount = Decimal::roundDiv($unitsMilli * (int) $band->rate_minor, 1000);
            $amount += $bandAmount;

            $breakdown[] = [
                'band' => $band->band_no,
                'units' => number_format($unitsMilli / 1000, 3, '.', ''),
                'rate_minor' => (int) $band->rate_minor,
                'amount_minor' => $bandAmount,
                'description' => $band->description ?: "Band {$band->band_no}",
            ];
        }

        $fixed = $this->fixedCharge($bands, $start, $end);
        if ($fixed > 0) {
            $amount += $fixed;
            $breakdown[] = ['band' => null, 'description' => 'Fixed charge', 'amount_minor' => $fixed, 'units' => null, 'rate_minor' => null];
        }

        $minimum = (int) ($bands->first()->minimum_charge_minor ?? 0);
        if ($minimum > 0 && $amount < $minimum) {
            $breakdown[] = ['band' => null, 'description' => 'Minimum charge adjustment', 'amount_minor' => $minimum - $amount, 'units' => null, 'rate_minor' => null];
            $amount = $minimum;
        }

        return [
            'amount_minor' => $amount,
            'breakdown' => $breakdown,
            'basis' => 'metered',
            'tariff_code' => $tariff->code,
            'tariff_version_id' => $tariff->id,
        ];
    }

    private function valuation(TariffVersion $tariff, $bands, int $propertyValueMinor, ?string $start, ?string $end): array
    {
        if ($propertyValueMinor <= 0) {
            throw new \DomainException('An approved property valuation is required for a valuation-based tariff.');
        }

        $band = $bands->first();
        // rate_minor is expressed in cents per $100 of valuation.
        $amount = Decimal::roundDiv($propertyValueMinor * (int) $band->rate_minor, 10000);

        $breakdown = [[
            'band' => $band->band_no,
            'description' => 'Rates on property value',
            'valuation_minor' => $propertyValueMinor,
            'rate_minor_per_100' => (int) $band->rate_minor,
            'amount_minor' => $amount,
        ]];

        $fixed = $this->fixedCharge($bands, $start, $end);
        if ($fixed > 0) {
            $amount += $fixed;
            $breakdown[] = ['band' => null, 'description' => 'Fixed charge', 'amount_minor' => $fixed];
        }

        return [
            'amount_minor' => $amount,
            'breakdown' => $breakdown,
            'basis' => 'valuation',
            'tariff_code' => $tariff->code,
            'tariff_version_id' => $tariff->id,
        ];
    }

    private function fixed(TariffVersion $tariff, $bands, ?string $start, ?string $end): array
    {
        $amount = $this->fixedCharge($bands, $start, $end);
        $minimum = (int) ($bands->first()->minimum_charge_minor ?? 0);
        $amount = max($amount, $minimum);

        return [
            'amount_minor' => $amount,
            'breakdown' => [[
                'band' => null,
                'description' => 'Fixed service charge',
                'base_amount_minor' => (int) $bands->first()->fixed_charge_minor,
                'prorated_days' => $this->billedDays($start, $end),
                'amount_minor' => $amount,
            ]],
            'basis' => 'fixed',
            'tariff_code' => $tariff->code,
            'tariff_version_id' => $tariff->id,
        ];
    }

    private function fixedCharge($bands, ?string $start, ?string $end): int
    {
        $base = (int) ($bands->first()->fixed_charge_minor ?? 0);

        if ($base === 0) {
            return 0;
        }

        $billedDays = $this->billedDays($start, $end);

        if ($billedDays >= 28) {
            return $base;
        }

        $monthDays = $start ? CarbonImmutable::parse($start)->daysInMonth : 30;

        return Decimal::roundDiv($base * $billedDays, $monthDays);
    }

    private function billedDays(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 30;
        }

        return CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
    }
}
