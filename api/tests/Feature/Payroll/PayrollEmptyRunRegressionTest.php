<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\PayrollEmptyRunException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Régression #1767 — La paie « calculate » avec 0 structure salariale
 * réussissait en silence avec 0 bulletins, puis validate/lock étaient
 * acceptés à vide (risque de clôture comptable erronée en production).
 *
 * Comportement attendu désormais :
 *  - calculate avec 0 structure salariale → 422 + message explicite,
 *    le run reste en draft et aucun bulletin n'est créé/détruit ;
 *  - calculate sans employé actif → 422 ;
 *  - validate / lock sur un run à 0 bulletins → 422 ;
 *  - le parcours nominal (structure présente) fonctionne toujours.
 */
class PayrollEmptyRunRegressionTest extends TestCase
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
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;

        Sanctum::actingAs($this->manager);
    }

    private function createRun(string $status = 'draft'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'country_code' => 'DZ',
            'status' => $status,
        ]);

        return $run;
    }

    private function createEmployee(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_base' => 60000,
        ]);

        return $employee;
    }

    public function test_calculate_with_no_salary_structure_returns_422_with_message(): void
    {
        $this->createEmployee();
        $run = $this->createRun();

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Aucun bulletin généré'));

        // Le run reste en draft — pas de transition silencieuse vers calculated.
        $this->assertSame('draft', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->paySlips()->count());
    }

    public function test_calculate_with_no_active_employees_returns_422(): void
    {
        $run = $this->createRun();

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertUnprocessable();

        $this->assertSame('draft', $run->fresh()->status);
    }

    public function test_calculate_throws_payroll_empty_run_exception_from_service(): void
    {
        $this->createEmployee();
        $run = $this->createRun();

        $this->expectException(PayrollEmptyRunException::class);

        (new PayrollCalculator)->calculateRun($run);
    }

    public function test_validate_on_run_without_slips_returns_422(): void
    {
        $run = $this->createRun('calculated');

        $this->postJson("/api/v1/payroll-runs/{$run->id}/validate")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'aucun bulletin'));

        $this->assertSame('calculated', $run->fresh()->status);
    }

    public function test_lock_on_run_without_slips_returns_422(): void
    {
        $run = $this->createRun('validated');

        $this->postJson("/api/v1/payroll-runs/{$run->id}/lock")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'aucun bulletin'));

        $this->assertSame('validated', $run->fresh()->status);
    }

    public function test_happy_path_with_salary_structure_still_works(): void
    {
        $this->createEmployee();
        $run = $this->createRun();

        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille DZ (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertOk()
            ->assertJsonPath('data.status', 'calculated')
            // Manager (salaire) + employé créé = 2 bulletins.
            ->assertJsonPath('data.employee_count', 2);

        $this->assertSame(2, $run->fresh()->paySlips()->count());
    }
}
