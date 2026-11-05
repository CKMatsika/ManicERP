<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ai\Models\PolicyDocument;
use App\Modules\Ai\Services\PolicyAssistantService;
use App\Modules\Platform\Models\AiUsageEvent;
use App\Modules\Platform\Models\Council;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class PolicyAssistantTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['manicerp.ai.enabled' => false, 'manicerp.ai.provider' => null, 'manicerp.ai.model' => null]);

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('aiadmin@test.local', 'AI Admin');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);
        $this->actingAsStaff($this->admin, $this->council, $membership);
    }

    public function test_approved_sources_are_retrieved_and_unapproved_documents_are_excluded(): void
    {
        $approved = $this->makeDocument('Water disconnection policy', 'approved', 'Before disconnecting water for non-payment the council must give fourteen days written notice.');
        $this->makeDocument('Draft water policy', 'draft', 'Draft text about water disconnection.');

        $result = app(PolicyAssistantService::class)->ask('How much notice is required before a water disconnection?', $this->admin);

        $this->assertFalse($result['generated']);
        $this->assertSame('ai_disabled', $result['notice']);
        $this->assertNotEmpty($result['sources']);
        $this->assertSame($approved->id, $result['sources'][0]['id']);
        $this->assertStringContainsString('fourteen days', $result['sources'][0]['excerpt']);
        $this->assertSame(0, AiUsageEvent::count());
    }

    public function test_no_matching_source_is_reported_without_calling_a_provider(): void
    {
        $result = app(PolicyAssistantService::class)->ask('Quotation for a helicopter landing pad', $this->admin);

        $this->assertFalse($result['generated']);
        $this->assertSame('no_sources', $result['notice']);
        $this->assertSame([], $result['sources']);
        $this->assertSame(0, AiUsageEvent::count());
    }

    public function test_budget_exhaustion_blocks_generation_but_still_returns_sources(): void
    {
        config([
            'manicerp.ai.enabled' => true,
            'manicerp.ai.provider' => 'openai',
            'manicerp.ai.model' => 'gpt-test',
            'manicerp.ai.monthly_budget_minor' => 100,
        ]);

        AiUsageEvent::create([
            'council_id' => $this->council->id,
            'user_id' => $this->admin->id,
            'feature' => 'policy_assistant',
            'provider' => 'openai',
            'model' => 'gpt-test',
            'input_tokens' => 100,
            'output_tokens' => 100,
            'cost_minor' => 250,
            'status' => 'succeeded',
            'occurred_at' => now(),
        ]);

        $this->makeDocument('Water disconnection policy', 'approved', 'Fourteen days notice is required before a water disconnection.');

        $result = app(PolicyAssistantService::class)->ask('What notice is required before a water disconnection?', $this->admin);

        $this->assertFalse($result['generated']);
        $this->assertSame('budget_exhausted', $result['notice']);
        $this->assertNotEmpty($result['sources']);
        $this->assertSame(1, AiUsageEvent::count());
    }

    public function test_a_user_without_the_ai_permission_is_refused(): void
    {
        $clerk = $this->makeUser('aiclerk@test.local');
        $role = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view']);
        $membership = $this->makeMembership($clerk, $this->council, $role);
        $this->actingAsStaff($clerk, $this->council, $membership);

        $this->expectException(AuthorizationException::class);

        app(PolicyAssistantService::class)->ask('Water policy', $clerk);
    }

    private function makeDocument(string $title, string $status, string $content): PolicyDocument
    {
        return PolicyDocument::create([
            'title' => $title,
            'version' => '1.0',
            'status' => $status,
            'effective_from' => '2026-01-01',
            'content' => $content,
            'checksum' => hash('sha256', $content),
            'approved_by' => $status === 'approved' ? $this->admin->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
    }
}
