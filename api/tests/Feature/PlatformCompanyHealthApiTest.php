<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyHealthApiTest extends TestCase
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

    public function test_super_admin_can_view_company_health_and_adoption_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00', 'UTC'));

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Pro',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'features' => ['rh' => true, 'finance' => true],
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'salary_base' => 173330]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'salary_base' => 120000]);

        app()->instance('current_company', $company);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-07',
            'check_in' => Carbon::parse('2026-05-07 08:20:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-07 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-08',
            'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson("/api/v1/platform/companies/{$company->id}/health");

        $response->assertOk();
        $response->assertJsonPath('data.company.id', $company->id);
        $response->assertJsonPath('data.plan.name', 'Pro');
        $response->assertJsonPath('data.subscription.mrr', 99);
        $response->assertJsonPath('data.features.active.finance', true);
        $response->assertJsonPath('data.adoption.risk_level', 'low');
        $response->assertJsonPath('data.adoption.employees.total', 2);
        $response->assertJsonPath('data.adoption.employees.payroll_ready', 2);
        $response->assertJsonPath('data.adoption.attendance.logs_30d', 2);
        $response->assertJsonPath('data.adoption.attendance.active_employees_30d', 2);
        $response->assertJsonPath('data.adoption.onboarding.progress_percent', 100);
        $response->assertJsonPath('data.adoption.anomalies.total_30d', 1);
        $response->assertJsonPath('data.adoption.anomalies.business_impact.late_minutes', 20);
        $response->assertJsonPath('data.next_actions.0.key', 'prepare_upsell');

        Carbon::setTestNow();
    }

    public function test_company_health_flags_high_risk_when_subscription_is_not_active(): void
    {
        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $company = Company::factory()->suspended()->create([
            'plan_id' => 1,
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson("/api/v1/platform/companies/{$company->id}/health");

        $response->assertOk();
        $response->assertJsonPath('data.adoption.risk_level', 'high');
        $response->assertJsonPath('data.next_actions.0.key', 'reactivate_subscription');
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }
}
