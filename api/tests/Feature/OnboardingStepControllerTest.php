<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OnboardingStepControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->ensureOnboardingStepsTable();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_checklist_auto_seeds_default_steps_for_company(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding-setup/checklist');

        // #3239 — shape canonique unifiée : data{ completed_steps,
        // total_steps, progress_percent, go_live_ready, next_actions, steps }.
        $response->assertOk();
        $response->assertJsonCount(10, 'data.steps');
        $response->assertJsonPath('data.total_steps', 10);
        $response->assertJsonPath('data.completed_steps', 0);
        $response->assertJsonPath('data.progress_percent', 0);
        $response->assertJsonPath('data.progress', 0);
        $response->assertJsonPath('data.go_live_ready', false);
        $response->assertJsonPath('data.next_actions.0.key', 'company_info');
        $response->assertJsonPath('data.steps.0.step_key', 'company_info');
        $response->assertJsonPath('data.steps.0.required', true);
        $this->assertSame(10, OnboardingStep::where('company_id', $company->id)->count());
    }

    public function test_progress_counts_completed_and_skipped_steps(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->step($company, 'company_info', 'completed', required: true);
        $this->step($company, 'first_report', 'skipped');
        $this->step($company, 'configure_payroll', 'pending');
        $this->step($company, 'install_kiosk', 'pending');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding-setup/progress');

        $response->assertOk();
        $response->assertJsonPath('data.completed', 2);
        $response->assertJsonPath('data.total', 4);
        $response->assertJsonPath('data.progress', 50);
        // #3239 — alias canonique progress_percent exposé par les deux moteurs.
        $response->assertJsonPath('data.progress_percent', 50);
    }

    public function test_employee_can_access_own_onboarding_without_manager_role(): void
    {
        // T118 (QA 2026-08-15) : les routes onboarding-setup vivent dans le
        // groupe authentifié du tenant (auth:sanctum + tenant) — un employé
        // non-manager peut LIRE sa checklist (plus de 403 api.manager).
        // Les écritures d'état company-level (complete) restent réservées
        // aux managers (#3430 — un employé ne peut pas falsifier le progrès
        // d'onboarding de l'entreprise) : PATCH → 403 pour un simple employé.
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $this->step($company, 'company_info', 'pending', required: true);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/onboarding-setup/checklist')->assertOk();
        $this->getJson('/api/v1/onboarding-setup/progress')->assertOk();

        $this->patchJson('/api/v1/onboarding-setup/company_info/complete')
            ->assertForbidden();
    }

    public function test_manager_can_complete_own_company_step(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->step($company, 'company_info', 'pending', required: true);

        Sanctum::actingAs($manager);

        $response = $this->patchJson('/api/v1/onboarding-setup/company_info/complete');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.completed_by', $manager->id);
        $this->assertNotNull(OnboardingStep::where('company_id', $company->id)->first()?->completed_at);
    }

    public function test_manager_can_skip_optional_step_but_not_required_step(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->step($company, 'first_report', 'pending');
        $this->step($company, 'company_info', 'pending', required: true);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/onboarding-setup/first_report/skip')
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped');

        $this->patchJson('/api/v1/onboarding-setup/company_info/skip')
            ->assertStatus(422)
            ->assertJsonPath('message', 'This step is required and cannot be skipped.');
    }

    public function test_company_cannot_complete_another_company_step(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $otherStep = $this->step($otherCompany, 'company_info', 'pending', required: true);

        Sanctum::actingAs($manager);

        // #4929 : seed paresseux — la société du manager n'a aucune étape, le
        // PATCH la seede puis complète SA PROPRE étape (200) ; l'étape de
        // l'AUTRE société reste intouchée (l'isolation tenant est préservée :
        // la requête est scopée sur company_id du manager).
        $this->patchJson('/api/v1/onboarding-setup/company_info/complete')
            ->assertOk();
        $this->assertSame('pending', $otherStep->fresh()->status);
    }

    public function test_checklist_exposes_timing_fields_for_pilot_onboarding(): void
    {
        // #5151 — instrumentation légère (pas d'outil externe) : la checklist
        // expose l'horodatage du parcours pilote (création société + minutes
        // écoulées) pour mesurer l'objectif « onboarding < 30 min ».
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding-setup/checklist');

        $response->assertOk();
        $response->assertJsonPath('data.company_created_at', $company->created_at->toIso8601String());
        $this->assertIsInt($response->json('data.elapsed_since_company_creation_minutes'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.elapsed_since_company_creation_minutes'));
    }

    public function test_completing_step_logs_timing_for_pilot_onboarding(): void
    {
        // #5151 — chaque étape complétée produit un log structuré
        // onboarding.step_completed avec horodatage + minutes écoulées depuis
        // la création de la société (preuve « < 30 min » sans télémétrie).
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->step($company, 'company_info', 'pending', required: true);

        // Log::spy() remplace le LogManager par un mock où
        // Log::channel('structured')->info(...) (middleware StructuredLogging)
        // renvoie null → 500. On capture le message via Log::listen sur le
        // VRAI manager (issue #5201).
        $captured = [];
        Log::listen(function ($message) use (&$captured): void {
            if (($message->message ?? null) === 'onboarding.step_completed') {
                $captured[] = (array) ($message->context ?? []);
            }
        });

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/onboarding-setup/company_info/complete')->assertOk();

        $this->assertNotEmpty($captured, 'le log onboarding.step_completed doit être émis');
        $this->assertSame('company_info', $captured[0]['step_key'] ?? null);
        $this->assertSame($company->id, $captured[0]['company_id'] ?? null);
        $this->assertArrayHasKey('elapsed_minutes_since_company_creation', $captured[0]);
    }

    private function step(
        Company $company,
        string $key,
        string $status,
        bool $required = false,
    ): OnboardingStep {
        return OnboardingStep::create([
            'company_id' => $company->id,
            'step_key' => $key,
            'title' => str_replace('_', ' ', $key),
            'description' => null,
            'status' => $status,
            'order' => OnboardingStep::where('company_id', $company->id)->count() + 1,
            'required' => $required,
            'metadata' => [],
        ]);
    }

    private function ensureOnboardingStepsTable(): void
    {
        if (Schema::hasTable('onboarding_steps')) {
            return;
        }

        Schema::create('onboarding_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('step_key', 100);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('required')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'step_key']);
        });
    }
}
