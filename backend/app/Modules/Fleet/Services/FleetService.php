<?php

namespace App\Modules\Fleet\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FleetService
{
    public function __construct(private readonly PostingService $posting, private readonly AuditLogger $audit) {}

    public function registerVehicle(string $registration, ?string $make = null, ?string $model = null): object
    {
        if (trim($registration) === '') {
            throw new \DomainException('Registration number is required.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('fleet_vehicles')->insert([
            'id' => $id, 'registration_number' => $registration, 'make' => $make, 'model' => $model,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('fleet_vehicles')->where('id', $id)->firstOrFail();
    }

    public function logFuel(string $vehicleId, string $logDate, float $litres, int $costMinor, ?int $odometer = null): object
    {
        if ($litres <= 0 || $costMinor <= 0) {
            throw new \DomainException('Fuel litres and cost must be positive.');
        }
        $vehicle = DB::connection('tenant')->table('fleet_vehicles')->where('id', $vehicleId)->firstOrFail();
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('fleet_fuel_logs')->insert([
            'id' => $id, 'vehicle_id' => $vehicle->id, 'log_date' => $logDate, 'litres' => $litres,
            'cost_minor' => $costMinor, 'odometer' => $odometer, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('fleet_fuel_logs')->where('id', $id)->firstOrFail();
    }

    public function logMaintenance(string $vehicleId, string $maintenanceDate, string $description, int $costMinor, string $accountCode, User $actor): object
    {
        if (trim($description) === '' || $costMinor <= 0) {
            throw new \DomainException('Maintenance description and cost are required.');
        }
        $vehicle = DB::connection('tenant')->table('fleet_vehicles')->where('id', $vehicleId)->firstOrFail();
        $id = (string) Str::ulid();
        $journal = $this->posting->post([
            'journal_type' => 'fleet_maintenance', 'entry_date' => $maintenanceDate,
            'description' => 'Fleet maintenance '.$vehicle->registration_number.': '.$description,
            'currency_code' => 'USD',
            'lines' => [
                ['account_code' => $accountCode, 'debit_minor' => $costMinor, 'memo' => $description],
                ['account_code' => '2100', 'credit_minor' => $costMinor, 'memo' => 'Fleet maintenance payable'],
            ],
            'source_type' => 'fleet_maintenance', 'source_id' => $id,
            'idempotency_key' => 'fleet:maintenance:'.$id, 'created_by' => $actor->getKey(),
        ]);
        DB::connection('tenant')->table('fleet_maintenance')->insert([
            'id' => $id, 'vehicle_id' => $vehicle->id, 'maintenance_date' => $maintenanceDate,
            'description' => $description, 'cost_minor' => $costMinor, 'account_code' => $accountCode,
            'journal_entry_id' => $journal->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record(action: 'fleet.maintenance.logged', after: ['vehicle_id' => $vehicle->id, 'cost_minor' => $costMinor, 'journal_id' => $journal->id], module: 'fleet');

        return DB::connection('tenant')->table('fleet_maintenance')->where('id', $id)->firstOrFail();
    }
}
