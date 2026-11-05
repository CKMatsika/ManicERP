<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Revenue\Models\Meter;
use App\Modules\Revenue\Models\MeterReading;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Reading capture with continuity across meter replacement, rollover handling
 * and abnormal-consumption flags. Photos and GPS are optional evidence.
 */
class MeterReadingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function record(
        Meter $meter,
        string $reading,
        string $readAt,
        string $source = 'actual',
        ?User $user = null,
        ?string $photoDocumentId = null,
        ?string $gpsLat = null,
        ?string $gpsLng = null,
        ?string $notes = null,
    ): MeterReading {
        if (! in_array($source, ['actual', 'estimated', 'rollover'], true)) {
            throw new \DomainException('Unknown reading source.');
        }

        $readingScaled = Decimal::toScaled($reading, 3);

        if ($readingScaled < 0) {
            throw new \DomainException('Meter readings cannot be negative.');
        }

        return DB::connection('tenant')->transaction(function () use ($meter, $readingScaled, $readAt, $source, $user, $photoDocumentId, $gpsLat, $gpsLng, $notes) {
            $previous = MeterReading::where('meter_id', $meter->id)
                ->orderByDesc('read_at')
                ->first();

            $previousScaled = $previous
                ? Decimal::toScaled((string) $previous->reading, 3)
                : Decimal::toScaled((string) $meter->initial_reading, 3);

            $consumptionScaled = $readingScaled - $previousScaled;

            if ($consumptionScaled < 0) {
                $rollover = (10 ** (int) $meter->digits) * 1000 - $previousScaled + $readingScaled;

                if ($rollover > 0 && $rollover < (10 ** (int) $meter->digits) * 1000) {
                    $consumptionScaled = $rollover;
                    $source = 'rollover';
                } else {
                    throw new \DomainException('Meter reading is lower than the previous reading and cannot be rolled over.');
                }
            }

            $recent = MeterReading::where('meter_id', $meter->id)
                ->whereNotNull('consumption')
                ->orderByDesc('read_at')
                ->limit(3)
                ->pluck('consumption');

            $average = $recent->isEmpty()
                ? 0
                : (int) ($recent->sum(fn ($value) => Decimal::toScaled((string) $value, 3)) / $recent->count());

            $abnormal = $average > 0 && $consumptionScaled > max($average * 3, 50_000);
            $reason = $abnormal ? 'Consumption is more than three times the recent average.' : null;

            $readingModel = MeterReading::create([
                'meter_id' => $meter->id,
                'service_connection_id' => $meter->service_connection_id,
                'read_at' => $readAt,
                'reading' => Decimal::fromScaled($readingScaled, 3),
                'previous_reading' => Decimal::fromScaled($previousScaled, 3),
                'consumption' => Decimal::fromScaled($consumptionScaled, 3),
                'source' => $source,
                'status' => 'captured',
                'photo_document_id' => $photoDocumentId,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'captured_by' => $user?->getKey(),
                'abnormal_flag' => $abnormal,
                'abnormal_reason' => $reason,
                'notes' => $notes,
            ]);

            $this->audit->record(
                action: 'revenue.reading.captured',
                subject: $readingModel,
                after: [
                    'meter' => $meter->serial_number,
                    'reading' => $readingModel->reading,
                    'consumption' => $readingModel->consumption,
                    'source' => $source,
                    'abnormal' => $abnormal,
                ],
                module: 'revenue',
            );

            return $readingModel;
        });
    }

    public function confirm(MeterReading $reading, User $user): MeterReading
    {
        if ($reading->status !== 'captured') {
            throw new \DomainException('Only captured readings can be confirmed.');
        }

        $reading->status = 'confirmed';
        $reading->save();

        $this->audit->record(
            action: 'revenue.reading.confirmed',
            subject: $reading,
            before: ['status' => 'captured'],
            after: ['status' => 'confirmed', 'consumption' => $reading->consumption],
            module: 'revenue',
        );

        return $reading;
    }
}
