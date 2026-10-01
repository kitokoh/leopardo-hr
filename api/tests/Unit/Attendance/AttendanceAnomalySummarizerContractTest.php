<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalyService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalySummarizerAdapter;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Shared\Contracts\Attendance\AttendanceAnomalySummarizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-023 cycle 3 (#8296) — contrat partagé `AttendanceAnomalySummarizer` :
 * le container résout l'adapter Attendance, l'autorisation `viewOwnAnomalies`
 * est appliquée côté adapter (invité refusé), et `employee_id` est FORCÉ à
 * l'appelant quelle que soit la valeur reçue (séquence historique de
 * `MeController::attendanceAnomalies`, HR — 403/422 inchangés).
 */
class AttendanceAnomalySummarizerContractTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_contract_resolves_to_attendance_adapter(): void
    {
        $this->assertInstanceOf(
            AttendanceAnomalySummarizerAdapter::class,
            $this->app->make(AttendanceAnomalySummarizer::class),
        );
    }

    public function test_guest_is_denied_before_any_delegation(): void
    {
        [, $employee] = $this->seedCompanyAndEmployee();

        // Aucun utilisateur authentifié : la Gate `viewOwnAnomalies` refuse
        // et le service métier n'est JAMAIS appelé (403 historique inchangé).
        $service = Mockery::mock(AttendanceAnomalyService::class);
        $service->shouldNotReceive('summarize');
        $this->instance(AttendanceAnomalyService::class, $service);

        $this->expectException(AuthorizationException::class);

        $this->app->make(AttendanceAnomalySummarizer::class)
            ->summarizeOwnAnomalies($employee, []);
    }

    public function test_employee_id_is_forced_to_the_caller(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();
        $this->be($employee); // employé authentifié : viewOwnAnomalies = true

        $captured = null;
        $service = Mockery::mock(AttendanceAnomalyService::class, function (MockInterface $mock) use (&$captured): void {
            $mock->shouldReceive('summarize')
                ->once()
                ->withArgs(function (string $companyId, array $filters, ?Employee $scopeActor) use (&$captured): bool {
                    $captured = [$companyId, $filters, $scopeActor];

                    return true;
                })
                ->andReturn(['data' => []]);
        });
        $this->instance(AttendanceAnomalyService::class, $service);

        $result = $this->app->make(AttendanceAnomalySummarizer::class)
            ->summarizeOwnAnomalies($employee, ['employee_id' => 999999, 'per_page' => 5]);

        $this->assertSame(['data' => []], $result);
        if ($captured === null) {
            $this->fail('Le service métier n\'a pas été appelé par l\'adapter.');
        }
        /** @var array{0: string, 1: array<string, mixed>, 2: Employee|null} $captured */
        // employee_id étranger écrasé par celui de l'appelant ; autres filtres préservés.
        $this->assertSame($employee->id, $captured[1]['employee_id']);
        $this->assertSame(5, $captured[1]['per_page']);
        // Mêmes arguments de délégation que l'appel historique du contrôleur.
        $this->assertSame((string) $company->id, $captured[0]);
        $this->assertNull($captured[2]);
    }

    /** @return array{0: Company, 1: Employee} */
    private function seedCompanyAndEmployee(): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Jour',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => true,
        ]);

        $employee = Employee::query()->forceCreate([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 17600,
            'hourly_rate' => 0,
        ]);

        return [$company, $employee];
    }
}
