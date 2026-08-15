<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\CalendarConnection;
use App\Modules\Attendance\Infrastructure\Services\CalendarSyncService;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2623) : calendar_connections / calendar_events
 * portent désormais company_id (défense en profondeur) — le service
 * d'intégration renseigne company_id explicitement.
 */
class CalendarCompanyIdTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_calendar_connection_is_scoped_to_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $service = new CalendarSyncService;
        $connection = $service->connect($employee, 'google', 'token-encrypted', null, null, now()->addHour());

        $this->assertSame($company->id, $connection->company_id);
        $this->assertDatabaseHas('calendar_connections', [
            'id' => $connection->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        // company_id est NOT NULL (migration tenant).
        $result = DB::table('calendar_connections')
            ->whereNull('company_id')
            ->count();
        $this->assertSame(0, $result);
    }

    public function test_calendar_connection_model_has_company_scope(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id, 'role' => 'employee']);

        (new CalendarSyncService)->connect($employeeA, 'google', 'tok', null, null, now()->addHour());

        $all = CalendarConnection::query()->withoutGlobalScopes()->count();
        $this->assertSame(1, $all);
    }
}
