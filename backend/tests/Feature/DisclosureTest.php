<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\DisclosureService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class DisclosureTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_disclosure_helpers_return_structured_partial_data(): void
    {
        $council = $this->makeCouncil();
        $user = $this->makeUser('disc@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $user);

        $service = app(DisclosureService::class);
        $changes = $service->changesInNetAssets('2026-01-01', '2026-12-31');
        $this->assertSame('partial', $changes['disclosure_status']);
        $this->assertArrayHasKey('opening_net_assets_minor', $changes);
        $this->assertArrayHasKey('closing_net_assets_minor', $changes);

        $cash = $service->cashFlowSummary('2026-01-01', '2026-12-31');
        $this->assertSame('heuristic_partial', $cash['status']);
        $this->assertArrayHasKey('operating_minor', $cash);
    }
}
