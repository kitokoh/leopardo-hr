<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\ClientEvent;
use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class LaunchReadinessControllerTest extends TestCase
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

    public function test_principal_manager_can_view_go_live_readiness(): void
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
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'salary_base' => 250000,
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 120000,
        ]);

        foreach ([$manager, $employee] as $person) {
            NotificationPreference::query()->create([
                'company_id' => $company->id,
                'employee_id' => $person->id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'categories' => ['hr' => true],
            ]);
        }

        ClientEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'event_name' => 'dashboard_loaded',
            'surface' => 'web',
            'occurred_at' => now(),
        ]);
        AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Accueil',
            'device_code' => 'KIOSK-001',
            'status' => 'active',
            'biometric_mode' => 'fingerprint',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/launch-readiness')
            ->assertOk()
            ->assertJsonPath('data.go_live_ready', true)
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.required_blockers', [])
            ->assertJsonPath('data.checks.0.key', 'company_profile');
    }

    public function test_readiness_reports_required_blockers(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create([
            'company_id' => $company->id,
            'salary_base' => 0,
        ]);

        CommunicationEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'email',
            'status' => 'failed',
            'provider' => 'mail',
            'template_key' => 'generic',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/launch-readiness')
            ->assertOk()
            ->assertJsonPath('data.go_live_ready', false)
            ->assertJsonFragment([
                'key' => 'employee_base',
                'label' => 'Base collaborateurs exploitable',
            ])
            ->assertJsonFragment([
                'key' => 'communication_governance',
                'label' => 'Preferences et audit communication prets',
            ]);
    }

    public function test_employee_cannot_view_launch_readiness(): void
    {
        Sanctum::actingAs(Employee::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => 'employee',
        ]));

        $this->getJson('/api/v1/launch-readiness')
            ->assertForbidden();
    }
}
