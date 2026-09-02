<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PaySlipValueCalculator;
use Tests\TestCase;

/**
 * Issue #6727 — le détail `income_tax_by_slab` doit sommer vers l'impôt réel
 * (`income_tax`) pour les pays à abattement frais professionnels.
 *
 * Repro prod (2026-09-01, brut 300 000) :
 *   SN : income_tax 39 460 vs Σ tranches 61 960 (Δ +22 500 = abattement 30 %
 *        plafonné 75 000 FCFA/mois)
 *   DZ : income_tax 75 190 vs Σ 76 690 (Δ +1 500 = abattement 40 % plafonné
 *        18 000 DZD/an)
 *   MA : income_tax 108 338,12 vs Σ 109 288,13 (Δ +950 = abattement annuel)
 *
 * Contrat : Σ slab.tax == income_tax (à 0,01 près) pour SN/DZ/MA.
 */
class PaySlipValueCalculatorSlabTaxBreakdownTest extends TestCase
{
    private const GROSS = 300000.0;

    public function test_senegal_slab_detail_sums_to_income_tax(): void
    {
        $this->assertSlabDetailConverges(new SenegalPayrollRules, 'SN');
    }

    public function test_algeria_slab_detail_sums_to_income_tax(): void
    {
        $this->assertSlabDetailConverges(new AlgeriaPayrollRules, 'DZ');
    }

    public function test_morocco_slab_detail_sums_to_income_tax(): void
    {
        $this->assertSlabDetailConverges(new MoroccoPayrollRules, 'MA');
    }

    private function assertSlabDetailConverges($rules, string $country): void
    {
        $calculator = new PaySlipValueCalculator;

        $breakdown = $calculator->computeNetBreakdown(self::GROSS, $rules);
        $incomeTax = $breakdown['income_tax'];

        $slabs = $calculator->slabTaxBreakdown($rules, self::GROSS, $breakdown['taxable_gross'], $incomeTax);

        $this->assertNotEmpty($slabs, "{$country}: le détail par tranche ne doit pas être vide.");
        $total = array_sum(array_column($slabs, 'tax'));

        $this->assertEqualsWithDelta(
            $incomeTax,
            $total,
            0.01,
            "{$country}: Σ income_tax_by_slab.tax ({$total}) doit converger vers income_tax ({$incomeTax})."
        );
    }
}
