<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\OnboardingStep;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3430 — écritures d'état company-level de l'onboarding réservées
 * aux managers : un employé simple ne peut pas marquer complete/skip les
 * étapes de configuration de l'entreprise (falsification du progrès).
 */
class OnboardingStepWriteGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $manager;

    protected Employee $employee;

    protected OnboardingStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->step = OnboardingStep::create([
            'company_id' => $this->company->id,
            'step_key' => 'configure_payroll',
            'title' => 'Configurer la paie',
            'order' => 8,
            'required' => false,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function plain_employee_cannot_complete_company_step(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->patchJson("/api/v1/onboarding-setup/{$this->step->step_key}/complete");

        $response->assertForbidden();
        $this->assertSame('pending', $this->step->fresh()->status);
    }

    /** @test */
    public function plain_employee_cannot_skip_company_step(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->patchJson("/api/v1/onboarding-setup/{$this->step->step_key}/skip");

        $response->assertForbidden();
        $this->assertSame('pending', $this->step->fresh()->status);
    }

    /** @test */
    public function manager_can_complete_company_step(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/v1/onboarding-setup/{$this->step->step_key}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertSame('completed', $this->step->fresh()->status);
    }

    /** @test */
    public function employee_can_still_read_checklist_and_progress(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/onboarding-setup/checklist')
            ->assertOk();

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/onboarding-setup/progress')
            ->assertOk()
            ->assertJsonPath('data.progress', 0);
    }
}
