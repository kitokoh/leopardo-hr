<?php

namespace Tests\Unit;

use App\Services\Payroll\CountryRules\AlgeriaPayrollRules;
use App\Services\Payroll\CountryRules\FrancePayrollRules;
use App\Services\Payroll\CountryRules\MoroccoPayrollRules;
use App\Services\Payroll\CountryRules\SenegalPayrollRules;
use App\Services\Payroll\CountryRules\TunisiaPayrollRules;
use App\Services\Payroll\CountryRules\TurkeyPayrollRules;
use PHPUnit\Framework\TestCase;

class PayrollCountryRulesTest extends TestCase
{
    public function test_country_rules_expose_expected_social_contributions(): void
    {
        $rules = [
            'DZ' => [new AlgeriaPayrollRules(), 90.0, 260.0],
            'MA' => [new MoroccoPayrollRules(), 67.4, 130.9],
            'TN' => [new TunisiaPayrollRules(), 91.8, 165.7],
            'FR' => [new FrancePayrollRules(), 170.3, 300.0],
            'TR' => [new TurkeyPayrollRules(), 150.0, 225.0],
            'SN' => [new SenegalPayrollRules(), 56.0, 114.0],
        ];

        foreach ($rules as $countryCode => [$countryRules, $expectedEmployeeCharge, $expectedEmployerCharge]) {
            self::assertSame($countryCode, $countryRules->countryCode());

            $charges = $countryRules->calculateSocialCharges(1000);

            self::assertEqualsWithDelta($expectedEmployeeCharge, $charges['employee'], 0.01);
            self::assertEqualsWithDelta($expectedEmployerCharge, $charges['employer'], 0.01);
            self::assertNotEmpty($countryRules->socialContributions());
        }
    }

    public function test_progressive_income_tax_rules_are_applied_at_slab_edges(): void
    {
        self::assertSame(0.0, (new TunisiaPayrollRules())->calculateIncomeTax(5000 / 12));
        self::assertSame(21.67, (new TunisiaPayrollRules())->calculateIncomeTax(6000 / 12));
        self::assertSame(0.0, (new FrancePayrollRules())->calculateIncomeTax(11294 / 12));
        self::assertSame(0.06, (new FrancePayrollRules())->calculateIncomeTax(11300 / 12));
        self::assertSame(1375.0, (new TurkeyPayrollRules())->calculateIncomeTax(110000 / 12));
        self::assertSame(0.0, (new SenegalPayrollRules())->calculateIncomeTax(630000 / 12));
    }

    public function test_algeria_irg_applies_monthly_progressive_tax_then_abatement(): void
    {
        $rules = new AlgeriaPayrollRules();

        self::assertSame(0.0, $rules->calculateIncomeTax(20000));
        self::assertSame(5800.0, $rules->calculateIncomeTax(50000));
    }

    public function test_morocco_uses_annual_ir_with_fixed_deduction(): void
    {
        $rules = new MoroccoPayrollRules();

        self::assertSame(0.0, $rules->calculateIncomeTax(2500));
        self::assertSame(333.33, $rules->calculateIncomeTax(5000));
    }
}
