<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\CalendarConnection;
use App\Modules\Attendance\Infrastructure\Services\CalendarSyncService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2623 — scoping tenant des calendriers : company_id porté par
 * calendar_connections et calendar_events (écriture service + isolation).
 */
class CalendarTenantScopingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    public function test_connect_stores_company_id(): void
    {
        $connection = app(CalendarSyncService::class)->connect(
            $this->employee,
            'google',
            'access-token-test',
            'refresh-token-test',
        );

        $this->assertSame($this->company->id, $connection->company_id);
        $this->assertDatabaseHas('calendar_connections', [
            'id' => $connection->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_calendar_connection_is_scoped_to_tenant(): void
    {
        // Deux tenants : la connexion du tenant A n'est pas visible depuis B.
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        $connection = CalendarConnection::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'provider' => 'google',
            'access_token' => 'encrypted',
            'is_active' => true,
        ]);

        app()->instance('current_company', $otherCompany);

        $visible = CalendarConnection::query()->whereKey($connection->id)->first();
        $this->assertNull($visible, 'La connexion calendrier d’un autre tenant ne doit pas être visible.');
    }
}
