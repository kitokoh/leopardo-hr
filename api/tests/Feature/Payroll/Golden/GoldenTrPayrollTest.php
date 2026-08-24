<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Turquie (TR) — issue #5253, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/TR_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles 2026 (sources : CSGB/SGK/GİB, Resmî Gazete 31/12/2025) :
 *  - SMIG brut 33 030,00 TRY/mois (net officiel 28 075,50).
 *  - SGK : salarié 14 % (9 % MYÖ + 5 % GSS) + chômage 1 % = 15 % ; patronal
 *    21,75 % (12 % MYÖ + 7,5 % GSS + 2,25 % KVSK, SANS teşvik) + chômage 2 %.
 *    Toutes les cotisations plafonnées au tavan 2026 : 297 270,00 TRY/mois.
 *  - Gelir vergisi : barème ANNUEL salariés 2026 (15/20/27/35/40 %,
 *    190 000 / 400 000 / 1 500 000 / 5 300 000) appliqué sur l'assiette
 *    mensuelle × 12, ramené au mois, MOINS l'asgari ücret istisnası
 *    (exonération du SMIC net — loi n° 7346 du 25/12/2022) : impôt dû =
 *    max(0, impôt total − impôt sur le SMIC net 28 075,50 TRY/mois).
 *  - Damga vergisi : binde 7,59 (0,759 %) sur la part du brut > SMIC,
 *    exposée via calculateBracketTax() (taxe forfaitaire sur salaire).
 */
class GoldenTrPayrollTest extends TestCase
{
    private function rules(): TurkeyPayrollRules
    {
        return new TurkeyPayrollRules;
    }

    public function test_golden_tr_smig_2026_33030(): void
    {
        // Calcul manuel, brut = SMIG 2026 33 030,00 TRY :
        //   SGK salarié = 33 030 × 14 % = 4 624,20
        //   Chômage salarié = 33 030 × 1 % = 330,30 → charges salarié 4 954,50
        //   Assiette IR = 33 030 − 4 954,50 = 28 075,50 → annuel 336 906 :
        //     190 000 × 15 % + 146 906 × 20 % = 57 881,20 → mensuel 4 823,43
        //   İstisna (SMIC net) = 4 823,43 → impôt = max(0 ; 4 823,43 − 4 823,43) = 0
        //   Damga = 0 (brut = SMIC, part exonérée)
        //   Net = 33 030 − 4 954,50 − 0 − 0 = 28 075,50  ← NET OFFICIEL CSGB
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(33030.0);
        $this->assertSame(4954.50, $charges['employee']);
        $this->assertSame(7844.63, $charges['employer']);

        $tax = $rules->calculateIncomeTax(33030.0 - $charges['employee']);
        $this->assertSame(0.00, $tax);

        $stamp = $rules->calculateBracketTax(33030.0);
        $this->assertSame(0.00, $stamp);

        $this->assertSame(28075.50, round(33030.0 - $charges['employee'] - $tax - $stamp, 2));
    }

