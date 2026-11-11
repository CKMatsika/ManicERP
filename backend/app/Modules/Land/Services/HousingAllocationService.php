<?php

namespace App\Modules\Land\Services;

use App\Models\User;
use App\Modules\Land\Models\HousingAllocation;
use App\Modules\Land\Models\HousingWaitingListEntry;
use App\Modules\Land\Models\LandParcel;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HousingAllocationService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function getAvailableParcels(?string $category = null): Collection
    {
        $query = LandParcel::query()->where('allocation_status', 'available');
        if ($category) {
            $query->where('stand_category', $category);
        }

        return $query->orderBy('suburb')->orderBy('parcel_number')->get();
    }

    public function listAllAllocations(): Collection
    {
        return HousingAllocation::with(['waitingListEntry', 'landParcel'])
            ->latest('allocated_at')
            ->get();
    }

    public function getOfferForCitizen(User $citizen): ?HousingAllocation
    {
        $entry = HousingWaitingListEntry::where(function ($q) use ($citizen) {
            $q->where('user_id', $citizen->id)->orWhere('email', $citizen->email);
        })->first();

        if (! $entry) {
            return null;
        }

        return HousingAllocation::with(['landParcel', 'waitingListEntry'])
            ->where('waiting_list_entry_id', $entry->id)
            ->whereIn('status', ['offer_issued', 'accepted', 'deposit_paid'])
            ->latest('allocated_at')
            ->first();
    }

    public function allocate(
        string $waitingListEntryId,
        string $landParcelId,
        array $options,
        User $staff
    ): HousingAllocation {
        return DB::connection('tenant')->transaction(function () use ($waitingListEntryId, $landParcelId, $options, $staff) {
            $entry = HousingWaitingListEntry::findOrFail($waitingListEntryId);
            $parcel = LandParcel::findOrFail($landParcelId);

            if ($parcel->allocation_status !== 'available') {
                throw new \DomainException("Stand [{$parcel->parcel_number}] is already {$parcel->allocation_status}.");
            }

            $now = now();
            $expiresAt = $now->copy()->addDays(30); // 30-day offer window

            $covenants = $options['covenants'] ?? [
                'Complete foundation slab within 12 months of agreement.',
                'Erect building to window level within 24 months.',
                'Comply with Model Building By-laws and approved council architectural plans.',
                'Subdivision or cession prohibited prior to Title Deed issuance.',
            ];

            $ref = 'ALC-' . date('Y') . '-' . strtoupper(Str::random(6));

            $allocation = HousingAllocation::create([
                'allocation_reference' => $ref,
                'waiting_list_entry_id' => $entry->id,
                'land_parcel_id' => $parcel->id,
                'customer_account_id' => $entry->customer_account_id,
                'stand_number' => $parcel->parcel_number,
                'suburb' => $parcel->suburb ?? 'Municipal Area',
                'category' => $parcel->stand_category,
                'area_sqm' => $parcel->area_sqm,
                'purchase_price_minor' => $parcel->purchase_price_minor,
                'deposit_required_minor' => $parcel->deposit_required_minor,
                'deposit_paid_minor' => 0,
                'currency_code' => $parcel->currency_code,
                'development_covenants' => $covenants,
                'allocated_by_user_id' => $staff->id,
                'allocated_at' => $now,
                'offer_expires_at' => $expiresAt,
                'status' => 'offer_issued',
                'notes' => $options['notes'] ?? "Allocated under Council Housing Resolution.",
            ]);

            // Update parcel status to reserved
            $parcel->update([
                'allocation_status' => 'reserved',
            ]);

            // Update waiting list entry
            $entry->update([
                'status' => 'allocated',
                'allocated_stand_number' => $parcel->parcel_number,
            ]);

            $this->audit->record(
                action: 'housing.stand_allocated',
                after: [
                    'reference' => $ref,
                    'applicant' => $entry->full_name,
                    'stand' => $parcel->parcel_number,
                    'suburb' => $parcel->suburb,
                ],
                module: 'land'
            );

            return $allocation;
        });
    }

    public function acceptOffer(HousingAllocation $allocation, User $citizen): HousingAllocation
    {
        if ($allocation->status !== 'offer_issued') {
            throw new \DomainException("This offer cannot be accepted (current status: {$allocation->status}).");
        }

        if ($allocation->offer_expires_at->isPast()) {
            throw new \DomainException("This stand allocation offer has expired.");
        }

        $allocation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->audit->record(
            action: 'housing.offer_accepted',
            after: ['allocation_id' => $allocation->id, 'reference' => $allocation->allocation_reference],
            module: 'land'
        );

        return $allocation->fresh();
    }

    public function rejectOffer(HousingAllocation $allocation, string $reason, User $citizen): HousingAllocation
    {
        return DB::connection('tenant')->transaction(function () use ($allocation, $reason) {
            $allocation->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            // Release parcel back to available
            if ($allocation->landParcel) {
                $allocation->landParcel->update(['allocation_status' => 'available']);
            }

            // Return waiting list entry to active queue
            if ($allocation->waitingListEntry) {
                $allocation->waitingListEntry->update([
                    'status' => 'active',
                    'allocated_stand_number' => null,
                ]);
            }

            $this->audit->record(
                action: 'housing.offer_rejected',
                after: ['allocation_id' => $allocation->id, 'reason' => $reason],
                module: 'land'
            );

            return $allocation->fresh();
        });
    }

    public function recordDeposit(HousingAllocation $allocation, int $amountMinor, string $reference): HousingAllocation
    {
        $newDeposit = $allocation->deposit_paid_minor + $amountMinor;
        $status = $newDeposit >= $allocation->deposit_required_minor ? 'deposit_paid' : $allocation->status;

        $allocation->update([
            'deposit_paid_minor' => $newDeposit,
            'status' => $status,
        ]);

        if ($status === 'deposit_paid' && $allocation->landParcel) {
            $allocation->landParcel->update(['allocation_status' => 'allocated']);
        }

        return $allocation->fresh();
    }

    public function transformAllocation(HousingAllocation $alloc): array
    {
        return [
            'id' => $alloc->id,
            'allocation_reference' => $alloc->allocation_reference,
            'stand_number' => $alloc->stand_number,
            'suburb' => $alloc->suburb,
            'category' => $alloc->category,
            'category_label' => match ($alloc->category) {
                'high_density' => 'High Density Residential',
                'medium_density' => 'Medium Density Residential',
                'low_density' => 'Low Density Residential',
                'commercial' => 'Commercial / Retail Stand',
                default => ucfirst(str_replace('_', ' ', $alloc->category)),
            },
            'area_sqm' => $alloc->area_sqm,
            'purchase_price_minor' => $alloc->purchase_price_minor,
            'purchase_price_usd' => number_format($alloc->purchase_price_minor / 100, 2),
            'deposit_required_minor' => $alloc->deposit_required_minor,
            'deposit_required_usd' => number_format($alloc->deposit_required_minor / 100, 2),
            'deposit_paid_minor' => $alloc->deposit_paid_minor,
            'deposit_paid_usd' => number_format($alloc->deposit_paid_minor / 100, 2),
            'currency_code' => $alloc->currency_code,
            'covenants' => $alloc->development_covenants ?? [],
            'allocated_at' => $alloc->allocated_at?->toDateString(),
            'offer_expires_at' => $alloc->offer_expires_at?->toDateString(),
            'days_remaining' => $alloc->offer_expires_at ? max(now()->diffInDays($alloc->offer_expires_at, false), 0) : 0,
            'status' => $alloc->status,
            'accepted_at' => $alloc->accepted_at?->toIso8601String(),
        ];
    }
}

