<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Application\Actions\GenerateAbsenteeismReportAction;
use App\Modules\Platform\Application\Actions\GenerateHeadcountReportAction;
use App\Modules\Platform\Application\Actions\GeneratePayrollSummaryReportAction;
use App\Modules\Platform\Application\Actions\GenerateTrainingProgressReportAction;
use App\Modules\Platform\Application\Actions\GenerateTurnoverReportAction;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Actions des rapports RH cross-tenant (issue #6569, audit DDD M1) :
 * la logique métier extraite de PlatformHrReportController doit être
 * invocable directement et produire le contrat {columns, rows}.
 */
class HrReportActionsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->seedEmployees();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function seedEmployees(): void
    {
        $employees = DB::table('shared_tenants.employees');
        foreach ([['active', '2026-01-10'], ['active', '2026-02-10'], ['on_leave', '2026-03-10']] as [$status, $contractStart]) {
            $employees->insert([
                'company_id' => '11111111-1111-1111-1111-111111111111',
                'email' => 'hr-action-'.bin2hex(random_bytes(4)).'@leopardo-rh.com',
                'password_hash' => bcrypt('secret123'),
                'first_name' => 'Action',
                'last_name' => 'Test',
                'status' => $status,
                'contract_start' => $contractStart,
            ]);
        }
    }

    /** @test */
    public function headcount_action_returns_columns_rows_and_total(): void
    {
        $report = app(GenerateHeadcountReportAction::class)->execute();

        $this->assertSame(['Statut', 'Effectif'], $report['columns']);
        $this->assertNotEmpty($report['rows']);

        $totals = array_column($report['rows'], 'Effectif');
        $this->assertSame(3, array_sum($totals));
        $this->assertSame('TOTAL', $report['rows'][count($report['rows']) - 1]['Statut']);
    }

    /** @test */
    public function turnover_action_returns_report_contract(): void
    {
        $report = app(GenerateTurnoverReportAction::class)->execute('2026-01-01', '2026-12-31');

        $this->assertSame(['Mois', 'Embauches', 'Departs', 'Effectif net'], $report['columns']);
        $this->assertIsArray($report['rows']);
    }

    /** @test */
    public function absenteeism_action_returns_report_contract(): void
    {
        $report = app(GenerateAbsenteeismReportAction::class)->execute('2026-01-01', '2026-12-31');

        $this->assertSame(['Type', 'Jours'], $report['columns']);
        $this->assertIsArray($report['rows']);
    }

    /** @test */
    public function payroll_summary_action_returns_report_contract(): void
    {
        $report = app(GeneratePayrollSummaryReportAction::class)->execute('2026-01-01', '2026-12-31');

        $this->assertSame(['Mois', 'Bulletins', 'Brut', 'Net'], $report['columns']);
        $this->assertIsArray($report['rows']);
    }

    /** @test */
    public function training_progress_action_returns_report_contract(): void
    {
        $report = app(GenerateTrainingProgressReportAction::class)->execute('2026-01-01', '2026-12-31');

        $this->assertSame(['Formation', 'Inscrits', 'Completes'], $report['columns']);
        $this->assertIsArray($report['rows']);
    }
}
