<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\EstimationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EstimationServiceTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('hr_model_templates');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_daily_summary_from_log_returns_absent_defaults(): void
    {
        [$company, $employee] = $this->seedEmployee([
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);
        app()->instance('current_company', $company);

        $summary = app(EstimationService::class)->dailySummaryFromLog($employee, null, '2026-04-07');

        $this->assertSame('absent', $summary['status']);
        $this->assertSame(0.0, $summary['total_estimated']);
        $this->assertSame('DZD', $summary['currency']);
    }

    public function test_quick_estimate_uses_schedule_work_days(): void
    {
        [$company, $employee] = $this->seedEmployee([
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ], [
            'work_days' => [1, 2, 3, 4],
        ]);
        app()->instance('current_company', $company);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => '2026-04-06',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-06 09:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-06 17:00:00', 'UTC'),
            'hours_worked' => 8,
            'overtime_hours' => 0,
            'method' => 'mobile',
            'status' => 'ontime',
        ]);

        $estimate = app(EstimationService::class)->quickEstimate($employee, '2026-04-06', '2026-04-10');

        $this->assertSame(4, $estimate['period']['working_days']);
        $this->assertSame(1, $estimate['period']['days_present']);
        $this->assertSame(3, $estimate['period']['days_absent']);
        $this->assertSame(800.0, $estimate['totals']['gross']);
    }

    public function test_quick_estimate_uses_hr_template_deduction_rate_when_available(): void
    {
        [$company, $employee] = $this->seedEmployee([
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);
        app()->instance('current_company', $company);
        $this->createHrModelTemplatesTable();

        DB::table('hr_model_templates')->insert([
            'country_code' => 'DZ',
            'cotisations' => json_encode(['total_salarial' => 0.12], JSON_THROW_ON_ERROR),
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => '2026-04-06',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-06 09:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-06 18:00:00', 'UTC'),
            'hours_worked' => 9,
            'overtime_hours' => 1,
            'method' => 'mobile',
            'status' => 'ontime',
        ]);

        $estimate = app(EstimationService::class)->quickEstimate($employee, '2026-04-06', '2026-04-06');

        $this->assertSame(925.0, $estimate['totals']['gross']);
        $this->assertSame(111.0, $estimate['totals']['deductions']);
        $this->assertSame(814.0, $estimate['totals']['net']);
    }

    private function seedEmployee(array $employeeOverrides = [], array $scheduleOverrides = []): array
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

        $schedule = Schedule::query()->create(array_merge([
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
        ], $scheduleOverrides));

        $employee = Employee::query()->create(array_merge([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 17600,
            'hourly_rate' => 0,
        ], $employeeOverrides));

        return [$company, $employee];
    }

    private function createHrModelTemplatesTable(): void
    {
        if (Schema::hasTable('hr_model_templates')) {
            return;
        }

        Schema::create('hr_model_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2)->unique();
            $table->json('cotisations')->nullable();
            $table->timestamps();
        });
    }
}
