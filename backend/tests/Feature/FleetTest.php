<?php

namespace Tests\Feature;

use App\Modules\Fleet\Services\FleetService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_vehicle_registration_fuel_log_and_maintenance_posting(): void
    {
        $council = $this->makeCouncil();
        $user = $this->makeUser('fleet@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $user);

        $service = app(FleetService::class);
        $vehicle = $service->registerVehicle('ABC-1234', 'Toyota', 'Hilux');
        $this->assertSame('active', $vehicle->status);

        $fuel = $service->logFuel($vehicle->id, '2026-01-10', 40.5, 5000, 12000);
        $this->assertEquals(40.5, (float) $fuel->litres);

        $maintenance = $service->logMaintenance($vehicle->id, '2026-01-12', 'Oil change', 3000, '5020', $user);
        $this->assertSame(3000, (int) $maintenance->cost_minor);
        $this->assertNotNull($maintenance->journal_entry_id);
    }
}