    public function test_golden_tr_ouvrier_40000(): void
    {
        // Brut 40 000 : SGK 5 600 + chômage 400 → 6 000,00 salarié
        //   Assiette IR = 34 000 → annuel 408 000 :
        //     190 000 × 15 % + 210 000 × 20 % + 8 000 × 27 % = 72 660 → /12 = 6 055,00
        //   Impôt = 6 055,00 − 4 823,43 = 1 231,57
        //   Damga = (40 000 − 33 030) × 0,759 % = 52,90
        //   Net = 40 000 − 6 000 − 1 231,57 − 52,90 = 32 715,53
        $charges = $this->rules()->calculateSocialCharges(40000.0);
        $this->assertSame(6000.00, $charges['employee']);
        $this->assertSame(9500.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(40000.0 - $charges['employee']);
        $this->assertSame(1231.57, $tax);
        $this->assertSame(52.90, $this->rules()->calculateBracketTax(40000.0));
        $this->assertSame(32715.53, round(40000.0 - $charges['employee'] - $tax - 52.90, 2));
    }

    public function test_golden_tr_cadre_moyen_50000(): void
    {
        // Brut 50 000 : salarié 7 500 · assiette 42 500 → annuel 510 000 :
        //   190 000 × 15 % + 210 000 × 20 % + 110 000 × 27 % = 100 200 → /12 = 8 350,00
        //   Impôt = 8 350,00 − 4 823,43 = 3 526,57 · Damga = 16 970 × 0,759 % = 128,80
        //   Net = 50 000 − 7 500 − 3 526,57 − 128,80 = 38 844,63
        $charges = $this->rules()->calculateSocialCharges(50000.0);
        $this->assertSame(7500.00, $charges['employee']);
        $this->assertSame(11875.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(50000.0 - $charges['employee']);
        $this->assertSame(3526.57, $tax);
        $this->assertSame(128.80, $this->rules()->calculateBracketTax(50000.0));
        $this->assertSame(38844.63, round(50000.0 - $charges['employee'] - $tax - 128.80, 2));
    }

    public function test_golden_tr_cadre_superieur_80000(): void
    {
        // Brut 80 000 : salarié 12 000 · assiette 68 000 → annuel 816 000 :
        //   190 000 × 15 % + 210 000 × 20 % + 416 000 × 27 % = 182 820 → /12 = 15 235,00
        //   Impôt = 15 235,00 − 4 823,43 = 10 411,57 · Damga = 46 970 × 0,759 % = 356,50
        //   Net = 80 000 − 12 000 − 10 411,57 − 356,50 = 57 231,93
        $charges = $this->rules()->calculateSocialCharges(80000.0);
        $this->assertSame(12000.00, $charges['employee']);
        $this->assertSame(19000.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(80000.0 - $charges['employee']);
        $this->assertSame(10411.57, $tax);
        $this->assertSame(356.50, $this->rules()->calculateBracketTax(80000.0));
        $this->assertSame(57231.93, round(80000.0 - $charges['employee'] - $tax - 356.50, 2));
    }

    public function test_golden_tr_haut_salaire_100000(): void
    {
        // Brut 100 000 : salarié 15 000 · assiette 85 000 → annuel 1 020 000 :
        //   190 000 × 15 % + 210 000 × 20 % + 620 000 × 27 % = 237 900 → /12 = 19 825,00
        //   Impôt = 19 825,00 − 4 823,43 = 15 001,57 · Damga = 66 970 × 0,759 % = 508,30
        //   Net = 100 000 − 15 000 − 15 001,57 − 508,30 = 69 490,13
        $charges = $this->rules()->calculateSocialCharges(100000.0);
        $this->assertSame(15000.00, $charges['employee']);
        $this->assertSame(23750.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(100000.0 - $charges['employee']);
        $this->assertSame(15001.57, $tax);
        $this->assertSame(508.30, $this->rules()->calculateBracketTax(100000.0));
        $this->assertSame(69490.13, round(100000.0 - $charges['employee'] - $tax - 508.30, 2));
    }

    public function test_golden_tr_tres_haut_salaire_250000(): void
    {
        // Brut 250 000 : salarié 37 500 · assiette 212 500 → annuel 2 550 000 :
        //   190 000 × 15 % + 210 000 × 20 % + 1 100 000 × 27 % + 1 050 000 × 35 %
        //   = 735 000 → /12 = 61 250,00
        //   Impôt = 61 250,00 − 4 823,43 = 56 426,57 · Damga = 216 970 × 0,759 % = 1 646,80
        //   Net = 250 000 − 37 500 − 56 426,57 − 1 646,80 = 154 426,63
        $charges = $this->rules()->calculateSocialCharges(250000.0);
        $this->assertSame(37500.00, $charges['employee']);
        $this->assertSame(59375.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(250000.0 - $charges['employee']);
        $this->assertSame(56426.57, $tax);
        $this->assertSame(1646.80, $this->rules()->calculateBracketTax(250000.0));
        $this->assertSame(154426.63, round(250000.0 - $charges['employee'] - $tax - 1646.80, 2));
    }

    public function test_golden_tr_plafond_sgk_tavan_300000(): void
    {
        // Brut 300 000 > tavan 297 270 : les cotisations sont plafonnées.
        //   SGK salarié = 297 270 × 14 % = 41 617,80 · chômage = 2 972,70 → 44 590,50
        //   Patronal = 297 270 × 21,75 % = 64 656,23 + 297 270 × 2 % = 5 945,40 → 70 601,63
        //   Assiette IR = 300 000 − 44 590,50 = 255 409,50 → annuel 3 064 914 :
        //     190 000×15 % + 210 000×20 % + 1 100 000×27 % + 1 564 914×35 %
        //     = 915 219,90 → /12 = 76 268,33
        //   Impôt = 76 268,33 − 4 823,43 = 71 444,90 · Damga = 266 970 × 0,759 % = 2 026,30
        //   Net = 300 000 − 44 590,50 − 71 444,90 − 2 026,30 = 181 938,30
        $charges = $this->rules()->calculateSocialCharges(300000.0);
        $this->assertSame(44590.50, $charges['employee']);
        $this->assertSame(70601.63, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(300000.0 - $charges['employee']);
        $this->assertSame(71444.90, $tax);
        $this->assertSame(2026.30, $this->rules()->calculateBracketTax(300000.0));
        $this->assertSame(181938.30, round(300000.0 - $charges['employee'] - $tax - 2026.30, 2));
    }

    public function test_golden_tr_istisna_boundary_net_smic(): void
    {
        // Assiette imposable EXACTEMENT égale au SMIC net (28 075,50/mois) :
        //   annuel 336 906 → impôt brut 57 881,20 → /12 = 4 823,43
        //   İstisna = 4 823,43 → impôt dû 0,00 (borne basse de l'exonération).
        $this->assertSame(0.00, $this->rules()->calculateIncomeTax(28075.50));
    }

    public function test_golden_tr_istisna_just_above_boundary(): void
    {
        // Assiette 28 075,58 (1 TRY annuel de plus) : impôt brut 4 823,45 →
        //   impôt dû = 4 823,45 − 4 823,43 = 0,02 (l'exonération s'arrête).
        $this->assertSame(0.02, $this->rules()->calculateIncomeTax(28075.58));
    }

    public function test_golden_tr_tax_slab_edge_190000(): void
    {
        // Assiette annuelle 190 000 (borne 15 % → 20 %) : mensuel 15 833,33
        //   → impôt brut 2 375,00 < istisna 4 823,43 → impôt dû 0,00
        //   (un salaire sous le SMIC net ne paie pas d'IR en 2026).
        $this->assertSame(0.00, $this->rules()->calculateIncomeTax(190000 / 12));
    }

    public function test_golden_tr_tax_slab_edge_400000(): void
    {
        // Assiette annuelle 400 000 (borne 20 % → 27 %) : mensuel 33 333,33
        //   190 000×15 % + 210 000×20 % = 70 500 → /12 = 5 875,00
        //   Impôt = 5 875,00 − 4 823,43 = 1 051,57
        $this->assertSame(1051.57, $this->rules()->calculateIncomeTax(400000 / 12));
    }

    public function test_golden_tr_tax_slab_edge_1500000(): void
    {
        // Assiette annuelle 1 500 000 (borne 27 % → 35 %) : mensuel 125 000
        //   190 000×15 % + 210 000×20 % + 1 100 000×27 % = 367 500 → /12 = 30 625,00
        //   Impôt = 30 625,00 − 4 823,43 = 25 801,57
        $this->assertSame(25801.57, $this->rules()->calculateIncomeTax(1500000 / 12));
    }

    public function test_golden_tr_tax_slab_edge_5300000(): void
    {
        // Assiette annuelle 5 300 000 (borne 35 % → 40 %) : mensuel 441 666,67
        //   367 500 + 3 800 000×35 % = 1 697 500 → /12 = 141 458,33
        //   Impôt = 141 458,33 − 4 823,43 = 136 634,90
        $this->assertSame(136634.90, $this->rules()->calculateIncomeTax(5300000 / 12));
    }

    public function test_golden_tr_damga_vergisi_minimum_wage_exempt(): void
    {
        // Damga vergisi : binde 7,59 sur la part > SMIC brut (33 030).
        //   brut = SMIC → 0,00 ; brut = SMIC + 1 000 → 1 000 × 0,759 % = 7,59
        $this->assertSame(0.00, $this->rules()->calculateBracketTax(33030.0));
        $this->assertSame(7.59, $this->rules()->calculateBracketTax(34030.0));
    }

    public function test_golden_tr_stamp_tax_is_a_separate_flat_line(): void
    {
        // La damga n'est PAS déductible de l'assiette IR : elle est exposée
        // via le mécanisme de taxe forfaitaire (calculateBracketTax), pas
        // dans les cotisations sociales — le libellé de ligne le reflète.
        $this->assertSame('Damga vergisi (binde 7,59)', $this->rules()->flatPayrollTaxLabel());
    }

    public function test_golden_tr_metadata_2026(): void
    {
        $rules = $this->rules();

        $this->assertSame('TR', $rules->countryCode());
        $this->assertSame('TRY', $rules->currency());
        $this->assertSame(33030.0, $rules->minimumWage());
        $this->assertSame('tr', $rules->language());
        $this->assertSame('Europe/Istanbul', $rules->timezone());
        $this->assertSame([7], $rules->weeklyRestDays());
        $this->assertSame(['monthly'], $rules->supportedPayCycles());
        $this->assertSame('pilot', $rules->confidenceLevel());
    }

    public function test_golden_tr_overtime_legal_tier(): void
    {
        // İş Kanunu n° 4857 art. 41 : heures supplémentaires majorées de 50 %
        // au-delà de 45 h/semaine (art. 63) — palier unique 1,5.
        $rules = $this->rules();

        $this->assertSame(45.0, $rules->overtimeThresholdWeeklyHours());

        $tiers = $rules->overtimeRateTiers();
        $this->assertNotEmpty($tiers);
        $this->assertNull($tiers[0]['up_to_hours']);
        $this->assertSame(1.5, $tiers[0]['multiplier']);
    }
}
