<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OnboardingStep;
use Illuminate\Database\Schema\Blueprint;
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

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('data.0.step_key', 'company_info');
        $response->assertJsonPath('data.0.required', true);
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

        $this->patchJson('/api/v1/onboarding-setup/company_info/complete')
            ->assertNotFound();
        $this->assertSame('pending', $otherStep->fresh()->status);
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
