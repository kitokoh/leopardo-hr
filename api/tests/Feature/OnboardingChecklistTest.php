<?php

namespace Tests\Feature;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
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
        // #7300 — la progression exposée en tête de réponse est désormais la
        // progression CANONIQUE (checklist setup). On amorce les étapes pour
        // que le test porte sur un tenant réellement configuré.
        app(SeedDefaultSteps::class)->execute($company->id);
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
        // Progression canonique (source de vérité = /onboarding-setup/checklist)
        $response->assertJsonPath('data.total_steps', 10);
        $response->assertJsonPath('data.go_live_ready', false);
        $response->assertJsonPath('data.deprecated', true);
        $response->assertJsonPath('data.canonical_source', '/onboarding-setup/checklist');
        // …et l'observation serveur reste disponible, sous un nom distinct.
        $response->assertJsonPath('data.observed.total_steps', 8);
        $response->assertJsonPath('data.steps.0.key', 'company_created');
        $this->assertGreaterThanOrEqual(90, $response->json('data.observed.progress_percent'));
    }

    public function test_employee_can_view_client_onboarding_checklist(): void
    {
        // #3239 — un employé non-manager (role `employee`) doit pouvoir lire
        // sa propre checklist : plus de 403 (l'ancien authorize viewAny était
        // réservé aux managers). La lecture n'expose que les données de sa
        // société (scopées par le middleware tenant).
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        app()->instance('current_company', $company);
        app(SeedDefaultSteps::class)->execute($company->id);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/onboarding/checklist');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                // Progression canonique (#7300)
                'completed_steps',
                'total_steps',
                'progress_percent',
                'progress',
                'go_live_ready',
                'next_actions' => [
                    ['key', 'label'],
                ],
                'deprecated',
                'canonical_source',
                // Observation serveur, explicitement distincte
                'observed' => [
                    'completed_steps',
                    'total_steps',
                    'progress_percent',
                ],
                'steps',
            ],
        ]);
        $response->assertJsonPath('data.total_steps', 10);
        $response->assertJsonPath('data.observed.total_steps', 8);
        $this->assertCount(8, $response->json('data.steps'));
        $this->assertIsInt($response->json('data.completed_steps'));
    }
}

