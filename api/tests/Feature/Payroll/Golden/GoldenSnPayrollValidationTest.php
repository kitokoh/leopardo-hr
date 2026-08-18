<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use Tests\TestCase;

/**
 * Validation experte #1912 — golden tests complémentaires paie Sénégal.
 *
 * Cette suite couvre les scénarios non présents dans GoldenSnPayrollTest :
 *   - IPRES T2 conditionné à la catégorie (correctif #1912)
 *   - Employé non-cadre au-dessus du plafond T1 (pas de T2)
 *   - Net salary complet (bulletin simplifié)
 *   - Hors-barème TRIMF / IR
 *   - Edge cases CSS plafond
 *
 * Méthodologie : calcul À LA MAIN dans chaque commentaire de test.
 * Statut : PRODUCTION — validé 2026-08-18 (#1912).
 */
class GoldenSnPayrollValidationTest extends TestCase
{
    private function rules(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    // -------------------------------------------------------------------------
    // IPRES T2 — catégorie (correctif #1912)
    // -------------------------------------------------------------------------

    public function test_ipres_t2_applies_only_to_cadre_category(): void
    {
        // Brut 600 000 XOF, catégorie 'cadre' :
        //   T1 salariale = 432 000 × 5,6 % = 24 192
        //   T2 salariale = (600 000 − 432 000) × 2,4 % = 4 032
        //   → salariale totale = 28 224
        $rules = $this->rules();
        $cadre = $rules->calculateSocialChargesWithCategory(600000.0, 'cadre');

        $this->assertSame(28224.0, $cadre['employee'], 'Cadre à 600k : T2 appliqué');
    }

    public function test_ipres_t2_not_applied_to_non_cadre_above_threshold(): void
    {
        // Brut 600 000 XOF, catégorie 'general' (non-cadre) :
        //   T1 uniquement = 432 000 × 5,6 % = 24 192 (plafonné)
        //   T2 = 0 (catégorie non cadre)
        $rules = $this->rules();
        $general = $rules->calculateSocialChargesWithCategory(600000.0, 'general');

        $this->assertSame(24192.0, $general['employee'], 'Employé général à 600k : pas de T2');
    }

    public function test_ipres_t2_not_applied_to_ouvrier_above_threshold(): void
    {
        // Brut 600 000 XOF, catégorie 'ouvrier' :
        //   T1 uniquement = 24 192 (plafonné), T2 = 0
        $rules = $this->rules();
        $ouvrier = $rules->calculateSocialChargesWithCategory(600000.0, 'ouvrier');

        $this->assertSame(24192.0, $ouvrier['employee'], 'Ouvrier à 600k : pas de T2');
        // Patronal sans T2 :
        // T1 patronale = 432 000 × 8,4 % = 36 288
        // CSS famille = min(600 000, 63 000) × 7 % = 4 410
        // CSS AT = 63 000 × 1 % = 630
        // CFCE = 600 000 × 3 % = 18 000
        // → patronale = 59 328
        $this->assertSame(59328.0, $ouvrier['employer'], 'Patronal ouvrier sans T2');
    }

    public function test_ipres_t2_null_category_uses_brut_threshold(): void
    {
        // Sans catégorie (null) → comportement historique : T2 si brut > 432 000.
        // Rétro-compatibilité du moteur préservée.
        $rules = $this->rules();
        $withNull = $rules->calculateSocialChargesWithCategory(600000.0, null);
        $withLegacy = $rules->calculateSocialCharges(600000.0);

        $this->assertSame($withNull['employee'], $withLegacy['employee'], 'Null == comportement legacy');
        $this->assertSame(28224.0, $withNull['employee'], 'T2 appliqué avec null (legacy)');
    }

    public function test_ipres_t2_cadre_at_exact_t1_ceiling(): void
    {
        // Brut exact = 432 000 (plafond T1), catégorie 'cadre' :
        //   T2 n'est PAS déclenché car brut n'est pas > 432 000 (seuil strict).
        $rules = $this->rules();
        $exactCadre = $rules->calculateSocialChargesWithCategory(432000.0, 'cadre');

        $this->assertSame(24192.0, $exactCadre['employee'], 'Cadre à 432k exact : pas de T2 (seuil strict)');
    }

    public function test_ipres_t2_cadre_1_above_ceiling(): void
    {
        // Brut 432 001 XOF, catégorie 'cadre' :
        //   T1 = 24 192,00 (plafonné)
        //   T2 = 1 × 2,4 % = 0,02 → arrondi
        //   → salariale = 24 192,02
        $rules = $this->rules();
        $justAbove = $rules->calculateSocialChargesWithCategory(432001.0, 'cadre');

        $this->assertSame(24192.02, $justAbove['employee'], '1 XOF au-dessus du plafond T1 → T2 minimal');
    }

    // -------------------------------------------------------------------------
    // Net salary complet — bulletin simplifié (validation experte #1912)
    // -------------------------------------------------------------------------

    public function test_net_salary_smig_employe_general(): void
    {
        // #1912 — Brut SMIG 64 305,43 XOF (décret 2023-1710), employé général :
        //   IPRES salariale = 3 601,10
        //   Assiette IR = 64 305,43 − 3 601,10 = 60 704,33
        //   Abattement 30 % du BRUT = 19 291,63 (sous le plafond 75 000)
        //   → assiette = 41 412,70 · Annualisé = 496 952,40 → tranche 0 % → IR = 0
        //   TRIMF = 900 (tranche ≤ 75 000)
        //   max(IR=0, TRIMF=900) = 900
        //   Net = 64 305,43 − 3 601,10 − 900 = 59 804,33
        $rules = $this->rules();

        $charges = $rules->calculateSocialChargesWithCategory(64305.43, 'general');
        $incomeTax = $rules->calculateIncomeTax(64305.43 - $charges['employee'], 12, 64305.43);
        $bracketTax = $rules->calculateBracketTax(64305.43);
        $fiscalTax = $rules->combineMinimumFiscalTax($incomeTax, $bracketTax);
        $net = round(64305.43 - $charges['employee'] - $fiscalTax, 2);

        $this->assertSame(3601.10, $charges['employee'], 'IPRES salariale SMIG');
        $this->assertSame(0.0, $incomeTax, 'IR = 0 (SMIG sous le seuil annuel 630k)');
        $this->assertSame(900.0, $bracketTax, 'TRIMF 900');
        $this->assertSame(900.0, $fiscalTax, 'max(0, 900) = 900');
        $this->assertSame(59804.33, $net, 'Net SMIG employé général');
    }

    public function test_net_salary_cadre_600000(): void
    {
        // Brut 600 000 XOF, cadre avec T2 :
        //   IPRES salariale = 28 224 (T1 24 192 + T2 4 032)
        //   Assiette IR = 600 000 − 28 224 = 571 776
        //   Abattement 30 % du BRUT = 180 000 → 391 776
        // #1912 — abattement plafonné : min(30 % × 600 000, 75 000) = 75 000.
        //   Annualisé = (600 000 − 28 224 − 75 000) × 12 = 5 961 312 →
        //     20 % × 870 000 = 174 000 · 30 % × 2 500 000 = 750 000 ·
        //     35 % × 1 961 312 = 686 459,20 → annuel 1 610 459,20 → mensuel 134 204,93
        //   TRIMF = 3 600 (tranche 200 001–600 000)
        //   max(134 204,93, 3 600) = 134 204,93
        //   Net = 600 000 − 28 224 − 134 204,93 = 437 571,07
        $rules = $this->rules();

        $charges = $rules->calculateSocialChargesWithCategory(600000.0, 'cadre');
        $incomeTax = $rules->calculateIncomeTax(600000.0 - $charges['employee'], 12, 600000.0);
        $bracketTax = $rules->calculateBracketTax(600000.0);
        $fiscalTax = $rules->combineMinimumFiscalTax($incomeTax, $bracketTax);
        $net = round(600000.0 - $charges['employee'] - $fiscalTax, 2);

        $this->assertSame(28224.0, $charges['employee']);
        $this->assertSame(134204.93, $incomeTax);
        $this->assertSame(3600.0, $bracketTax);
        $this->assertSame(134204.93, $fiscalTax, 'IR > TRIMF pour cadre 600k');
        $this->assertSame(437571.07, $net);
    }

    // -------------------------------------------------------------------------
    // CSS plafond — indépendance des cotisations patronales
    // -------------------------------------------------------------------------

    public function test_css_patronal_caps_at_63000_regardless_of_gross(): void
    {
        // CSS famille = 7 % × min(brut, 63 000), CSS AT = 1 % × min(brut, 63 000)
        // Brut 200 000 : min = 63 000 → CSS fam = 4 410, AT = 630 → total CSS 5 040
        // Brut 500 000 : idem plafond → CSS fam = 4 410, AT = 630 → total CSS 5 040
        $rules = $this->rules();

        $high = $rules->calculateSocialChargesWithCategory(200000.0, 'general');
        $veryHigh = $rules->calculateSocialChargesWithCategory(500000.0, 'general');

        // CSS part dans le patronal :
        // 200k : T1 patronal 16 800 + CSS 5 040 + CFCE 6 000 = 27 840
        // 500k : T1 plafond 36 288 + CSS 5 040 + CFCE 15 000 = 56 328
        $this->assertSame(11200.0, $high['employee'], 'Employé 200k salariale T1');
        $this->assertSame(27840.0, $high['employer'], 'Patronal 200k sans T2');
        $this->assertSame(24192.0, $veryHigh['employee'], 'Employé 500k salariale T1 plafonnée');
        $this->assertSame(56328.0, $veryHigh['employer'], 'Patronal 500k sans T2');
    }

    // -------------------------------------------------------------------------
    // TRIMF — limites de tranches
    // -------------------------------------------------------------------------

    public function test_trimf_boundary_between_tranche_1_and_2(): void
    {
        // #1912 : barème révisé — tranche 1 ≤ 75 000 (900), tranche 2 ≤ 200 000 (1 800).
        $rules = $this->rules();
        $this->assertSame(900.0, $rules->calculateBracketTax(75000.0), 'Tranche 1 max');
        $this->assertSame(1800.0, $rules->calculateBracketTax(75001.0), 'Tranche 2 min');
    }

    public function test_trimf_boundary_between_tranche_2_and_3(): void
    {
        $rules = $this->rules();
        $this->assertSame(1800.0, $rules->calculateBracketTax(200000.0), 'Tranche 2 max');
        $this->assertSame(3600.0, $rules->calculateBracketTax(200001.0), 'Tranche 3 min');
    }

    // -------------------------------------------------------------------------
    // confidenceLevel + verificationDate (#1912)
    // -------------------------------------------------------------------------

    public function test_confidence_level_is_production_after_1912(): void
    {
        $rules = $this->rules();

        $this->assertSame('production', $rules->confidenceLevel(), 'Sénégal en production après validation #1912');
        $this->assertSame('2026-08-18', $rules->verificationDate(), 'Date de validation expert');
    }

    // -------------------------------------------------------------------------
    // Préavis — récapitulatif complet par catégorie
    // -------------------------------------------------------------------------

    public function test_notice_period_all_categories(): void
    {
        $rules = $this->rules();

        // cadre → 3 mois = 66 j ouvrés
        $this->assertSame(66.0, $rules->noticePeriodDays(1.0, 'cadre'));
        // ouvrier → 8 j calendaires = 6 j ouvrés
        $this->assertSame(6.0, $rules->noticePeriodDays(1.0, 'ouvrier'));
        $this->assertSame(6.0, $rules->noticePeriodDays(1.0, 'worker'));
        // employé / général → 1 mois = 22 j ouvrés
        $this->assertSame(22.0, $rules->noticePeriodDays(1.0, 'general'));
        $this->assertSame(22.0, $rules->noticePeriodDays(1.0, null));
        $this->assertSame(22.0, $rules->noticePeriodDays(1.0, 'technicien'));
    }
}
