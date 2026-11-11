<?php

namespace Tests\Feature;

use App\Modules\Land\Services\LandService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class LandTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_land_parcel_registration_and_coordinate_provenance(): void
    {
        $council = $this->makeCouncil();
        $user = $this->makeUser('land@test.local');
        $this->useCouncil($council);

        $service = app(LandService::class);
        $parcel = $service->registerParcel('P-001', 'Synthetic parcel', 'W01', -17.8292, 31.0522, 'survey', 'approximate');
        $this->assertSame('P-001', $parcel->parcel_number);
        $this->assertEquals(-17.8292, (float) $parcel->latitude);

        $updated = $service->updateCoordinates($parcel->id, -17.8300, 31.0530, 'gps', 'high');
        $this->assertEquals(-17.83, (float) $updated->latitude);
        $this->assertSame('gps', $updated->coordinate_source);
    }
}
