<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CnssDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\IpresDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1920 — réconciliation bulletin ↔ déclaration à partir d'UNE MÊME
 * entrée (brut, période, employé).
 *
 * Méthodologie : un run réel est calculé par le moteur (calculateRun) puis la
 * déclaration CSV est générée depuis le MÊME run ; on asserte que chaque ligne
 * de déclaration (assiettes, cotisations) correspond aux montants du bulletin
 * (moteur) — les valeurs attendues sont re-dérivées DEPUIS les règles pays
 * (source unique), pas recopiées des générateurs : si les constantes des
 * générateurs divergent des règles, ce test échoue explicitement.
 *
 * Périmètre constaté (à documenter pour l'expert-comptable) :
 *  - CI : réconciliation COMPLÈTE — le CSV CNSS couvre retraite/famille/AT,
 *    mêmes caps que le moteur (plafond 1 647 315 XOF) ;
 *  - SN : la déclaration IPRES/CSS couvre T1/T2 + CSS famille (plafonnée au
 *    plafond T1) mais EXCLUT CSS AT 1 % et CFCE 3 % du patronal bulletin.
 *    Le test SN se limite donc aux montants déclarés (T1/T2/CSS famille) —
 *    la divergence AT/CFCE est tracée dans le suivi #1920.
 */
class BulletinDeclarationReconciliationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Run réel calculé par le moteur pour un employé donné (même entrée que
     * la déclaration). Le contrat couvre toute la période (factory sinon
     * aléatoire → prorata flaky, cf. #1966).
     *
     * @param  array<string, mixed>  $employeeExtra
     */
    /**
     * @param  array<string, mixed>  $employeeExtra
     */
    private function engineRun(string $country, string $currency, float $gross, array $employeeExtra = []): PayrollRun
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => $gross,
            'contract_start' => '2026-01-01',
            'contract_end' => '2026-12-31',
        ], $employeeExtra));

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille '.$country,
            'base_salary' => $gross,
            'currency' => $currency,
            'country_code' => $country,
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        (new PayrollCalculator)->calculateRun($run);

        return $run->refresh();
    }

    /**
     * Montants par code de cotisation attendus DEPUIS les règles pays
     * (même formule de base plafonnée que le moteur).
     *
     * @return array<string, float>
     */
    private function expectedPerCode(string $countryCode, float $gross): array
    {
        $rules = $countryCode === 'CI' ? new CedeaoPayrollRules('CI') : new SenegalPayrollRules;

        $expected = [];
        foreach ($rules->socialContributions() as $contribution) {
            $base = $contribution['cap'] === null
                ? $gross
                : min($gross, (float) $contribution['cap']);
            $expected[$contribution['code']] = round($base * (float) $contribution['rate'] / 100, 2);
        }

        return $expected;
    }

    // ── CNSS Côte d'Ivoire ────────────────────────────────────────────────

    public function test_ci_bulletin_reconciles_with_cnss_declaration_below_cap(): void
    {
        // Brut 400 000 XOF (sous le plafond CNSS 1 647 315).
        $run = $this->engineRun('CI', 'XOF', 400000.0, [
            'first_name' => 'Aya', 'last_name' => 'Kouassi', 'cnss_ci_matricule' => 'CNSS-CI-001',
        ]);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $slip->update(['status' => 'validated']);

        $csv = (new CnssDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        $expected = $this->expectedPerCode('CI', (float) $slip->gross_salary);

        // Chaque ligne de déclaration == montants du bulletin (même entrée).
        $this->assertSame('400000.00', $row[3]); // salaire_brut
        $this->assertSame('400000.00', $row[4]); // assiette plafonnée (sous plafond)
        $this->assertSame(number_format($expected['CNSS_CI_RET_EMP'], 2, '.', ''), $row[5]);
        $this->assertSame(number_format($expected['CNSS_CI_RET_PAT'], 2, '.', ''), $row[6]);
        $this->assertSame(number_format($expected['CNSS_CI_FAM_PAT'], 2, '.', ''), $row[7]);
        $this->assertSame(number_format($expected['CNSS_CI_AT_PAT'], 2, '.', ''), $row[8]);

        // Réconciliation agrégée bulletin ↔ déclaration :
        //  - salarial : la seule cotisation salariale CI est la retraite 3,2 % ;
        //  - patronal : le CSV couvre TOUTES les cotisations patronales CI
        //    (retraite + famille + AT) → égal au bulletin du moteur.
        $rules = new CedeaoPayrollRules('CI');
        $charges = $rules->calculateSocialCharges((float) $slip->gross_salary);
        $this->assertSame(number_format($charges['employee'], 2, '.', ''), $row[9]);
        $this->assertSame(number_format($charges['employer'], 2, '.', ''), $row[10]);
        $this->assertEquals($charges['employer'], (float) $slip->employer_contributions);
        $this->assertEquals($charges['employee'], $expected['CNSS_CI_RET_EMP']);
    }

    public function test_ci_bulletin_reconciles_with_cnss_declaration_above_cap(): void
    {
        // Brut 2 000 000 XOF (AU-DESSUS des plafonds) : assiette retraite
        // plafonnée à 1 647 315, famille et AT plafonnées à 70 000 (#1913).
        $run = $this->engineRun('CI', 'XOF', 2000000.0, [
            'first_name' => 'Moussa', 'last_name' => 'Traore', 'cnss_ci_matricule' => 'CNSS-CI-002',
        ]);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $slip->update(['status' => 'validated']);

        $csv = (new CnssDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        $gross = (float) $slip->gross_salary;
        $expected = $this->expectedPerCode('CI', $gross);

        $this->assertSame('1647315.00', $row[4]); // assiette retraite plafonnée
        $this->assertSame(number_format($expected['CNSS_CI_RET_EMP'], 2, '.', ''), $row[5]);
        $this->assertSame(number_format($expected['CNSS_CI_FAM_PAT'], 2, '.', ''), $row[7]);
        $this->assertSame(number_format($expected['CNSS_CI_AT_PAT'], 2, '.', ''), $row[8]);

        $rules = new CedeaoPayrollRules('CI');
        $charges = $rules->calculateSocialCharges($gross);
        $this->assertSame(number_format($charges['employee'], 2, '.', ''), $row[9]);
        $this->assertSame(number_format($charges['employer'], 2, '.', ''), $row[10]);
        $this->assertEquals($charges['employer'], (float) $slip->employer_contributions);
    }

    // ── IPRES/CSS Sénégal ─────────────────────────────────────────────────

    public function test_sn_bulletin_reconciles_with_ipres_declaration_t1(): void
    {
        // Salarié régime général (T1), brut 400 000 XOF (sous le plafond
        // T1 432 000) : pas de tranche T2. CSS famille plafonnée à 63 000
        // (#1913) — la base CSS n'est plus celle du T1.
        $run = $this->engineRun('SN', 'XOF', 400000.0, [
            'first_name' => 'Fatou', 'last_name' => 'Diop', 'ipres_matricule' => 'IPRES-SN-001',
            'ipres_category' => 'general',
        ]);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $slip->update(['status' => 'validated']);

        $csv = (new IpresDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        $gross = (float) $slip->gross_salary;
        $expected = $this->expectedPerCode('SN', $gross);

        $this->assertSame('general', $row[3]);
        $this->assertSame('400000.00', $row[4]); // brut
        $this->assertSame('400000.00', $row[5]); // assiette T1
        $this->assertSame(number_format($expected['IPRES_SN_EMP'], 2, '.', ''), $row[6]);
        $this->assertSame(number_format($expected['IPRES_SN_PAT'], 2, '.', ''), $row[7]);
        $this->assertSame('0.00', $row[8]); // pas de T2 (général)
        $this->assertSame('0.00', $row[9]);
        $this->assertSame('0.00', $row[10]);
        $this->assertSame(number_format($expected['CSS_SN_PAT_FAM'], 2, '.', ''), $row[11]);

        // Réconciliation bulletin ↔ déclaration (salarié + cotisations déclarées).
        $rules = new SenegalPayrollRules;
        $charges = $rules->calculateSocialCharges($gross);
        $this->assertSame(number_format($charges['employee'], 2, '.', ''), $row[6]);
        // total_patronal déclaré = T1 + CSS famille (le CSV n'inclut ni CSS AT
        // 1 % ni CFCE 3 % — périmètre déclaration IPRES/CSS, suivi #1920).
        $this->assertEquals($charges['employee'], $expected['IPRES_SN_EMP']);
        // total_patronal CSV = T1 8,4 % + CSS famille (plaf. 63 000 #1913) —
        // AT 1 % et CFCE 3 % restent hors périmètre CSV (déclarations dédiées).
        $this->assertEquals(
            $expected['IPRES_SN_PAT'] + $expected['CSS_SN_PAT_FAM'],
            (float) $row[12],
        );
        $this->assertEquals(
            $charges['employee'],
            $expected['IPRES_SN_EMP'],
        );
    }

    public function test_sn_cadre_t2_tranche_reconciles_between_engine_and_declaration(): void
    {
        // Cadre T2, brut 1 000 000 XOF : le moteur applique T2 sur la tranche
        // 432 001 – 2 160 000 quand le brut dépasse le plafond T1 (hypothèse
        // pilot #1827) ; la déclaration applique T2 pour la catégorie cadre.
        // Les deux doivent produire les MÊMES montants T1/T2/CSS.
        $run = $this->engineRun('SN', 'XOF', 1000000.0, [
            'first_name' => 'Ousmane', 'last_name' => 'Ndiaye', 'ipres_matricule' => 'IPRES-SN-002',
            'ipres_category' => 'cadre',
        ]);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();
        $slip->update(['status' => 'validated']);

        $csv = (new IpresDeclarationGenerator)->generate($run);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        $row = str_getcsv($lines[1]);

        $gross = (float) $slip->gross_salary;
        $t1Base = min($gross, 432000.0);
        $t2Base = min($gross, 2160000.0) - 432000.0;

        // Valeurs légales documentées (SN_COMPLIANCE.md §4bis, pilot #1827).
        $this->assertSame('cadre', $row[3]);
        $this->assertSame('432000.00', $row[5]); // assiette T1
        $this->assertSame('568000.00', $row[8]); // assiette T2 = 1 000 000 − 432 000
        $this->assertSame(number_format($t1Base * 5.6 / 100, 2, '.', ''), $row[6]);
        $this->assertSame(number_format($t1Base * 8.4 / 100, 2, '.', ''), $row[7]);
        $this->assertSame(number_format($t2Base * 2.4 / 100, 2, '.', ''), $row[9]);
        $this->assertSame(number_format($t2Base * 3.6 / 100, 2, '.', ''), $row[10]);

        // Mêmes montants côté moteur (bulletins) : l'employé salarié du run
        // a exactement ces cotisations dans son bulletin.
        $rules = new SenegalPayrollRules;
        $charges = $rules->calculateSocialCharges($gross);
        $this->assertEquals(
            round($t1Base * 5.6 / 100 + $t2Base * 2.4 / 100, 2),
            $charges['employee'],
        );
        // total_patronal déclaré (T1 + T2 + CSS famille plafonnée 63 000 à 7 %
        // depuis #2486) — cohérent avec le moteur sur les mêmes composantes
        // (AT 1 % + CFCE 3 % hors périmètre CSV — décision #2014 §11).
        $declaredPatronal = (float) $row[12];
        $cssCap = min($gross, 63000.0);
        // Issue #2473/#2486 : CSS famille portée à 7 % (CIPRES/CLEISS) dans
        // le moteur ET le générateur CSV (#2568) — le calcul manuel du test
        // doit suivre (il était à 3 %, régression #2590).
        $enginePatronalDeclaredScope = round(
            $t1Base * 8.4 / 100 + $t2Base * 3.6 / 100 + $cssCap * 7.0 / 100,
            2,
        );
        $this->assertEquals($enginePatronalDeclaredScope, $declaredPatronal);
        // Écart bulletin ↔ CSV = AT 1 % + CFCE 3 % (hors périmètre, documenté).
        $this->assertLessThan($charges['employer'], $declaredPatronal);
    }
}
