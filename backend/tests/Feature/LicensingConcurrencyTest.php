<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceCertificate;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\CouncilProvisioner;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class LicensingConcurrencyTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    public function test_concurrent_decisions_serialize_and_two_issuers_return_one_certificate(): void
    {
        $suffix = strtolower(Str::random(12));
        $database = 'manicerp_test_licence_race_'.$suffix;
        $provisioner = app(CouncilProvisioner::class);
        $council = null;
        $users = [];
        $processes = [];
        $created = false;
        DB::connection('pgsql')->commit();
        Storage::fake('documents');

        try {
            $provisioner->createDatabase($database);
            $created = true;
            $council = $this->makeCouncil(['code' => 'LR'.strtoupper($suffix), 'database_name' => $database]);
            $council->forceFill(['is_demo' => true])->save();
            $provisioner->migrateTenant($council);
            $staffRole = $this->makeRole($council, 'licence_race_staff', ['*']);
            $citizenRole = $this->makeRole($council, 'licence_race_citizen', ['*']);
            foreach (['preparer', 'approver_a', 'approver_b', 'issuer_a', 'issuer_b'] as $name) {
                $users[$name] = $this->makeUser($name.$suffix.'@licence-race.test');
                $this->makeMembership($users[$name], $council, $staffRole);
            }
            $users['citizen'] = $this->makeUser('citizen'.$suffix.'@licence-race.test');
            $citizenMembership = $this->makeMembership($users['citizen'], $council, $citizenRole, 'citizen');
            $this->seedAccounting($council, $users['preparer']);
            $account = $this->makeCustomer($council, $users['preparer'])['account'];
            PortalAccountLink::create([
                'user_id' => $users['citizen']->id, 'customer_account_id' => $account->id, 'relationship' => 'representative',
                'status' => 'verified', 'verification_method' => 'test', 'requested_at' => now(), 'verified_at' => now(),
            ]);
            $this->actingAsCitizen($users['citizen'], $council, $citizenMembership);
            $licensing = app(LicensingService::class);
            $type = $licensing->seedDemonstrationType($users['preparer']);
            $configuration = $type->versions()->first()->configuration;
            $configuration['inspection']['required'] = false;
            $configuration['approval_stages'] = ['review', 'approval', 'issuance'];
            $licensing->reviseType($type, ['configuration' => $configuration, 'approval_status' => 'demonstration'], $users['preparer']);
            $form = ['trading_name' => 'Concurrency Test', 'activity' => 'Retail'];
            $application = $licensing->saveDraft($users['citizen'], $account->id, $type->code, $form);
            foreach (['identity', 'premises'] as $documentType) {
                $document = app(DocumentService::class)->store(UploadedFile::fake()->create($documentType.'.txt', 1, 'text/plain'), 'confidential', $application, $users['citizen']);
                $licensing->attachDocument($users['citizen'], $account->id, $application, $document, $documentType);
            }
            $application = $licensing->submit($users['citizen'], $account->id, $type->code, $form, 'race-submit-'.$suffix, $application->id);
            $intent = $licensing->applicationPaymentIntent($users['citizen'], $application, 'race-payment-'.$suffix)['intent'];
            app(PaymentService::class)->settle($intent, 'succeeded', 'RACE-PAY-'.$suffix, ['amount_minor' => 2500]);
            $this->actingAsStaff($users['preparer'], $council, $users['preparer']->memberships()->first());
            $licensing->review($application->fresh(), $users['preparer']);

            $environment = $this->processEnvironment();
            $decisionMarker = 'licence-decision-'.$suffix;
            [$firstDecision, $secondDecision] = $this->contend($council, $users['approver_a'], $users['approver_b'], $application->id, 'decide', 'approved', 'rejected', $decisionMarker, $environment, $processes);
            $this->assertSame(0, $firstDecision->wait(), $firstDecision->getErrorOutput());
            $this->assertSame(0, $secondDecision->wait(), $secondDecision->getErrorOutput());
            $this->assertStringContainsString('ok:', $firstDecision->getOutput());
            $this->assertStringContainsString('rejected', $secondDecision->getOutput());
            $this->useCouncil($council);
            $this->assertSame('approved', LicenceApplication::findOrFail($application->id)->status);
            $this->assertSame(1, DB::connection('tenant')->table('audit_events')->where('action', 'licensing.application.approved')->count());

            $issueMarker = 'licence-issue-'.$suffix;
            [$firstIssue, $secondIssue] = $this->contend($council, $users['issuer_a'], $users['issuer_b'], $application->id, 'issue', '', '', $issueMarker, $environment, $processes);
            $this->assertSame(0, $firstIssue->wait(), $firstIssue->getErrorOutput());
            $this->assertSame(0, $secondIssue->wait(), $secondIssue->getErrorOutput());
            $firstOutput = $firstIssue->getOutput();
            $secondOutput = $secondIssue->getOutput();
            preg_match('/ok:([^\r\n]+)/', $firstOutput, $firstMatch);
            preg_match('/ok:([^\r\n]+)/', $secondOutput, $secondMatch);
            $this->assertNotEmpty($firstMatch[1] ?? null);
            $this->assertSame($firstMatch[1], $secondMatch[1] ?? null);
            $this->useCouncil($council);
            $this->assertSame(1, LicenceCertificate::where('licence_application_id', $application->id)->count());
            $this->assertSame(1, LicenceCertificate::where('licence_id', $application->fresh()->licence_id)->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            app(TenantManager::class)->clear();
            if ($council) {
                DB::connection('pgsql')->table('council_memberships')->where('council_id', $council->id)->delete();
                DB::connection('pgsql')->table('councils')->where('id', $council->id)->delete();
            }
            foreach ($users as $user) {
                DB::connection('pgsql')->table('users')->where('id', $user->id)->delete();
            }
            if ($created && preg_match('/^manicerp_test_licence_race_[a-z0-9]{12}$/', $database)) {
                $provisioner->dropDatabase($database);
            }
        }
    }

    private function contend(Council $council, User $firstUser, User $secondUser, string $applicationId, string $action, string $firstDecision, string $secondDecision, string $marker, array $environment, array &$processes): array
    {
        $input = new InputStream;
        $first = new Process([PHP_BINARY, base_path('tests/Support/licensing_race.php'), $council->id, $firstUser->id, $applicationId, $action, 'hold', $firstDecision, $marker.'-a'], base_path(), $environment, $input, 30);
        $processes[] = $first;
        $first->start();
        $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'locked')), $first->getErrorOutput());
        $second = new Process([PHP_BINARY, base_path('tests/Support/licensing_race.php'), $council->id, $secondUser->id, $applicationId, $action, 'compete', $secondDecision, $marker.'-b'], base_path(), $environment, null, 30);
        $processes[] = $second;
        $second->start();
        $this->assertTrue($second->waitUntil(fn ($type, $output) => str_contains($output, 'attempting')), $second->getErrorOutput());
        $blocked = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $blocked = (bool) DB::connection('pgsql')->selectOne("SELECT 1 AS blocked FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$marker.'-b']);
            if ($blocked) {
                break;
            }
            usleep(20000);
        }
        $this->assertTrue($blocked, 'Second licensing operation never contended on the locked application row.');
        $input->write("go\n");
        $input->close();

        return [$first, $second];
    }

    private function processEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => 'manicerp_test_central', 'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '', 'TENANT_DB_HOST' => config('database.connections.tenant.host'),
            'TENANT_DB_PORT' => (string) config('database.connections.tenant.port'), 'TENANT_DB_USERNAME' => config('database.connections.tenant.username'),
            'TENANT_DB_PASSWORD' => config('database.connections.tenant.password') ?? '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
    }
}
