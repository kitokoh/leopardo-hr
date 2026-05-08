<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
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

    public function test_manager_can_view_client_onboarding_checklist(): void
    {
        $company = Company::factory()->create([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);
        Employee::factory()->create([
            'company_id' => $company->id,
            'biometric_fingerprint_enabled' => true,
        ]);
        AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Entree principale',
            'device_code' => 'KIOSK-01',
            'status' => 'active',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding/checklist');

        $response->assertOk();
        $response->assertJsonPath('data.total_steps', 8);
        $response->assertJsonPath('data.steps.0.key', 'company_created');
        $response->assertJsonPath('data.go_live_ready', true);
        $this->assertGreaterThanOrEqual(90, $response->json('data.progress_percent'));
    }

    public function test_employee_cannot_view_client_onboarding_checklist(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/onboarding/checklist')->assertStatus(403);
    }
}
