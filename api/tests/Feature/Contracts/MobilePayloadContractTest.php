<?php

namespace Tests\Feature\Contracts;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MobilePayloadContractTest extends TestCase
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

    public function test_auth_me_payload_matches_mobile_contract(): void
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
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-NORA',
            'first_name' => 'Nora',
            'last_name' => 'Ait',
            'email' => 'nora@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'matricule',
                'company_id',
                'first_name',
                'last_name',
                'email',
                'role',
                'status',
                'photo_url',
                'hire_date',
                'language',
                'is_rtl',
                'features',
                'mobile_experience' => [
                    'stage',
                    'modules' => [
                        '*' => [
                            'key',
                            'title',
                            'description',
                            'domain',
                            'route',
                            'status',
                        ],
                    ],
                    'quick_actions' => [
                        '*' => [
                            'key',
                            'title',
                            'description',
                            'domain',
                            'icon',
                            'route',
                        ],
                    ],
                ],
                'company' => [
                    'id',
                    'name',
                    'language',
                    'timezone',
                    'currency',
                ],
            ],
        ]);
        $response->assertJsonPath('data.email', 'nora@company.test');
        $response->assertJsonPath('data.matricule', 'EMP-NORA');
        $response->assertJsonPath('data.role', 'employee');
        $response->assertJsonPath('data.company.name', 'Company A');
        $response->assertJsonPath('data.features.rh', true);
        $response->assertJsonPath('data.mobile_experience.stage', 'regular');
        $modules = $response->json('data.mobile_experience.modules');
        $quickActions = $response->json('data.mobile_experience.quick_actions');

        $this->assertIsArray($modules);
        $this->assertIsArray($quickActions);
        $this->assertContains('attendance', array_column($modules, 'key'));
        $this->assertContains('finance', array_column($modules, 'key'));
        $this->assertContains('settings', array_column($quickActions, 'key'));
    }

    public function test_me_daily_summary_payload_matches_mobile_contract(): void
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-ME',
            'first_name' => 'Ahmed',
            'last_name' => 'B.',
            'email' => 'ahmed@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 100,
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-10 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-10 17:00:00', 'UTC'),
            'hours_worked' => 9.00,
            'overtime_hours' => 1.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/daily-summary?date=2026-04-10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'employee_id',
                'matricule',
                'name',
                'checked_in',
                'check_in_time',
                'check_out_time',
                'hours_worked',
                'overtime_hours',
                'status',
                'late_minutes',
                'base_gain',
                'overtime_gain',
                'total_estimated',
                'currency',
            ],
        ]);
        $response->assertJsonPath('data.matricule', 'EMP-ME');
        $this->assertSame(9.0, (float) $response->json('data.hours_worked'));
        $this->assertSame(1.0, (float) $response->json('data.overtime_hours'));
        $this->assertSame(100 * 8 + 100 * 1 * 1.25, (float) $response->json('data.total_estimated'));
    }

    public function test_attendance_today_collection_payload_matches_mobile_contract(): void
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
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-18',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-18 08:00:00', 'UTC'),
            'check_out' => null,
            'hours_worked' => 0,
            'overtime_hours' => 0,
            'status' => 'incomplete',
        ]);

        Sanctum::actingAs($manager);
        $this->travelTo(Carbon::parse('2026-04-18 09:00:00', 'UTC'));

        $response = $this->getJson('/api/v1/attendance/today');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'collection');
        $response->assertJsonStructure([
            'data' => [
                'mode',
                'items' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'matricule',
                        'name',
                        'checked_in',
                        'check_in_time',
                        'check_out_time',
                        'hours_worked',
                        'overtime_hours',
                        'status',
                        'late_minutes',
                        'base_gain',
                        'overtime_gain',
                        'total_estimated',
                        'currency',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ],
        ]);
    }

    public function test_employees_index_payload_matches_mobile_contract(): void
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
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'matricule',
                    'company_id',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'status',
                    'photo_url',
                    'hire_date',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_attendance_history_payload_matches_mobile_contract(): void
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
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-H',
            'first_name' => 'Hassen',
            'last_name' => 'B.',
            'email' => 'hassen@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-15',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-15 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-15 17:00:00', 'UTC'),
            'hours_worked' => 9.00,
            'overtime_hours' => 1.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/attendance?date_from=2026-04-01&date_to=2026-04-30');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'employee_id',
                    'employee' => [
                        'id',
                        'name',
                        'matricule',
                        'photo_url',
                    ],
                    'date',
                    'check_in',
                    'check_out',
                    'hours_worked',
                    'overtime_hours',
                    'status',
                    'late_minutes',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
    }
}
