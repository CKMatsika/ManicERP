<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ProcurementAccessTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_procurement_workbench_requires_permission_and_scopes_records_to_the_active_council(): void
    {
        $councilA = $this->makeCouncil(['code' => 'PROC-A']);
        $adminA = $this->makeUser('proc-a-admin@test.local');
        $membershipA = $this->makeMembership($adminA, $councilA, $this->makeRole($councilA, 'council_admin', ['*']));
        $this->actingAsStaff($adminA, $councilA, $membershipA);
        $this->get('/procurement')->assertOk()->assertInertia(fn ($page) => $page->component('Procurement/Index')->has('suppliers'));

        $councilB = $this->makeCouncil(['code' => 'PROC-B', 'database_name' => $this->secondaryTenantDatabase()]);
        $userB = $this->makeUser('proc-b-user@test.local');
        $membershipB = $this->makeMembership($userB, $councilB, $this->makeRole($councilB, 'read_only', []));
        config(['database.connections.tenant.database' => $councilB->database_name]);
        DB::purge('tenant');
        $this->actingAsStaff($userB, $councilB, $membershipB);
        $this->get('/procurement')->assertForbidden();
    }
}
