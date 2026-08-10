<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Infrastructure\Services\PayrollCycleService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-3 (#1663) — Durcissement paie :
 *  - `social_contributions.effective_from` NOT NULL (migration additive + backfill) ;
 *  - `safeEmployeeBalance` propage l'erreur (500 explicite + Log) au lieu de valeurs vides ;
 *  - migrations additives geo_auto (000205) et company_id UUID (000003) ;
 *  - `SET search_path TO public` retiré de public/0001.
 *
 * Les migrations additives sont validées sur base fraîche (RefreshTenantDatabase
 * = vraies migrations public + tenant), et la validation store
 * `after:effective_from` est couverte par l'API.
 */
class PayrollHardeningTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
    }

    public function test_social_contributions_effective_from_is_not_nullable(): void
    {
        $isNullable = DB::selectOne(
            'SELECT is_nullable
               FROM information_schema.columns
              WHERE table_schema = current_schema()
                AND table_name = ?
                AND column_name = ?',
            ['social_contributions', 'effective_from']
        );

        $this->assertNotNull($isNullable);
        $this->assertSame('NO', $isNullable->is_nullable);
    }

    public function test_attendance_logs_accepts_geo_auto_method(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $log = AttendanceLog::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'date' => '2026-08-01',
            'check_in' => '2026-08-01 08:00:00',
            'check_out' => '2026-08-01 17:00:00',
            'method' => 'geo_auto',
            'status' => 'ontime',
            'hours_worked' => 8,
        ]);

        $this->assertSame('geo_auto', $log->refresh()->method);
    }

    public function test_zkteco_devices_company_id_is_uuid(): void
    {
        $this->assertCompanyIdColumnType('zkteco_devices', 'uuid');
    }

    public function test_kiosk_announcements_company_id_is_uuid(): void
    {
        $this->assertCompanyIdColumnType('kiosk_announcements', 'uuid');
    }

    public function test_social_contribution_store_rejects_effective_to_before_effective_from(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'CNAS — Sécurité sociale',
            'code' => 'CNAS-SS',
            'type' => 'employee',
            'rate' => 9,
            'effective_from' => '2026-01-01',
            'effective_to' => '2025-12-31', // avant effective_from → 422
        ])->assertStatus(422)->assertJsonValidationErrors('effective_to');
    }

    public function test_mobile_summary_propagates_balance_errors(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);

        /** @var PayrollCycleService&\Mockery\MockInterface $service */
        $service = Mockery::mock(PayrollCycleService::class)->makePartial();
        /** @var \Mockery\Expectation $expectation */
        $expectation = $service->shouldReceive('getEmployeeBalance');
        $expectation->once()->andThrow(new \RuntimeException('paie indisponible'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('paie indisponible');

        $service->getMobileSummary($manager);
    }

    private function assertCompanyIdColumnType(string $table, string $expectedType): void
    {
        $row = DB::selectOne(
            'SELECT data_type
               FROM information_schema.columns
              WHERE table_schema = current_schema()
                AND table_name = ?
                AND column_name = ?',
            [$table, 'company_id']
        );

        $this->assertNotNull($row, "Colonne company_id absente sur {$table}");
        $this->assertSame($expectedType, $row->data_type, "Type de company_id sur {$table}");
    }
}
