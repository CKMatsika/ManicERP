<?php

namespace Tests\Feature;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $user = $this->makeUser('auditor@test.local', 'Auditor');
        $role = $this->makeRole($this->council, 'auditor', ['platform.audit.view']);
        $membership = $this->makeMembership($user, $this->council, $role);
        $this->actingAsStaff($user, $this->council, $membership);
    }

    public function test_events_chain_and_verify(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('test.first', metadata: ['value' => 1], module: 'platform.core');
        $logger->record('test.second', metadata: ['value' => 2, 'nested' => ['b' => 2, 'a' => 1]], module: 'platform.core');
        $logger->record('test.third', metadata: ['unicode' => 'Zimbabwe 🇿🇼'], module: 'platform.core');

        $result = $logger->verify();

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['checked']);
    }

    public function test_tampering_with_a_stored_event_breaks_the_chain(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('test.first');
        $logger->record('test.second');

        DB::connection('tenant')->table('audit_events')->where('chain_index', 1)->update(['action' => 'tampered']);

        $result = $logger->verify();

        $this->assertFalse($result['ok']);
        $this->assertSame(1, $result['broken_at']);
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $event = app(AuditLogger::class)->record('test.immutable');

        $this->expectException(\Throwable::class);
        $event->action = 'changed';
        $event->save();
    }

    public function test_audit_events_record_the_actor_and_role(): void
    {
        $event = app(AuditLogger::class)->record('test.actor');

        $this->assertSame('Auditor', $event->actor_name);
        $this->assertSame('auditor', $event->actor_role);
        $this->assertNotEmpty($event->hash);
        $this->assertNotSame($event->prev_hash, $event->hash);
    }
}
