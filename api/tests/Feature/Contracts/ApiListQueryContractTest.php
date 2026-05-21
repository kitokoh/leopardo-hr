<?php

namespace Tests\Feature\Contracts;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\PaySlipLine;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ApiListQueryContractTest extends TestCase
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

    public function test_employees_index_supports_validated_filters_sorting_pagination_and_empty_payloads(): void
    {
        [$company, $manager] = $this->actors();

        Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Zed',
            'email' => 'amina.zed@example.test',
            'role' => 'employee',
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Alpha',
            'email' => 'amina.alpha@example.test',
            'role' => 'employee',
            'status' => 'archived',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees?search=Amina&status=active&sort_by=last_name&sort_dir=desc&per_page=5');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'amina.zed@example.test')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/employees?search=not-found&per_page=5')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/employees?sort_by=password_hash')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_absences_index_supports_filters_sorting_pagination_and_empty_payloads(): void
    {
        [$company, $manager, $employee] = $this->actors();
        $type = AbsenceType::factory()->create(['company_id' => $company->id, 'name' => 'Paid leave', 'code' => 'CP']);

        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-21',
            'days_count' => 2,
            'status' => 'approved',
        ]);
        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'days_count' => 1,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/absences?status=approved&sort_by=start_date&sort_dir=asc&per_page=10');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.absence_type.code', 'CP')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/absences?month=1&year=2026&per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/absences?sort_by=employee_id')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_attendance_index_supports_status_filter_sorting_pagination_and_empty_payloads(): void
    {
        [$company, $manager, $employee] = $this->actors();

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-01',
            'check_in' => Carbon::parse('2026-05-01 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-01 16:00:00', 'UTC'),
            'status' => 'ontime',
        ]);
        AttendanceLog::factory()->late()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-02',
            'check_in' => Carbon::parse('2026-05-02 08:30:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-02 16:00:00', 'UTC'),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance?status=late&date_from=2026-05-01&date_to=2026-05-31&sort_by=date&sort_dir=asc&per_page=10');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'late')
            ->assertJsonPath('data.0.employee.id', $employee->id)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/attendance?status=absent&date_from=2026-05-01&date_to=2026-05-31')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/attendance?sort_by=company_id')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_mobile_pay_slip_list_and_detail_payloads_are_filterable_and_stable(): void
    {
        [$company, , $employee] = $this->actors();
        [, $validated] = $this->payrollSlip($company, $employee, ['status' => 'validated', 'net_salary' => 90000]);
        $this->payrollSlip($company, $employee, ['status' => 'sent', 'net_salary' => 110000]);
        $this->payrollSlip($company, $employee, ['status' => 'calculated', 'net_salary' => 120000]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/pay-slips?status=validated&sort_by=net_salary&sort_dir=asc&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $validated->id)
            ->assertJsonPath('data.0.status', 'validated')
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/me/pay-slips/{$validated->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'payroll_run_id',
                    'employee_id',
                    'period_start',
                    'period_end',
                    'gross_salary',
                    'total_deductions',
                    'net_salary',
                    'status',
                    'lines' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'amount',
                            'order',
                        ],
                    ],
                ],
            ]);

        $this->getJson('/api/v1/me/pay-slips?status=calculated')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_notifications_index_supports_mobile_filters_and_payload_shape(): void
    {
        [$company, , $employee] = $this->actors();

        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'absence',
            'title' => 'Absence approved',
            'body' => 'Your absence was approved.',
            'data' => ['absence_id' => 10],
            'is_read' => false,
            'created_at' => Carbon::parse('2026-05-10 10:00:00', 'UTC'),
        ]);
        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'payroll',
            'title' => 'Payslip ready',
            'body' => 'Your payslip is ready.',
            'is_read' => true,
            'read_at' => Carbon::parse('2026-05-11 10:00:00', 'UTC'),
            'created_at' => Carbon::parse('2026-05-11 10:00:00', 'UTC'),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/notifications?unread=1&type=absence&sort_dir=asc&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'absence')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'company_id',
                        'employee_id',
                        'type',
                        'title',
                        'body',
                        'data',
                        'is_read',
                        'read_at',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'unread_count',
                ],
            ]);

        $this->getJson('/api/v1/notifications?type=unknown')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $manager, $employee];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee, array $overrides = []): array
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => $overrides['net_salary'] ?? 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => $overrides['status'] ?? 'validated',
        ]);

        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'name' => 'Base salary',
            'type' => 'earning',
            'base_amount' => 120000,
            'rate' => 1,
            'amount' => 120000,
            'order' => 1,
        ]);

        return [$run, $slip];
    }
}
