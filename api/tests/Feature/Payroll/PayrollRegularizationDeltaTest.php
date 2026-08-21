<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1983 — moteur de calcul du DELTA de régularisation.
 *
 * Un run de régularisation (type=regularization) ne recalcule PAS des
 * bulletins complets : il produit un DIFFÉRENTIEL (corrigé − original) par
 * employé affecté, en référence au run original verrouillé (original_slip_id).
 * Périmètre = employés ayant un bulletin dans l'original (départs couverts,
 * embauchés après la période exclus) ; delta nul → pas de bulletin ; recalcul
 * idempotent ; totaux du run = somme des deltas.
 */
class PayrollRegularizationDeltaTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeLockedOriginalRun(Company $company, float $baseSalary = 60000): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => $baseSalary,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => $baseSalary,
            // #1983 : prorata contrat déterministe — un contract_start aléatoire
            // (factory) tombant dans la période du run déclenchait le prorata et
            // rendait le delta non-déterministe (13 545,46 au lieu de 20 000).
            'contract_start' => '2025-01-01',
            'status' => 'active',
        ]);

        (new PayrollCalculator)->calculateRun($run);
        $run = $run->refresh();

        /** @var Employee $rh */
        $rh = Employee::factory()->manager()->create(['company_id' => $company->id]);
        (new PayrollClosingService)->validateRh($run, $rh);
        $run->paySlips()->update(['status' => 'validated']);

        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return (new PayrollClosingService)->lock($run->refresh(), $comptable);
    }

    private function regularize(PayrollRun $originalRun, Employee $actor, string $reason = 'Augmentation rétroactive'): PayrollRun
    {
        return (new PayrollRegularizationService)->createRegularization($originalRun, $actor, $reason);
    }

    /**
     * Cas central : augmentation rétroactive de la structure salariale
     * (60 000 → 80 000 DZD). Calcul manuel :
     *   Original (60 000) : CNAS 9 % = 5 400 · assiette 54 600 · IRG 7 042 ·
     *     net 47 558 · patronal 15 600 · coût 75 600
     *   Corrigé (80 000) : CNAS = 7 200 · assiette 72 800 ·
     *     IRG : 20 000×23 % + 32 800×27 % = 13 456 → ×12 = 161 472,
     *     abattement 18 000 → (161 472 − 18 000)/12 = 11 956 ·
     *     net 60 844 · patronal 20 800 · coût 100 800
     *   Delta : brut +20 000 · retenues +6 714 · net +13 286 · coût +25 200
     */
    public function test_regularization_run_produces_delta_not_full_slips(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $originalRun = $this->makeLockedOriginalRun($company, 60000);

        // Correction rétroactive : le salaire de base de la grille change.
        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::query()->where('company_id', $company->id)->firstOrFail();
        $structure->update(['base_salary' => 80000]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $regRun = $this->regularize($originalRun, $manager);

        (new PayrollCalculator)->calculateRun($regRun);
        $regRun = $regRun->refresh();

        // Un SEUL bulletin, différentiel — pas un doublon complet par employé.
        $this->assertSame(1, $regRun->paySlips()->count());

        /** @var PaySlip $deltaSlip */
        $deltaSlip = $regRun->paySlips()->first();
        $this->assertEquals(20000.0, (float) $deltaSlip->gross_salary);
        $this->assertEquals(6714.0, (float) $deltaSlip->total_deductions);
        $this->assertEquals(13286.0, (float) $deltaSlip->net_salary);
        $this->assertEquals(5200.0, (float) $deltaSlip->employer_contributions);
        $this->assertEquals(25200.0, (float) $deltaSlip->total_cost);

        // Référence au bulletin original (audit + PDF « corrige le bulletin #N »).
        /** @var PaySlip $originalSlip */
        $originalSlip = $originalRun->paySlips()->first();
        $this->assertSame($originalSlip->id, $deltaSlip->original_slip_id);

        // Lignes delta : salaire de base +20 000, cotisations +1 800, impôt +4 914.
        $linesByName = $deltaSlip->lines->keyBy('name');
        $this->assertEquals(20000.0, (float) $linesByName->get('Salaire de base')?->amount);
        $this->assertEquals(1800.0, (float) $linesByName->get('Cotisations salariales')?->amount);
        $this->assertEquals(4914.0, (float) $linesByName->get('Impot sur le revenu')?->amount);

        // Totaux du run = sommes des deltas.
        $this->assertEquals(20000.0, (float) $regRun->total_gross);
        $this->assertEquals(13286.0, (float) $regRun->total_net);
        $this->assertEquals(25200.0, (float) $regRun->total_employer_cost);
        $this->assertSame(1, $regRun->employee_count);
    }

    public function test_unchanged_employee_gets_no_regularization_slip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $originalRun = $this->makeLockedOriginalRun($company, 60000);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $regRun = $this->regularize($originalRun, $manager);

        (new PayrollCalculator)->calculateRun($regRun);

        // Aucun changement → aucun bulletin de régularisation.
        $this->assertSame(0, $regRun->refresh()->paySlips()->count());
        $this->assertEquals(0.0, (float) $regRun->refresh()->total_net);
    }

    public function test_leaver_with_original_slip_is_included_in_delta(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $originalRun = $this->makeLockedOriginalRun($company, 60000);

        // Le salarié a quitté l'entreprise APRÈS la période d'origine — il a un
        // bulletin original, la régularisation doit l'ajuster quand même.
        /** @var Employee $leaver */
        $leaver = Employee::query()->where('company_id', $company->id)->where('role', 'employee')->firstOrFail();
        $leaver->forceFill(['status' => 'archived'])->save();

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::query()->where('company_id', $company->id)->firstOrFail();
        $structure->update(['base_salary' => 90000]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $regRun = $this->regularize($originalRun, $manager);

        (new PayrollCalculator)->calculateRun($regRun);

        $this->assertSame(1, $regRun->refresh()->paySlips()->count());
        /** @var PaySlip $deltaSlip */
        $deltaSlip = $regRun->paySlips()->first();
        $this->assertSame($leaver->id, $deltaSlip->employee_id);
        $this->assertEquals(30000.0, (float) $deltaSlip->gross_salary);
    }

    public function test_hired_after_period_is_excluded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $originalRun = $this->makeLockedOriginalRun($company, 60000);

        // Embauché APRÈS la période d'origine : pas de bulletin original → exclu.
        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
            'status' => 'active',
            'contract_start' => '2026-09-01',
        ]);

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::query()->where('company_id', $company->id)->firstOrFail();
        $structure->update(['base_salary' => 80000]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $regRun = $this->regularize($originalRun, $manager);

        (new PayrollCalculator)->calculateRun($regRun);

        // Seul l'employé du run original est ajusté (1 bulletin).
        $this->assertSame(1, $regRun->refresh()->paySlips()->count());
    }

    public function test_recalculate_is_idempotent_no_double_application(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $originalRun = $this->makeLockedOriginalRun($company, 60000);

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::query()->where('company_id', $company->id)->firstOrFail();
        $structure->update(['base_salary' => 80000]);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $regRun = $this->regularize($originalRun, $manager);

        (new PayrollCalculator)->calculateRun($regRun);
        (new PayrollCalculator)->calculateRun($regRun->refresh());

        // Toujours UN bulletin delta (recalcul = remplacement, jamais d'ajout).
        $this->assertSame(1, $regRun->refresh()->paySlips()->count());

        /** @var PaySlip $deltaSlip */
        $deltaSlip = $regRun->paySlips()->first();
        $this->assertEquals(20000.0, (float) $deltaSlip->gross_salary);
    }
}
