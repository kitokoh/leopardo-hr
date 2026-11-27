<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9975 — `PaySlipLine` hérite du tenant de SON bulletin.
 *
 * ## Pourquoi ce test existe
 *
 * `pay_slip_lines` n'avait aucune colonne `company_id` : elle n'était isolée
 * que par jointure sur `pay_slips`. La migration lot 6 ajoute la colonne en
 * `NOT NULL` — et casse alors tout writer qui ne la renseigne pas (mesuré en
 * CI : `GoldenAccountingInvariants` échouait en
 * `null value in column "company_id"`).
 *
 * Le correctif est un listener `creating` sur `PaySlipLine` qui hérite du
 * tenant du bulletin parent quand aucun tenant n'est actif (console, jobs,
 * fixtures). Ce test couvre les trois branches de ce listener :
 *
 *   1. tenant actif → `BelongsToCompany` a déjà posé `company_id`, le listener
 *      ne doit PAS l'écraser (garde anti-spoof #7646) ;
 *   2. aucun tenant actif → héritage depuis `pay_slips` ;
 *   3. `pay_slip_id` absent → aucune valeur inventée, la ligne reste telle
 *      quelle (l'erreur `NOT NULL` est alors légitime et visible).
 *
 * Sans ce test, la branche 1 (la plus dangereuse : un écrasement silencieux
 * permettrait d'écrire une ligne de paie dans un autre tenant) n'était couverte
 * par rien.
 */
class PaySlipLineTenantInheritanceTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Construit un bulletin réellement persistable.
     *
     * `pay_slips` exige `payroll_run_id` et `employee_id` en NOT NULL : un
     * `PaySlip::factory()` n'existe pas (le modèle n'utilise pas `HasFactory`),
     * et un `create()` partiel échouerait sur les clés étrangères. On construit
     * donc la chaîne minimale réelle, comme les autres tests de paie.
     */
    private function makeSlip(Company $company): PaySlip
    {
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'calculated',
            'total_gross' => 0,
            'total_net' => 0,
            'employee_count' => 1,
        ]);

        return PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 1000.0,
            'total_deductions' => 0.0,
            'net_salary' => 1000.0,
            'status' => 'calculated',
        ]);
    }

    /**
     * Branche 2 : hors contexte tenant, la ligne hérite du bulletin.
     */
    public function test_line_inherits_company_from_its_pay_slip_without_active_tenant(): void
    {
        $company = Company::factory()->create();
        $slip = $this->makeSlip($company);

        // Aucun tenant actif : on sort du contexte posé par le middleware.
        app()->forgetInstance('current_company');

        $line = PaySlipLine::create([
            'pay_slip_id' => $slip->id,
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => 1000.0,
            'rate' => 100.0,
            'amount' => 1000.0,
            'order' => 1,
        ]);

        expect($line->company_id)->toBe(
            $company->id,
            'la ligne doit hériter du tenant de son bulletin, jamais rester NULL.'
        );
    }

    /**
     * Branche 1 : un tenant actif n'est JAMAIS écrasé par le parent.
     *
     * C'est la garde anti-spoof #7646 : si le listener écrasait, une ligne
     * pourrait être rattachée au tenant du bulletin tout en étant créée dans un
     * autre contexte — exactement la fuite que RLS doit empêcher.
     */
    public function test_active_tenant_is_never_overwritten_by_the_parent(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $slipOfA = $this->makeSlip($companyA);

        // Tenant actif = B, bulletin = A. Le listener ne doit pas « corriger »
        // vers A : le trait a posé B, et c'est B qui fait foi.
        app()->instance('current_company', $companyB);

        $line = PaySlipLine::create([
            'pay_slip_id' => $slipOfA->id,
            'name' => 'Prime',
            'type' => 'earning',
            'base_amount' => 100.0,
            'rate' => 100.0,
            'amount' => 100.0,
            'order' => 2,
        ]);

        expect($line->company_id)->toBe(
            $companyB->id,
            'le tenant actif doit primer : le listener ne s\'exécute que si le trait n\'a rien posé.'
        );
    }

    /**
     * Branche 3 : sans bulletin, aucune valeur inventée — l'échec est BRUYANT.
     *
     * Le listener ne pose rien, donc la contrainte `NOT NULL` refuse
     * l'insertion. C'est le comportement voulu : une ligne de paie sans tenant
     * est une erreur de programmation, pas une donnée à réparer en silence.
     * Une valeur inventée (premier tenant venu, tenant courant) serait bien
     * pire : elle rangerait la ligne dans la mauvaise entreprise.
     */
    public function test_line_without_pay_slip_fails_loudly_instead_of_inventing_a_company(): void
    {
        app()->forgetInstance('current_company');

        $this->expectException(\Illuminate\Database\QueryException::class);

        PaySlipLine::create([
            'pay_slip_id' => null,
            'name' => 'Ligne orpheline',
            'type' => 'earning',
            'base_amount' => 0.0,
            'rate' => 0.0,
            'amount' => 0.0,
            'order' => 1,
        ]);
    }
}
