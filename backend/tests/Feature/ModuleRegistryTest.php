<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\ModuleRegistry;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('modules@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);
        $this->actingAsStaff($this->admin, $this->council, $membership);
    }

    public function test_core_modules_cannot_be_disabled(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertFalse($registry->canDisable($this->council, 'platform.core'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot be disabled');

        $registry->setEnabled($this->council, 'platform.core', false, $this->admin->id);
    }

    public function test_a_module_with_active_dependants_cannot_be_disabled(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertTrue($registry->enabled($this->council, 'accounting'));
        $this->assertTrue($registry->enabled($this->council, 'revenue'));
        $this->assertContains('revenue', $registry->activeDependants($this->council, 'accounting'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('dependants');

        $registry->setEnabled($this->council, 'accounting', false, $this->admin->id);
    }

    public function test_dependants_can_be_disabled_before_their_prerequisite(): void
    {
        $registry = app(ModuleRegistry::class);

        $registry->setEnabled($this->council, 'licensing', false, $this->admin->id);
        $registry->setEnabled($this->council, 'customer', false, $this->admin->id);
        $registry->setEnabled($this->council, 'procurement', false, $this->admin->id);
        $registry->setEnabled($this->council, 'hr', false, $this->admin->id);
        $registry->setEnabled($this->council, 'fleet', false, $this->admin->id);
        $registry->setEnabled($this->council, 'revenue', false, $this->admin->id);
        $registry->setEnabled($this->council, 'accounting', false, $this->admin->id);

        $this->assertFalse($registry->enabled($this->council, 'revenue'));
        $this->assertFalse($registry->enabled($this->council, 'accounting'));
    }

    public function test_disabled_module_routes_are_blocked_server_side(): void
    {
        app(ModuleRegistry::class)->setEnabled($this->council, 'licensing', false, $this->admin->id);
        app(ModuleRegistry::class)->setEnabled($this->council, 'customer', false, $this->admin->id);
        app(ModuleRegistry::class)->setEnabled($this->council, 'revenue', false, $this->admin->id);

        $this->get('/revenue/accounts')->assertStatus(403);
    }

    public function test_unavailable_modules_cannot_be_enabled(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertFalse($registry->isAvailable('assets'));
        $this->assertFalse($registry->canEnable($this->council, 'assets'));

        $this->expectException(\DomainException::class);

        $registry->setEnabled($this->council, 'assets', true, $this->admin->id);
    }
}
