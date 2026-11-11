<?php

namespace Tests\Feature;

use App\Modules\Platform\Jobs\TenantContextException;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantCache;
use App\Modules\Platform\Services\TenantLogger;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Platform\Services\TenantScheduleRunner;
use App\Modules\Platform\Services\TenantStorage;
use App\Modules\Revenue\Models\Party;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\Support\FailingTenantJob;
use Tests\Support\ProbeTenantJob;
use Tests\TestCase;

class TenantQueueTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    private Council $councilA;

    private Council $councilB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
        Cache::flush();
        Context::flush();

        $this->councilA = $this->makeCouncil(['code' => 'QUEUEA', 'database_name' => $this->tenantDatabase()]);
        $this->councilB = $this->makeCouncil(['code' => 'QUEUEB', 'database_name' => $this->secondaryTenantDatabase()]);
        ProbeTenantJob::$runs = 0;
        FailingTenantJob::$runs = 0;
    }

    protected function tearDown(): void
    {
        foreach ([$this->councilA, $this->councilB] as $council) {
            try {
                app(TenantManager::class)->connect($council);

                foreach (['account_credits', 'customer_accounts', 'party_roles', 'contact_points', 'parties', 'audit_events', 'number_sequences'] as $table) {
                    DB::connection('tenant')->table($table)->delete();
                }
            } catch (\Throwable) {
                // Best-effort cleanup across the two physical test databases.
            }
        }

        Context::flush();
        Cache::flush();

        parent::tearDown();
    }

    public function test_consecutive_jobs_for_different_councils_keep_data_cache_files_and_context_separate(): void
    {
        (new ProbeTenantJob($this->councilA->id))->handle();
        (new ProbeTenantJob($this->councilB->id))->handle();
        (new ProbeTenantJob($this->councilA->id))->handle();

        $this->assertSame(3, ProbeTenantJob::$runs);

        $this->assertNull(Context::get('council'));
        $this->assertNull(Context::get('tenant_database'));
        $this->assertNull(app(TenantManager::class)->council());

        app(TenantManager::class)->connect($this->councilA);
        $this->assertSame(2, Party::where('display_name', 'Queued probe for QUEUEA')->count());
        $this->assertSame(0, Party::where('display_name', 'Queued probe for QUEUEB')->count());

        app(TenantManager::class)->connect($this->councilB);
        $this->assertSame(1, Party::where('display_name', 'Queued probe for QUEUEB')->count());
        $this->assertSame(0, Party::where('display_name', 'Queued probe for QUEUEA')->count());

        $this->assertSame('QUEUEA', Cache::get('council:QUEUEA:probe'));
        $this->assertSame('QUEUEB', Cache::get('council:QUEUEB:probe'));

        Storage::disk('documents')->assertExists('councils/QUEUEA/queue/probe-QUEUEA.txt');
        Storage::disk('documents')->assertExists('councils/QUEUEB/queue/probe-QUEUEB.txt');

        app(TenantManager::class)->clear();
        $this->assertNull(Context::get('council'));
        $this->assertNull(Context::get('tenant_database'));
        $this->assertNull(app(TenantManager::class)->council());
    }

    public function test_a_missing_council_context_fails_without_touching_data(): void
    {
        $this->expectException(TenantContextException::class);

        (new ProbeTenantJob((string) Str::ulid()))->handle();
    }

    public function test_an_inactive_council_is_rejected(): void
    {
        $this->councilB->forceFill(['status' => 'suspended'])->save();

        $this->expectException(TenantContextException::class);
        $this->expectExceptionMessage('No active council');

        (new ProbeTenantJob($this->councilB->id))->handle();
    }

    public function test_context_is_cleared_when_a_job_throws_and_the_next_job_still_works(): void
    {
        try {
            (new FailingTenantJob($this->councilA->id))->handle();
            $this->fail('Expected the failing job to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Probe failure', $exception->getMessage());
        }

        $this->assertNull(Context::get('council'));
        $this->assertNull(app(TenantManager::class)->council());

        (new ProbeTenantJob($this->councilB->id))->handle();

        app(TenantManager::class)->connect($this->councilB);
        $this->assertSame(1, Party::where('display_name', 'Queued probe for QUEUEB')->count());
    }

    public function test_tenant_jobs_declare_bounded_retries_and_timeout(): void
    {
        $job = new ProbeTenantJob($this->councilA->id);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff);
        $this->assertSame(120, $job->timeout);
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
    }

    public function test_database_worker_deserializes_consecutive_councils_and_clears_all_job_context(): void
    {
        $queue = app('queue')->connection('database');
        $queue->push(new ProbeTenantJob($this->councilA->id));
        $queue->push(new ProbeTenantJob($this->councilB->id));
        $worker = app('queue.worker');
        $options = new WorkerOptions(sleep: 0, maxTries: 3);
        $worker->runNextJob('database', 'default', $options);
        $worker->runNextJob('database', 'default', $options);
        $this->assertSame(2, ProbeTenantJob::$runs);
        $this->assertSame(0, DB::connection('pgsql')->table('jobs')->count());
        $this->assertNull(Context::get('probe_council'));
        $this->assertNull(app(TenantManager::class)->council());
        $this->assertSame('QUEUEA', Cache::get('council:QUEUEA:probe'));
        $this->assertSame('QUEUEB', Cache::get('council:QUEUEB:probe'));
    }

    public function test_database_worker_retries_and_records_final_failure_then_processes_another_council(): void
    {
        $queue = app('queue')->connection('database');
        $queue->push(new FailingTenantJob($this->councilA->id));
        $worker = app('queue.worker');
        $options = new WorkerOptions(sleep: 0, maxTries: 3);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            DB::connection('pgsql')->table('jobs')->update(['available_at' => now()->timestamp - 1]);
            $worker->runNextJob('database', 'default', $options);
            $this->assertNull(app(TenantManager::class)->council());
            $this->assertNull(Context::get('council'));
        }
        $this->assertSame(3, FailingTenantJob::$runs);
        $this->assertSame(0, DB::connection('pgsql')->table('jobs')->count());
        $queue->push(new ProbeTenantJob($this->councilB->id));
        $worker->runNextJob('database', 'default', $options);
        $this->assertSame(1, ProbeTenantJob::$runs);
    }

    public function test_storage_traversal_cannot_escape_the_council_prefix(): void
    {
        app(TenantManager::class)->connect($this->councilA);
        $this->expectException(\DomainException::class);
        app(TenantStorage::class)->put('../QUEUEB/private.txt', 'escaped');
    }

    public function test_council_log_payloads_are_written_to_separate_files(): void
    {
        $marker = 'isolation-'.Str::random(20);
        app(TenantManager::class)->runFor($this->councilA, fn () => app(TenantLogger::class)->write('info', $marker.'-A'));
        app(TenantManager::class)->runFor($this->councilB, fn () => app(TenantLogger::class)->write('info', $marker.'-B'));
        $a = file_get_contents(storage_path('logs/councils/QUEUEA.log'));
        $b = file_get_contents(storage_path('logs/councils/QUEUEB.log'));
        $this->assertStringContainsString($marker.'-A', $a);
        $this->assertStringNotContainsString($marker.'-B', $a);
        $this->assertStringContainsString($marker.'-B', $b);
        $this->assertStringNotContainsString($marker.'-A', $b);
    }

    public function test_nested_context_restores_caller_and_removes_custom_job_log_fields(): void
    {
        app(TenantManager::class)->connect($this->councilA);
        Context::add('request_reference', 'caller');
        (new ProbeTenantJob($this->councilB->id))->handle();
        $this->assertSame($this->councilA->id, app(TenantManager::class)->requireCouncil()->id);
        $this->assertSame('QUEUEA', Context::get('council'));
        $this->assertSame('caller', Context::get('request_reference'));
        $this->assertNull(Context::get('probe_council'));
        app(TenantManager::class)->clear();
    }

    public function test_scheduled_task_runner_runs_each_council_and_isolates_failures(): void
    {
        $seen = [];

        $results = app(TenantScheduleRunner::class)->run('probe', function (Council $council) use (&$seen) {
            $seen[] = $council->code;

            if ($council->code === 'QUEUEB') {
                throw new \RuntimeException('Scheduled failure for QUEUEB');
            }

            app(TenantCache::class)->put('scheduled', $council->code);

            return 'ok';
        });

        $this->assertSame(['QUEUEA', 'QUEUEB'], $seen);
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['ok']);
        $this->assertFalse($results[1]['ok']);
        $this->assertSame('QUEUEA', Cache::get('council:QUEUEA:scheduled'));
        $this->assertNull(Cache::get('council:QUEUEB:scheduled'));
        $this->assertNull(Context::get('council'));
    }
}
