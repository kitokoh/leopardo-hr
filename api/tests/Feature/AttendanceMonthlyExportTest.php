<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\ExportHistory;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Export CSV mensuel des présences — Issue #5696.
 *
 * `GET /api/v1/export/attendance/monthly?month=YYYY-MM` :
 *   - manager seulement (403 pour un employé, 401 sans session) ;
 *   - synthèse par employé (jours/heures/HS/retards/…) via
 *     AttendanceReportService, même agrégation que le rapport mensuel ;
 *   - enveloppe JSON {format, content, filename, count, month} avec
 *     neutralisation OWASP (CsvCellSanitizer) ;
 *   - ligne ExportHistory tracée (type attendance_monthly) ;
 *   - `month` strictement validé (YYYY-MM), défaut = mois courant.
 */
class AttendanceMonthlyExportTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/export/attendance/monthly')->assertStatus(401);
    }

    public function test_ordinary_employee_gets_403(): void
    {
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/export/attendance/monthly')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_invalid_month_is_rejected(): void
    {
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/export/attendance/monthly?month=not-a-month')->assertStatus(422);
    }

    public function test_manager_gets_monthly_csv_summary(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Amine', 'last_name' => 'Benali']);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Sara', 'last_name' => 'Mansouri']);

        $month = Carbon::now()->startOfMonth();
        foreach ([$employeeA, $employeeB] as $index => $employee) {
            AttendanceLog::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $month->copy()->addDays($index)->toDateString(),
                'session_number' => 1,
                'check_in' => $month->copy()->addDays($index)->setTime(8, 0)->format('Y-m-d H:i:s'),
                'check_out' => $month->copy()->addDays($index)->setTime(17, 0)->format('Y-m-d H:i:s'),
                'method' => 'mobile',
                'status' => 'ontime',
                'hours_worked' => 8.0,
                'overtime_hours' => 0,
                'late_minutes' => 0,
            ]);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/export/attendance/monthly?month='.$month->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.month', $month->format('Y-m'))
            ->assertJsonPath('data.count', 2);

        $content = (string) $response->json('data.content');

        // En-têtes de colonnes + une ligne par employé visible.
        $this->assertStringContainsString('employee_id', $content);
        $this->assertStringContainsString('worked_days', $content);
        $this->assertStringContainsString((string) $employeeA->id, $content);
        $this->assertStringContainsString((string) $employeeB->id, $content);
    }

    public function test_export_history_is_recorded(): void
    {
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/export/attendance/monthly?month='.now()->format('Y-m'))->assertOk();

        $this->assertDatabaseHas('export_history', [
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'type' => 'attendance_monthly',
            'format' => 'csv',
        ]);

        $this->assertSame(1, ExportHistory::query()->where('company_id', $company->id)->count());
    }

    public function test_cross_tenant_employee_is_not_leaked_into_csv(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        $month = Carbon::now()->startOfMonth();
        AttendanceLog::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'date' => $month->toDateString(),
            'session_number' => 1,
            'check_in' => $month->setTime(8, 0)->format('Y-m-d H:i:s'),
            'check_out' => $month->setTime(17, 0)->format('Y-m-d H:i:s'),
            'method' => 'mobile',
            'status' => 'ontime',
            'hours_worked' => 8.0,
            'overtime_hours' => 0,
            'late_minutes' => 0,
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/export/attendance/monthly?month='.$month->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        $this->assertStringNotContainsString((string) $employeeB->id, (string) $response->json('data.content'));
    }
}
