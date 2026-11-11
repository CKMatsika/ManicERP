<?php

namespace App\Modules\Land\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandService
{
    public function registerParcel(string $parcelNumber, ?string $description = null, ?string $ward = null, ?float $latitude = null, ?float $longitude = null, ?string $coordinateSource = null, ?string $coordinateAccuracy = null): object
    {
        if (trim($parcelNumber) === '') {
            throw new \DomainException('Parcel number is required.');
        }
        if (($latitude !== null || $longitude !== null) && ($latitude === null || $longitude === null)) {
            throw new \DomainException('Latitude and longitude must be supplied together.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('land_parcels')->insert([
            'id' => $id, 'parcel_number' => $parcelNumber, 'description' => $description, 'ward' => $ward,
            'latitude' => $latitude, 'longitude' => $longitude, 'coordinate_source' => $coordinateSource,
            'coordinate_accuracy' => $coordinateAccuracy, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('land_parcels')->where('id', $id)->firstOrFail();
    }

    public function updateCoordinates(string $parcelId, float $latitude, float $longitude, string $coordinateSource, ?string $coordinateAccuracy = null): object
    {
        $parcel = DB::connection('tenant')->table('land_parcels')->where('id', $parcelId)->firstOrFail();
        DB::connection('tenant')->table('land_parcels')->where('id', $parcelId)->update([
            'latitude' => $latitude, 'longitude' => $longitude, 'coordinate_source' => $coordinateSource,
            'coordinate_accuracy' => $coordinateAccuracy, 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('land_parcels')->where('id', $parcelId)->firstOrFail();
    }
}
