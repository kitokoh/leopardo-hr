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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);

        $this->step($company, 'first_report', 'pending');
        $this->step($company, 'company_info', 'pending', required: true);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/v1/onboarding-setup/first_report/skip')
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped');

        // #7268 — le 422 porte un code stable (`error`/`message`) + le message
        // traduit par le catalogue `errors.*` dans la langue résolue par
        // SetLocale. Le portail affiche `localized_message` en priorité : plus
        // aucun littéral anglais en dur n'est renvoyé.
        $refused = $this->patchJson('/api/v1/onboarding-setup/company_info/skip');

        $refused->assertStatus(422);
        $refused->assertJsonPath('error', 'ONBOARDING_STEP_REQUIRED');
        $refused->assertJsonPath('message', 'ONBOARDING_STEP_REQUIRED');
        $refused->assertJsonPath(
            'localized_message',
            'Cette étape est obligatoire et ne peut pas être ignorée.'
        );
    }

    public function test_required_step_refusal_is_localized_in_user_language(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'preferred_language' => 'ar',
        ]);
        $this->step($company, 'company_info', 'pending', required: true);

        Sanctum::actingAs($manager);

        // #7268 — même code stable, message réellement localisé (×4 langues).
        $this->patchJson('/api/v1/onboarding-setup/company_info/skip')
            ->assertStatus(422)
            ->assertJsonPath('error', 'ONBOARDING_STEP_REQUIRED')
            ->assertJsonPath('localized_message', 'هذه الخطوة إلزامية ولا يمكن تخطيها.');
    }

    public function test_company_cannot_complete_another_company_step(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $manager */
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
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/onboarding-setup/checklist');

        $response->assertOk();
        $response->assertJsonPath('data.company_created_at', $company->created_at?->toIso8601String());
        $this->assertIsInt($response->json('data.elapsed_since_company_creation_minutes'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.elapsed_since_company_creation_minutes'));
    }

    public function test_completing_step_logs_timing_for_pilot_onboarding(): void
    {
        // #5151 — chaque étape complétée produit un log structuré
        // onboarding.step_completed avec horodatage + minutes écoulées depuis
        // la création de la société (preuve « < 30 min » sans télémétrie).
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
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

    public function test_onboarding_completion_is_persisted_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        // #7261/#7300 — `first_employee` est gardée par un prédicat serveur :
        // `Employee::count() > 1` (le manager compte pour un). La fixture de ce
        // test ne créait que le manager, donc l'étape répondait 422 et le test
        // était rouge sur `main` sans rapport avec ce qu'il vérifie. On ajoute
        // le second employé pour satisfaire la garde, ce qui laisse le test
        // porter sur son vrai sujet : la persistance serveur de la complétion.
        Employee::factory()->create(['company_id' => $company->id]);

        $this->step($company, 'company_info', 'completed');
        $this->step($company, 'first_employee', 'pending');
        $this->step($company, 'first_report', 'pending');

        Sanctum::actingAs($manager);

        // #7262 — tant qu'une étape reste en attente, rien n'est persisté.
        $this->patchJson('/api/v1/onboarding-setup/first_employee/complete')->assertOk();
        $this->assertArrayNotHasKey('onboarding_completed', $this->freshCompanyMetadata($company));

        // Dernière étape terminée : le serveur devient la source de vérité.
        $this->patchJson('/api/v1/onboarding-setup/first_report/complete')->assertOk();

        $metadata = $this->freshCompanyMetadata($company);
        $this->assertTrue($metadata['onboarding_completed'] ?? false);
        $this->assertArrayHasKey('onboarding_completed_at', $metadata);

        // Idempotent : re-compléter une étape ne réécrit pas la date de fin.
        $completedAt = $metadata['onboarding_completed_at'];

        $this->patchJson('/api/v1/onboarding-setup/company_info/complete')->assertOk();

        $this->assertSame($completedAt, $this->freshCompanyMetadata($company)['onboarding_completed_at'] ?? null);
    }

    public function test_onboarding_completion_is_persisted_when_last_step_is_skipped(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->step($company, 'company_info', 'completed');
        $this->step($company, 'invite_manager', 'pending');

        Sanctum::actingAs($manager);

        // Les étapes ignorées comptent comme terminées (même définition que
        // `progress()` et le moteur calculé) : l'onboarding est donc fini.
        $this->patchJson('/api/v1/onboarding-setup/invite_manager/skip')->assertOk();

        $this->assertTrue($this->freshCompanyMetadata($company)['onboarding_completed'] ?? false);
    }

    /**
     * Relecture depuis la base : simule une autre session / un autre appareil,
     * qui ne partage ni le localStorage ni l'instance Eloquent courante.
     *
     * @return array<string, mixed>
     */
    private function freshCompanyMetadata(Company $company): array
    {
        /** @var Company|null $fresh */
        $fresh = Company::query()->whereKey($company->id)->first();
        $metadata = $fresh?->metadata;

        return is_array($metadata) ? $metadata : [];
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
