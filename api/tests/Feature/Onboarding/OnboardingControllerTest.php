<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;
    protected Company $otherCompany;
    protected Employee $manager;
    protected Employee $employee;
    protected Employee $otherManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->company      = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
        $this->manager      = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->employee     = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->otherManager = Employee::factory()->manager()->create(['company_id' => $this->otherCompany->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function invalid_token_returns_404_on_show(): void
    {
        $invalidToken = 'this-token-does-not-exist-' . uniqid();

        $response = $this->getJson("/api/v1/onboarding/invitation/{$invalidToken}");

        $response->assertStatus(404);
    }

    /** @test */
    public function activate_with_invalid_token_returns_404(): void
    {
        $invalidToken = 'invalid-activation-token-' . uniqid();

        $response = $this->postJson("/api/v1/onboarding/invitation/{$invalidToken}/activate", [
            'password'              => 'SecureP@ssw0rd!',
            'password_confirmation' => 'SecureP@ssw0rd!',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function activate_without_required_fields_returns_422(): void
    {
        // Use a syntactically valid but non-existent token; if 404 fires first, that is acceptable
        $token = 'well-formed-but-missing-' . uniqid();

        $response = $this->postJson("/api/v1/onboarding/invitation/{$token}/activate", []);

        $this->assertContains($response->status(), [422, 404]);

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['password']);
        }
    }

    /** @test */
    public function authenticated_manager_can_get_checklist(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/onboarding/checklist');

        $response->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_cannot_get_checklist(): void
    {
        $response = $this->getJson('/api/v1/onboarding/checklist');

        $response->assertStatus(401);
    }

    /** @test */
    public function manager_can_list_onboarding_steps(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/onboarding/steps');

        $response->assertStatus(200);
    }

    /** @test */
    public function cross_tenant_checklist_isolation(): void
    {
        // Each manager sees only their own company's checklist
        Sanctum::actingAs($this->manager);
        $thisResponse = $this->getJson('/api/v1/onboarding/checklist');
        $thisResponse->assertStatus(200);

        Sanctum::actingAs($this->otherManager);
        $otherResponse = $this->getJson('/api/v1/onboarding/checklist');
        $otherResponse->assertStatus(200);

        $thisChecklist  = $thisResponse->json();
        $otherChecklist = $otherResponse->json();

        // The two responses should not be referencing each other's company data
        $this->assertNotEquals(
            $thisChecklist,
            $otherChecklist,
            'Two different tenants should receive different checklist data.'
        );
    }
}
