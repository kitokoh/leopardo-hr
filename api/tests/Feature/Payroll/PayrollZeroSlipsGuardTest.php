<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1767 — Un run de paie sans structure salariale ne doit plus
 * « réussir » en silence à 0 bulletin (puis être validé/verrouillé à vide,
 * clôture comptable à zéro sans avertissement).
 */
class PayrollZeroSlipsGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        Employee::factory()->create(['company_id' => $company->id]);
    }

    private function makeRun(string $status): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => $status,
        ]);

        return $run;
    }

    public function test_calculate_returns_422_when_no_salary_structure(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);

        // Avant le correctif : 200, status calculated, employee_count 0.
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'structure'));

        // Le run repart en draft : l'utilisateur peut corriger la config
        // (structures salariales) et retenter.
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->fresh()?->status);
    }

    public function test_validate_returns_422_when_run_has_zero_slips(): void
    {
        Sanctum::actingAs($this->manager);

        // Run « calculé » à vide (état atteignable avant le correctif).
        $run = $this->makeRun(PayrollRun::STATUS_CALCULATED);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'aucun bulletin'));

        $this->assertSame(PayrollRun::STATUS_CALCULATED, $run->fresh()?->status);
    }

    public function test_lock_returns_422_when_run_has_zero_slips(): void
    {
        Sanctum::actingAs($this->manager);

        // Run « validé » à vide (état atteignable avant le correctif).
        $run = $this->makeRun(PayrollRun::STATUS_VALIDATED);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'aucun bulletin'));
    }
}
