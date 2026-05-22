<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\ClientEvent;
use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ProfileFunctionalReadinessTest extends TestCase
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

    public function test_sensitive_api_capabilities_are_limited_to_principal_and_hr_profiles(): void
    {
        [$company, $profiles] = $this->makeProfileMatrix();
        $this->seedReadinessSignals($company, $profiles);

        foreach (['principal', 'rh'] as $role) {
            Sanctum::actingAs($profiles[$role]);

            $this->getJson('/api/v1/auth/me')->assertOk();
            $this->getJson('/api/v1/launch-readiness')->assertOk();
            $this->getJson('/api/v1/communication/analytics')->assertOk();
        }

        foreach (['dept', 'comptable', 'superviseur', 'employee'] as $role) {
            Sanctum::actingAs($profiles[$role]);

            $this->getJson('/api/v1/auth/me')->assertOk();
            $this->getJson('/api/v1/launch-readiness')->assertForbidden();
            $this->getJson('/api/v1/communication/analytics')->assertForbidden();
        }
    }

    public function test_web_profile_access_matrix_matches_operational_roles(): void
    {
        [, $profiles] = $this->makeProfileMatrix();

        foreach (['principal', 'rh', 'dept', 'comptable', 'superviseur'] as $role) {
            $this->actingAs($profiles[$role], 'web')
                ->get('/dashboard')
                ->assertOk();
        }

        $this->actingAs($profiles['employee'], 'web')
            ->get('/dashboard')
            ->assertForbidden();

        foreach (['principal', 'rh'] as $role) {
            $this->actingAs($profiles[$role], 'web')
                ->get('/employees/create')
                ->assertOk();
        }

        foreach (['dept', 'comptable', 'superviseur', 'employee'] as $role) {
            $this->actingAs($profiles[$role], 'web')
                ->get('/employees/create')
                ->assertForbidden();
        }

        foreach (['principal', 'superviseur'] as $role) {
            $this->actingAs($profiles[$role], 'web')
                ->get('/biometrics')
                ->assertOk();
        }

        foreach (['rh', 'dept', 'comptable', 'employee'] as $role) {
            $this->actingAs($profiles[$role], 'web')
                ->get('/biometrics')
                ->assertForbidden();
        }

        $this->actingAs($profiles['employee'], 'web')
            ->get('/me')
            ->assertOk();
    }

    /**
     * @return array{0: Company, 1: array<string, Employee>}
     */
    private function makeProfileMatrix(): array
    {
        $company = Company::factory()->create([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.75,
                    'lng' => 3.04,
                    'radius_meters' => 150,
                ],
            ],
        ]);

        return [
            $company,
            [
                'principal' => $this->employee($company, 'principal@test.local', 'manager', 'principal'),
                'rh' => $this->employee($company, 'rh@test.local', 'manager', 'rh'),
                'dept' => $this->employee($company, 'dept@test.local', 'manager', 'dept'),
                'comptable' => $this->employee($company, 'comptable@test.local', 'manager', 'comptable'),
                'superviseur' => $this->employee($company, 'superviseur@test.local', 'manager', 'superviseur'),
                'employee' => $this->employee($company, 'employee@test.local', 'employee', null),
            ],
        ];
    }

    private function employee(Company $company, string $email, string $role, ?string $managerRole): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => ucfirst(strtok($email, '@') ?: 'Demo'),
            'last_name' => 'Profile',
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 100000,
            'leave_balance' => 12,
        ]);
    }

    /**
     * @param  array<string, Employee>  $profiles
     */
    private function seedReadinessSignals(Company $company, array $profiles): void
    {
        foreach ($profiles as $profile) {
            NotificationPreference::query()->create([
                'company_id' => $company->id,
                'employee_id' => $profile->id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'categories' => ['hr' => true, 'payroll' => true],
            ]);
        }

        CommunicationEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $profiles['rh']->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'email',
            'status' => 'sent',
            'provider' => 'demo',
            'template_key' => 'welcome_employee',
            'occurred_at' => now(),
        ]);

        ClientEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $profiles['principal']->id,
            'event_name' => 'launch_readiness_viewed',
            'surface' => 'web',
            'occurred_at' => now(),
        ]);

        AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Kiosk demo',
            'device_code' => 'PLAN21-KIOSK',
            'status' => 'active',
            'biometric_mode' => 'fingerprint',
        ]);
    }
}
