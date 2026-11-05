<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\AccountLiability;
use App\Modules\Revenue\Models\ContactPoint;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Meter;
use App\Modules\Revenue\Models\OwnershipInterest;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\PartyRole;
use App\Modules\Revenue\Models\Property;
use App\Modules\Revenue\Models\PropertyValuation;
use App\Modules\Revenue\Models\ServiceConnection;
use App\Modules\Revenue\Models\TariffBand;
use App\Modules\Revenue\Models\TariffVersion;
use App\Modules\Revenue\Models\Ward;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Master-data application services. These enforce uniqueness and explicit,
 * dated liability/ownership rather than silently transferring history.
 */
class MasterDataService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{party_type: string, display_name: string, first_name?: string, last_name?: string, registration_number?: string, tax_number?: string, roles?: array<int, string>, email?: string, phone?: string}  $data
     */
    public function createParty(array $data, ?User $user = null): Party
    {
        if (! in_array($data['party_type'], Party::TYPES, true)) {
            throw new \DomainException('Party type must be individual or organisation.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $user) {
            $party = Party::create([
                'code' => $this->sequences->next('party', 'P', 6),
                'party_type' => $data['party_type'],
                'display_name' => $data['display_name'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'status' => 'active',
                'created_by' => $user?->getKey(),
            ]);

            foreach ($data['roles'] ?? ['resident'] as $role) {
                PartyRole::create([
                    'party_id' => $party->id,
                    'role' => $role,
                    'effective_from' => now()->toDateString(),
                ]);
            }

            if (! empty($data['email'])) {
                ContactPoint::create([
                    'party_id' => $party->id,
                    'type' => 'email',
                    'value' => strtolower($data['email']),
                    'is_primary' => true,
                ]);
            }

            if (! empty($data['phone'])) {
                ContactPoint::create([
                    'party_id' => $party->id,
                    'type' => 'phone',
                    'value' => $data['phone'],
                    'is_primary' => true,
                ]);
            }

            $this->audit->record(
                action: 'revenue.party.created',
                subject: $party,
                after: ['code' => $party->code, 'display_name' => $party->display_name, 'type' => $party->party_type],
                module: 'revenue',
            );

            return $party->load('roles', 'contacts');
        });
    }

    /**
     * @param  array{parcel_number: string, stand_number?: string, ward_code?: string, address_line?: string, suburb?: string, gps_lat?: float|string|null, gps_lng?: float|string|null, area_sqm?: string|null, land_use?: string}  $data
     */
    public function createProperty(array $data, ?User $user = null): Property
    {
        if (Property::where('parcel_number', $data['parcel_number'])->exists()) {
            throw new \DomainException('A property with this parcel number already exists.');
        }

        $ward = null;

        if (! empty($data['ward_code'])) {
            $ward = Ward::where('code', $data['ward_code'])->first();

            if (! $ward) {
                throw new \DomainException("Unknown ward [{$data['ward_code']}].");
            }
        }

        $property = Property::create([
            'parcel_number' => $data['parcel_number'],
            'stand_number' => $data['stand_number'] ?? null,
            'ward_id' => $ward?->id,
            'address_line' => $data['address_line'] ?? null,
            'suburb' => $data['suburb'] ?? null,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'area_sqm' => $data['area_sqm'] ?? null,
            'land_use' => $data['land_use'] ?? 'residential',
            'status' => 'active',
            'created_by' => $user?->getKey(),
        ]);

        $this->audit->record(
            action: 'revenue.property.created',
            subject: $property,
            after: ['parcel_number' => $property->parcel_number, 'ward' => $ward?->code],
            module: 'revenue',
        );

        return $property;
    }

    public function recordValuation(Property $property, string $valuationDate, int $landValueMinor, int $improvementsValueMinor, ?User $user = null): PropertyValuation
    {
        if ($landValueMinor < 0 || $improvementsValueMinor < 0) {
            throw new \DomainException('Valuation amounts cannot be negative.');
        }

        $valuation = PropertyValuation::updateOrCreate(
            ['property_id' => $property->id, 'valuation_date' => $valuationDate],
            [
                'land_value_minor' => $landValueMinor,
                'improvements_value_minor' => $improvementsValueMinor,
                'total_value_minor' => $landValueMinor + $improvementsValueMinor,
                'status' => 'draft',
            ],
        );

        $this->audit->record(
            action: 'revenue.valuation.recorded',
            subject: $valuation,
            after: ['property' => $property->parcel_number, 'total_minor' => $valuation->total_value_minor],
            module: 'revenue',
        );

        return $valuation;
    }

    public function approveValuation(PropertyValuation $valuation, User $approver): PropertyValuation
    {
        if ($valuation->status === 'approved') {
            return $valuation;
        }

        $valuation->status = 'approved';
        $valuation->approved_by = $approver->getKey();
        $valuation->approved_at = now();
        $valuation->save();

        $this->audit->record(
            action: 'revenue.valuation.approved',
            subject: $valuation,
            before: ['status' => 'draft'],
            after: ['status' => 'approved', 'total_minor' => $valuation->total_value_minor],
            module: 'revenue',
        );

        return $valuation;
    }

    public function createOwnership(Property $property, Party $party, string $interestType, string $effectiveFrom, string $sharePct = '100', ?User $user = null): OwnershipInterest
    {
        $share = Decimal::toScaled($sharePct, 2);

        if ($share <= 0 || $share > 10000) {
            throw new \DomainException('Ownership share must be between 0 and 100 percent.');
        }

        $activeShare = OwnershipInterest::where('property_id', $property->id)
            ->where('interest_type', $interestType)
            ->where('status', 'approved')
            ->where(function ($query) use ($effectiveFrom) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->get()
            ->sum(fn (OwnershipInterest $interest) => Decimal::toScaled((string) $interest->share_pct, 2));

        if (($activeShare + $share) > 10000) {
            throw new \DomainException('Total ownership share would exceed 100 percent for this property.');
        }

        $interest = OwnershipInterest::create([
            'property_id' => $property->id,
            'party_id' => $party->id,
            'interest_type' => $interestType,
            'share_pct' => $sharePct,
            'effective_from' => $effectiveFrom,
            'status' => 'pending',
        ]);

        $this->audit->record(
            action: 'revenue.ownership.recorded',
            subject: $interest,
            after: ['property' => $property->parcel_number, 'party' => $party->code, 'share_pct' => $sharePct],
            module: 'revenue',
        );

        return $interest;
    }

    public function createAccount(Property $property, Party $party, string $accountType = 'rates', ?User $user = null): CustomerAccount
    {
        if (! in_array($accountType, CustomerAccount::TYPES, true)) {
            throw new \DomainException('Unknown account type.');
        }

        return DB::connection('tenant')->transaction(function () use ($property, $party, $accountType, $user) {
            $account = CustomerAccount::create([
                'account_number' => $this->sequences->next('customer_account', 'ACC', 7),
                'property_id' => $property->id,
                'primary_party_id' => $party->id,
                'account_type' => $accountType,
                'status' => 'active',
                'opened_on' => now()->toDateString(),
                'currency_code' => 'USD',
                'billing_day' => 1,
                'created_by' => $user?->getKey(),
            ]);

            // Liability is explicit and dated; historic debt is not inherited.
            AccountLiability::create([
                'customer_account_id' => $account->id,
                'party_id' => $party->id,
                'liability_type' => 'primary',
                'effective_from' => now()->toDateString(),
            ]);

            $this->audit->record(
                action: 'revenue.account.created',
                subject: $account,
                after: ['account_number' => $account->account_number, 'party' => $party->code, 'type' => $accountType],
                module: 'revenue',
            );

            return $account;
        });
    }

    public function addConnection(CustomerAccount $account, string $serviceType, ?Property $property = null, ?User $user = null): ServiceConnection
    {
        $connection = ServiceConnection::create([
            'customer_account_id' => $account->id,
            'property_id' => $property?->id ?? $account->property_id,
            'connection_number' => $this->sequences->next('service_connection', 'SC', 6),
            'service_type' => $serviceType,
            'status' => 'active',
            'installed_on' => now()->toDateString(),
        ]);

        $this->audit->record(
            action: 'revenue.connection.created',
            subject: $connection,
            after: ['connection_number' => $connection->connection_number, 'service' => $serviceType],
            module: 'revenue',
        );

        return $connection;
    }

    public function addMeter(ServiceConnection $connection, string $serialNumber, string $initialReading = '0', ?User $user = null): Meter
    {
        if (Meter::where('serial_number', $serialNumber)->exists()) {
            throw new \DomainException('A meter with this serial number already exists.');
        }

        $meter = Meter::create([
            'service_connection_id' => $connection->id,
            'serial_number' => $serialNumber,
            'meter_type' => $connection->service_type,
            'digits' => 6,
            'installed_on' => now()->toDateString(),
            'status' => 'active',
            'initial_reading' => $initialReading,
        ]);

        $this->audit->record(
            action: 'revenue.meter.installed',
            subject: $meter,
            after: ['serial' => $serialNumber, 'connection' => $connection->connection_number, 'initial' => $initialReading],
            module: 'revenue',
        );

        return $meter;
    }

    public function createTariff(
        string $code,
        string $name,
        string $serviceType,
        string $chargeBasis,
        string $effectiveFrom,
        array $bands,
        string $currency = 'USD',
        ?User $user = null,
    ): TariffVersion {
        if ($bands === []) {
            throw new \DomainException('A tariff requires at least one band.');
        }

        return DB::connection('tenant')->transaction(function () use ($code, $name, $serviceType, $chargeBasis, $effectiveFrom, $bands, $currency) {
            $versionNo = (int) TariffVersion::where('code', $code)->max('version_no') + 1;

            $tariff = TariffVersion::create([
                'code' => $code,
                'name' => $name,
                'service_type' => $serviceType,
                'charge_basis' => $chargeBasis,
                'status' => 'draft',
                'effective_from' => $effectiveFrom,
                'currency_code' => $currency,
                'version_no' => $versionNo,
                'notes' => null,
            ]);

            foreach (array_values($bands) as $index => $band) {
                TariffBand::create([
                    'tariff_version_id' => $tariff->id,
                    'band_no' => $index + 1,
                    'from_units' => $band['from_units'] ?? '0',
                    'to_units' => $band['to_units'] ?? null,
                    'rate_minor' => (int) ($band['rate_minor'] ?? 0),
                    'fixed_charge_minor' => (int) ($band['fixed_charge_minor'] ?? 0),
                    'minimum_charge_minor' => (int) ($band['minimum_charge_minor'] ?? 0),
                    'description' => $band['description'] ?? null,
                ]);
            }

            $this->audit->record(
                action: 'revenue.tariff.created',
                subject: $tariff,
                after: ['code' => $code, 'version' => $versionNo, 'basis' => $chargeBasis, 'bands' => count($bands)],
                module: 'revenue',
            );

            return $tariff->load('bands');
        });
    }

    public function approveTariff(TariffVersion $tariff, User $approver): TariffVersion
    {
        if ($tariff->status === 'approved') {
            return $tariff;
        }

        $tariff->status = 'approved';
        $tariff->approved_by = $approver->getKey();
        $tariff->approved_at = now();
        $tariff->save();

        $this->audit->record(
            action: 'revenue.tariff.approved',
            subject: $tariff,
            before: ['status' => 'draft'],
            after: ['status' => 'approved', 'effective_from' => $tariff->effective_from->toDateString()],
            module: 'revenue',
        );

        return $tariff;
    }

    public function assignTariff(CustomerAccount $account, TariffVersion $tariff, string $effectiveFrom): void
    {
        if ($tariff->status !== 'approved') {
            throw new \DomainException('Only approved tariffs may be assigned.');
        }

        DB::connection('tenant')->table('account_tariffs')->insert([
            'id' => (string) Str::ulid(),
            'customer_account_id' => $account->id,
            'service_type' => $tariff->service_type,
            'tariff_version_id' => $tariff->id,
            'effective_from' => $effectiveFrom,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
