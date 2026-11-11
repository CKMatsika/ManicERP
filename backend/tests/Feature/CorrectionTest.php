<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\CorrectionService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class CorrectionTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_controlled_correction_posts_balanced_journal_and_requires_independent_approval(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('corr-prep@test.local');
        $approver = $this->makeUser('corr-approver@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $prep);

        $service = app(CorrectionService::class);
        $correction = $service->prepareCorrection('Fix misclassified expense', [
            ['account_code' => '5020', 'debit_minor' => 1000, 'credit_minor' => 0, 'memo' => 'correction'],
            ['account_code' => '5030', 'debit_minor' => 0, 'credit_minor' => 1000, 'memo' => 'correction'],
        ], null, $prep);
        $this->assertSame('pending_approval', $correction->status);

        try {
            $service->approveCorrection($correction->id, $prep);
            $this->fail('Expected independent approval requirement.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('independent approver', $exception->getMessage());
        }

        $applied = $service->approveCorrection($correction->id, $approver);
        $this->assertSame('applied', $applied->status);
        $this->assertNotNull($applied->correction_journal_entry_id);

        // idempotency
        $same = $service->prepareCorrection('Fix misclassified expense', [
            ['account_code' => '5020', 'debit_minor' => 1000, 'credit_minor' => 0],
            ['account_code' => '5030', 'debit_minor' => 0, 'credit_minor' => 1000],
        ], null, $prep, 'corr-key-1');
        $again = $service->prepareCorrection('Fix misclassified expense', [
            ['account_code' => '5020', 'debit_minor' => 1000, 'credit_minor' => 0],
            ['account_code' => '5030', 'debit_minor' => 0, 'credit_minor' => 1000],
        ], null, $prep, 'corr-key-1');
        $this->assertSame($same->id, $again->id);

        // reject path
        $rej = $service->prepareCorrection('Another correction', [
            ['account_code' => '5020', 'debit_minor' => 500, 'credit_minor' => 0],
            ['account_code' => '5030', 'debit_minor' => 0, 'credit_minor' => 500],
        ], null, $prep);
        $rejected = $service->rejectCorrection($rej->id, 'Not approved', $approver);
        $this->assertSame('rejected', $rejected->status);
    }
}
