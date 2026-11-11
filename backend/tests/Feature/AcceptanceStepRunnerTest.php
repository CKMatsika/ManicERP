<?php

namespace Tests\Feature;

use App\Modules\Platform\Services\AcceptanceStepRunner;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AcceptanceStepRunnerTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_completed_step_is_idempotent_and_rejects_changed_inputs(): void
    {
        $council = $this->makeCouncil();
        $this->useCouncil($council);
        $calls = 0;
        $runner = app(AcceptanceStepRunner::class);
        $first = $runner->run('test.stable', ['b' => ['y' => 2, 'x' => 1], 'a' => 1], function () use (&$calls): array {
            $calls++;

            return ['record' => 'synthetic-1'];
        });
        $second = $runner->run('test.stable', ['a' => 1, 'b' => ['x' => 1, 'y' => 2]], function () use (&$calls): array {
            $calls++;

            return ['record' => 'duplicate'];
        });
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
        $this->assertSame(1, DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', 'test.stable')->value('attempts'));

        $this->expectException(\RuntimeException::class);
        $runner->run('test.stable', ['a' => 2], fn () => ['record' => 'should-not-run']);
    }

    public function test_failed_step_can_resume_without_retaining_partial_transaction_effects(): void
    {
        $council = $this->makeCouncil();
        $this->useCouncil($council);
        $runner = app(AcceptanceStepRunner::class);
        try {
            $runner->run('test.retry', ['version' => 1], function (): array {
                DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', 'missing')->update(['status' => 'unexpected']);
                throw new \RuntimeException('Synthetic operation failure');
            });
            $this->fail('Expected the synthetic step to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic operation failure', $exception->getMessage());
        }
        $result = $runner->run('test.retry', ['version' => 1], fn () => ['resumed' => true]);
        $this->assertTrue($result['resumed']);
        $row = DB::connection('tenant')->table('acceptance_setup_steps')->where('step_key', 'test.retry')->first();
        $this->assertSame('complete', $row->status);
        $this->assertSame(2, (int) $row->attempts);
    }
}
