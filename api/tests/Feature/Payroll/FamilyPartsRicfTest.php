<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2117 — parts fiscales / charges de famille (RICF CI, art. 120 CGI).
 *
 * Vérifie le câblage COMPLET du champ employé `family_parts` jusqu'au
 * bulletin : la colonne existe (migration tenant), le modèle la porte, et
 * `PayrollCalculator::calculateSlip()` l'applique aux règles pays via
 * `withFamilyParts()` → l'ITS CI est réduit de la RICF (max 5 parts,
 * plancher 0). Cas défaut (1 part / colonne absente) : comportement
 * historique inchangé.
 */
class FamilyPartsRicfTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $this->company = $company;
    }

    private function makeRun(int $baseSalary = 300000): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CI',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille CI (test RICF)',
            'base_salary' => $baseSalary,
            'currency' => 'XOF',
            'country_code' => 'CI',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        return $run;
    }

    public function test_employees_table_has_family_parts_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('employees', 'family_parts')
        );
    }

    public function test_default_family_parts_one_keeps_historic_its(): void
    {
        // Défaut (1 part) : ITS CI brut 300 000 = 39 000,00 (calcul manuel,
        // CI_COMPLIANCE.md §1) — aucune réduction, net = 300 000 − CNSS
        // 9 600 (3,2 %) − 39 000 = 251 400,00.
        $run = $this->makeRun();
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'fixed',
            'salary_base' => 300000,
            // Épinglé avant la période du run (2026-07) : la factory tire un
            // contract_start aléatoire (faker) qui pouvait tomber en juillet
            // → prorata → 270 954,55 au lieu de 300 000 (flake, Refs #5241).
            'contract_start' => '2026-01-01',
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $this->assertSame(300000.0, (float) $slip->gross_salary);
        $this->assertSame(48600.0, (float) $slip->total_deductions); // 9 600 CNSS + 39 000 ITS
        $this->assertSame(251400.0, (float) $slip->net_salary);
    }

    public function test_family_parts_three_applies_ricf(): void
    {
        // 3 parts (marié, 2 enfants) : RICF = 22 000 XOF/mois imputable sur
        // l'ITS brut 39 000 → ITS net 17 000,00. Net = 300 000 − 9 600 −
        // 17 000 = 273 400,00.
        $run = $this->makeRun();
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'fixed',
            'salary_base' => 300000,
            'contract_start' => '2026-01-01',
            'family_parts' => 3.0,
            'contract_start' => '2026-01-01',
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $this->assertSame(26600.0, (float) $slip->total_deductions); // 9 600 CNSS + 17 000 ITS
        $this->assertSame(273400.0, (float) $slip->net_salary);
    }

    public function test_family_parts_five_floors_tax_at_zero(): void
    {
        // 5 parts (maximum légal) : RICF = 44 000 > ITS brut 39 000 →
        // ITS net 0 (plancher). Net = 300 000 − 9 600 − 0 = 290 400,00.
        $run = $this->makeRun();
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'fixed',
            'salary_base' => 300000,
            'contract_start' => '2026-01-01',
            'family_parts' => 5.0,
            'contract_start' => '2026-01-01',
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $this->assertSame(9600.0, (float) $slip->total_deductions); // CNSS seule
        $this->assertSame(290400.0, (float) $slip->net_salary);
    }
}
