<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2119 — golden tests Canada (CA), calculés À LA MAIN (règle #1938).
 * Référence : docs/payroll/CA_COMPLIANCE.md (Loi de l'impôt sur le revenu —
 * barème FÉDÉRAL 2024, RPC (CPP) Loi n° C-23, AE (EI) Loi n° C-45).
 *
 * Modèle moteur (CA_COMPLIANCE.md §1-2) — FÉDÉRAL uniquement :
 *   RPC = brut × 5,95 % · AE = brut × 1,66 %
 *   IR  = progressif ANNUEL sur (brut − cotisations) × 12, / 12.
 *   Tranches fédérales 2024 = 0–55 867 : 15 % · 55 868–111 733 : 20,5 % ·
 *      111 734–173 205 : 26 % · 173 206–246 752 : 29 % · > 246 752 : 33 %
 *
 * Écarts documentés (placeholder/pilot) : pas d'impôt PROVINCIAL, de
 * crédits d'impôt, de plafonds RPC/AE (annuel), ni d'exemption personnelle
 * de base — voir CA_COMPLIANCE.md.
 */
class GoldenCaPayrollTest extends TestCase
{
    private function rules(): CanadaPayrollRules
    {
        return new CanadaPayrollRules;
    }

    public function test_golden_ca_minimum_wage_2999(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1-3), brut = min. fédéral approx.
        // 2 999 CAD/mois :
        //   RPC = 2 999 × 5,95 % = 178,44 · AE = 2 999 × 1,66 % = 49,78
        //   Cotisations = 228,22
        //   Assiette = 2 770,78 → annuel 33 249,36
        //   IR fédéral = 33 249,36 × 15 % (tranche 0–55 867) = 4 987,40
        //     → mensuel 415,62
        //   Net = 2 999 − 228,22 − 415,62 = 2 355,16
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(2999.0, $rules);

        $this->assertSame(228.22, $breakdown['social']['employee']);
        $this->assertSame(415.62, $breakdown['income_tax']);
        $this->assertSame(2355.16, $breakdown['net_salary']);
    }

    public function test_golden_ca_cadre_moyen_5000(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1), brut 5 000 CAD/mois :
        //   RPC = 297,50 · AE = 83,00 → Cotisations = 380,50
        //   Assiette = 4 619,50 → annuel 55 434,00
        //   IR fédéral = 55 434 × 15 % = 8 315,10 → mensuel 692,93
        //   Net = 5 000 − 380,50 − 692,93 = 3 926,57
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(5000.0, $rules);

        $this->assertSame(380.5, $breakdown['social']['employee']);
        $this->assertSame(692.93, $breakdown['income_tax']);
        $this->assertSame(3926.57, $breakdown['net_salary']);
    }

    public function test_golden_ca_haut_salaire_10000(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1), brut 10 000 CAD/mois :
        //   RPC = 595,00 · AE = 166,00 → Cotisations = 761,00
        //   Assiette = 9 239 → annuel 110 868,00
        //   IR fédéral progressif = 55 867 × 15 % = 8 380,05
        //     + 55 001 × 20,5 % (55 868–110 868) = 11 275,21
        //     → 19 655,26 → mensuel 1 637,94
        //   Net = 10 000 − 761 − 1 637,94 = 7 601,06
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(10000.0, $rules);

        $this->assertSame(761.0, $breakdown['social']['employee']);
        $this->assertSame(1637.94, $breakdown['income_tax']);
        $this->assertSame(7601.06, $breakdown['net_salary']);
    }
}
