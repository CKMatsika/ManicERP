<?php

namespace App\Modules\Land\Services;

use App\Models\User;
use App\Modules\Land\Models\HousingWaitingListEntry;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HousingWaitingListService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function listForUser(User $user): Collection
    {
        return HousingWaitingListEntry::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->latest()
            ->get()
            ->map(function (HousingWaitingListEntry $entry) {
                return $this->transformEntry($entry);
            });
    }

    public function getEntry(string $id, ?User $user = null): HousingWaitingListEntry
    {
        $query = HousingWaitingListEntry::where('id', $id);
        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', $user->email);
            });
        }

        return $query->firstOrFail();
    }

    public function register(User $user, array $data): HousingWaitingListEntry
    {
        $category = $data['category'] ?? 'high_density';
        $nationalId = strtoupper(trim($data['national_id']));
        $dependents = (int) ($data['number_of_dependents'] ?? 0);
        $incomeMinor = (int) ($data['monthly_income_minor'] ?? 0);

        // Calculate initial priority score based on Zimbabwean council weighting:
        // Baseline 10 pts + 2 pts per dependent (max 10) + employment stability
        $priorityPoints = 10 + min($dependents * 2, 10);
        if (($data['employment_status'] ?? '') === 'employed') {
            $priorityPoints += 5;
        }

        $now = now();
        $refNumber = 'HWL-' . date('Y') . '-' . strtoupper(Str::random(6));

        $entry = HousingWaitingListEntry::create([
            'waiting_list_number' => $refNumber,
            'user_id' => $user->id,
            'customer_account_id' => $data['customer_account_id'] ?? null,
            'national_id' => $nationalId,
            'full_name' => trim($data['full_name'] ?? $user->name),
            'phone_number' => trim($data['phone_number']),
            'email' => trim($data['email'] ?? $user->email),
            'category' => $category,
            'preferred_suburbs' => $data['preferred_suburbs'] ?? [],
            'marital_status' => $data['marital_status'] ?? 'married',
            'number_of_dependents' => $dependents,
            'employment_status' => $data['employment_status'] ?? 'employed',
            'monthly_income_minor' => $incomeMinor,
            'currency_code' => $data['currency_code'] ?? 'USD',
            'priority_points' => $priorityPoints,
            'status' => 'active',
            'application_date' => $now->toDateString(),
            'expiry_date' => $now->copy()->addYear()->toDateString(),
            'last_renewed_at' => $now,
            'notes' => $data['notes'] ?? 'Citizen mobile portal application.',
        ]);

        return $entry;
    }

    public function renew(HousingWaitingListEntry $entry): HousingWaitingListEntry
    {
        $now = now();
        // Extending validity by another 12 months and increasing priority points for duration on list (+5 pts per year)
        $newExpiry = $entry->expiry_date && $entry->expiry_date->isFuture()
            ? $entry->expiry_date->copy()->addYear()
            : $now->copy()->addYear();

        $entry->update([
            'status' => 'active',
            'expiry_date' => $newExpiry->toDateString(),
            'last_renewed_at' => $now,
            'priority_points' => $entry->priority_points + 5,
        ]);

        return $entry->fresh();
    }

    public function calculateQueuePosition(HousingWaitingListEntry $entry): int
    {
        if ($entry->status !== 'active') {
            return 0;
        }

        // Rank by priority_points desc, then application_date asc
        return HousingWaitingListEntry::where('category', $entry->category)
            ->where('status', 'active')
            ->where(function ($query) use ($entry) {
                $query->where('priority_points', '>', $entry->priority_points)
                    ->orWhere(function ($q) use ($entry) {
                        $q->where('priority_points', $entry->priority_points)
                            ->where('application_date', '<', $entry->application_date);
                    });
            })
            ->count() + 1;
    }

    public function transformEntry(HousingWaitingListEntry $entry): array
    {
        $queuePos = $this->calculateQueuePosition($entry);
        $totalInCategory = HousingWaitingListEntry::where('category', $entry->category)
            ->where('status', 'active')
            ->count();

        return [
            'id' => $entry->id,
            'waiting_list_number' => $entry->waiting_list_number,
            'national_id' => $entry->national_id,
            'full_name' => $entry->full_name,
            'phone_number' => $entry->phone_number,
            'email' => $entry->email,
            'category' => $entry->category,
            'category_label' => match ($entry->category) {
                'high_density' => 'High Density Residential',
                'medium_density' => 'Medium Density Residential',
                'low_density' => 'Low Density Residential',
                'commercial' => 'Commercial / Business Stand',
                'industrial' => 'Light Industrial Stand',
                default => ucfirst(str_replace('_', ' ', $entry->category)),
            },
            'preferred_suburbs' => $entry->preferred_suburbs ?? [],
            'marital_status' => $entry->marital_status,
            'number_of_dependents' => $entry->number_of_dependents,
            'employment_status' => $entry->employment_status,
            'monthly_income_minor' => $entry->monthly_income_minor,
            'currency_code' => $entry->currency_code,
            'priority_points' => $entry->priority_points,
            'queue_position' => $queuePos,
            'total_in_queue' => max($totalInCategory, 1),
            'status' => $entry->status,
            'application_date' => $entry->application_date?->toDateString(),
            'expiry_date' => $entry->expiry_date?->toDateString(),
            'days_until_renewal' => $entry->expiry_date ? now()->diffInDays($entry->expiry_date, false) : 0,
            'renewal_fee_minor' => 1500, // $15.00 annual statutory renewal fee
            'allocated_stand_number' => $entry->allocated_stand_number,
            'last_renewed_at' => $entry->last_renewed_at?->toIso8601String(),
        ];
    }
}

