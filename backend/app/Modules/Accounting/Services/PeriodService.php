<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\FinancialPeriod;
use Carbon\CarbonImmutable;

class PeriodService
{
    public function forDate(string|CarbonImmutable $date): FinancialPeriod
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        $period = FinancialPeriod::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        if (! $period) {
            throw new \DomainException("No financial period covers {$date->toDateString()}. Configure periods before posting.");
        }

        return $period;
    }

    public function ensureOpen(FinancialPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw new \DomainException("Financial period [{$period->code}] is {$period->status}; posting is not permitted.");
        }
    }

    public function forPosting(string|CarbonImmutable $date): FinancialPeriod
    {
        $period = $this->forDate($date);
        $this->ensureOpen($period);

        return $period;
    }

    public function lock(FinancialPeriod $period, string $userId, string $reason): FinancialPeriod
    {
        if ($period->status === 'locked') {
            return $period;
        }

        $period->status = 'locked';
        $period->locked_at = now();
        $period->locked_by = $userId;
        $period->save();

        return $period;
    }
}
