<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\ChartImportBatch;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Services\ChartImportService;
use App\Modules\Platform\Models\Council;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ChartImportTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('chartadmin@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);
        $this->actingAsStaff($this->admin, $this->council, $membership);
    }

    public function test_leading_zero_account_codes_are_preserved(): void
    {
        $batch = $this->import($this->csv([
            ['0010', 'Assets', 'asset', 'head', '', 'debit', 'false'],
            ['0011', 'Cash', 'asset', 'posting', '0010', 'debit', 'false'],
        ]));

        $this->assertSame(0, $batch->error_count);

        $chart = app(ChartImportService::class)->approve($batch, $this->admin);

        $this->assertDatabaseHas('gl_accounts', ['chart_version_id' => $chart->id, 'code' => '0010'], 'tenant');
        $this->assertDatabaseHas('gl_accounts', ['chart_version_id' => $chart->id, 'code' => '0011'], 'tenant');

        $cash = GlAccount::where('chart_version_id', $chart->id)->where('code', '0011')->firstOrFail();
        $head = GlAccount::where('chart_version_id', $chart->id)->where('code', '0010')->firstOrFail();

        $this->assertSame($head->id, $cash->parent_id);
    }

    public function test_duplicate_codes_are_rejected_before_approval(): void
    {
        $batch = $this->import($this->csv([
            ['1000', 'Assets', 'asset', 'head', '', 'debit', 'false'],
            ['1000', 'Duplicate assets', 'asset', 'head', '', 'debit', 'false'],
        ]));

        $this->assertGreaterThan(0, $batch->error_count);
        $this->assertSame('validated_with_errors', $batch->status);

        $this->expectException(\DomainException::class);
        app(ChartImportService::class)->approve($batch, $this->admin);
    }

    public function test_parent_cycles_are_rejected(): void
    {
        $batch = $this->import($this->csv([
            ['2000', 'A', 'asset', 'subhead', '2001', 'debit', 'false'],
            ['2001', 'B', 'asset', 'subhead', '2000', 'debit', 'false'],
        ]));

        $this->assertGreaterThan(0, $batch->error_count);
        $this->assertStringContainsString('cycle', json_encode($batch->rows()->pluck('errors')));
    }

    public function test_missing_parent_is_rejected(): void
    {
        $batch = $this->import($this->csv([
            ['3000', 'Orphan', 'asset', 'posting', '9999', 'debit', 'false'],
        ]));

        $this->assertGreaterThan(0, $batch->error_count);
    }

    public function test_approval_activates_the_chart_and_supersedes_the_previous_active_version(): void
    {
        $first = $this->import($this->csv([
            ['1000', 'Assets', 'asset', 'head', '', 'debit', 'false'],
        ]), 'Chart A');

        $firstChart = app(ChartImportService::class)->approve($first, $this->admin);
        $this->assertSame('active', $firstChart->fresh()->status);

        $second = $this->import($this->csv([
            ['1000', 'Assets revised', 'asset', 'head', '', 'debit', 'false'],
        ]), 'Chart A');

        $secondChart = app(ChartImportService::class)->approve($second, $this->admin);

        $this->assertSame('superseded', $firstChart->fresh()->status);
        $this->assertSame('active', $secondChart->fresh()->status);
        $this->assertSame(1, ChartVersion::where('status', 'active')->count());
    }

    public function test_official_charts_clear_the_production_blocker_and_demo_charts_keep_it(): void
    {
        $demo = $this->import($this->csv([['1000', 'Assets', 'asset', 'head', '', 'debit', 'false']]), 'Demo chart', 'demo');
        $demoChart = app(ChartImportService::class)->approve($demo, $this->admin);
        $this->assertTrue($demoChart->production_blocker);

        $official = $this->import($this->csv([['1000', 'Assets', 'asset', 'head', '', 'debit', 'false']]), 'Official chart', 'official');
        $officialChart = app(ChartImportService::class)->approve($official, $this->admin);
        $this->assertFalse($officialChart->production_blocker);
    }

    private function import(string $contents, string $name = 'Test chart', string $sourceType = 'demo'): ChartImportBatch
    {
        $this->useCouncil($this->council);

        return app(ChartImportService::class)->importContents(
            'chart.csv',
            $contents,
            'csv',
            ['name' => $name, 'source_type' => $sourceType],
            $this->admin,
        );
    }

    /** @param array<int, array<int, string>> $rows */
    private function csv(array $rows): string
    {
        $header = 'code,name,class,type,parent_code,normal_balance,is_control';
        $lines = [$header];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines);
    }
}
