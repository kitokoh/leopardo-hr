<?php

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use PHPUnit\Framework\TestCase;

class PayrollCountryRulesTest extends TestCase
{
    public function test_country_rules_expose_expected_social_contributions(): void
    {
        $rules = [
            'DZ' => [new AlgeriaPayrollRules, 90.0, 260.0],
            'MA' => [new MoroccoPayrollRules, 67.4, 130.9],
            'TN' => [new TunisiaPayrollRules, 91.8, 165.7],
            'FR' => [new FrancePayrollRules, 170.3, 300.0],
            'TR' => [new TurkeyPayrollRules, 150.0, 225.0],
            'SN' => [new SenegalPayrollRules, 56.0, 114.0],
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
        self::assertSame(0.0, (new TunisiaPayrollRules)->calculateIncomeTax(5000 / 12));
        self::assertSame(21.67, (new TunisiaPayrollRules)->calculateIncomeTax(6000 / 12));
        self::assertSame(0.0, (new FrancePayrollRules)->calculateIncomeTax(11294 / 12));
        self::assertSame(0.06, (new FrancePayrollRules)->calculateIncomeTax(11300 / 12));
        self::assertSame(1375.0, (new TurkeyPayrollRules)->calculateIncomeTax(110000 / 12));
        self::assertSame(0.0, (new SenegalPayrollRules)->calculateIncomeTax(630000 / 12));
    }

    public function test_algeria_irg_applies_monthly_progressive_tax_then_abatement(): void
    {
        $rules = new AlgeriaPayrollRules;

        self::assertSame(0.0, $rules->calculateIncomeTax(20000));
        self::assertSame(5800.0, $rules->calculateIncomeTax(50000));
    }

    public function test_morocco_uses_annual_ir_with_fixed_deduction(): void
    {
        $rules = new MoroccoPayrollRules;

        self::assertSame(0.0, $rules->calculateIncomeTax(2500));
        self::assertSame(333.33, $rules->calculateIncomeTax(5000));
    }

    /**
     * Regression test for the TaxSlab/PayrollCalculator disconnect (PA2-ARCH-001):
     * outside a booted Laravel app (no facade root, no DB — same environment as
     * every other test in this pure PHPUnit\Framework\TestCase file),
     * taxSlabs()/calculateIncomeTax() must keep falling back to the hardcoded
     * defaultTaxSlabs() instead of fataling, so results stay identical to
     * before the DB-backed lookup was introduced.
     */
    public function test_tax_slabs_fall_back_to_hardcoded_defaults_without_a_booted_app(): void
    {
        $rules = new AlgeriaPayrollRules;

        self::assertSame(6, count($rules->taxSlabs()));
        self::assertSame(0.0, $rules->calculateIncomeTax(20000));
        self::assertSame(5800.0, $rules->calculateIncomeTax(50000));
    }

    /**
     * forCompany() must return a new scoped instance without mutating the
     * original rules object (PayrollCalculator relies on this to scope
     * company-specific TaxSlab overrides per payroll run without leaking
     * state across companies/requests).
     */
    public function test_for_company_returns_a_scoped_clone_without_mutating_the_original(): void
    {
        $rules = new TunisiaPayrollRules;

        $scoped = $rules->forCompany('11111111-1111-1111-1111-111111111111');

        self::assertNotSame($rules, $scoped);
        self::assertInstanceOf(TunisiaPayrollRules::class, $scoped);
        self::assertSame('TN', $scoped->countryCode());
        // No DB/tax_slabs table available in this pure unit-test environment,
        // so both the original and the scoped clone fall back to the same
        // hardcoded defaults — confirms forCompany() doesn't break the
        // no-DB fallback path.
        self::assertSame($rules->taxSlabs(), $scoped->taxSlabs());
    }

    /**
     * PA2-COUNTRY-004: Algeria's rules must expose the standard weekend
     * (Friday+Saturday, not the generic Sunday-only default other countries
     * use) plus the statutory 40h/week overtime threshold and its premium
     * tier, so payroll/attendance can compute overtime pay without
     * hardcoding Algeria-specific values elsewhere.
     */
    public function test_algeria_exposes_weekend_and_overtime_rules(): void
    {
        $rules = new AlgeriaPayrollRules;

        self::assertSame([5, 6], $rules->weeklyRestDays());
        self::assertSame(['daily', 'weekly', 'monthly'], $rules->supportedPayCycles());
        self::assertSame('Africa/Algiers', $rules->timezone());
        self::assertSame(40.0, $rules->overtimeThresholdWeeklyHours());

        $tiers = $rules->overtimeRateTiers();
        self::assertNotEmpty($tiers);
        self::assertNull($tiers[array_key_last($tiers)]['up_to_hours']);
        self::assertSame(1.5, $tiers[0]['multiplier']);
    }

    /**
     * Every CountryRulesInterface implementation must expose the full
     * country-metadata + overtime contract, not just Algeria — regression
     * guard so a future country addition can't skip it silently.
     */
    public function test_every_country_rules_implementation_exposes_the_full_contract(): void
    {
        $allRules = [
            new AlgeriaPayrollRules,
            new MoroccoPayrollRules,
            new TunisiaPayrollRules,
            new FrancePayrollRules,
            new TurkeyPayrollRules,
            new SenegalPayrollRules,
        ];

        foreach ($allRules as $rules) {
            $label = $rules->countryCode();

            self::assertNotSame('', $rules->timezone(), $label.': timezone must not be empty');
            self::assertNotEmpty($rules->weeklyRestDays(), $label.': weeklyRestDays must not be empty');
            self::assertNotEmpty($rules->supportedPayCycles(), $label.': supportedPayCycles must not be empty');
            self::assertNotSame('', $rules->publicHolidaysSource(), $label.': publicHolidaysSource must not be empty');
            self::assertContains(
                $rules->confidenceLevel(),
                ['production', 'pilot', 'placeholder'],
                $label.': confidenceLevel must be one of the documented values'
            );
            self::assertGreaterThan(0.0, $rules->overtimeThresholdWeeklyHours(), $label.': overtimeThresholdWeeklyHours must be positive');

            $tiers = $rules->overtimeRateTiers();
            self::assertNotEmpty($tiers, $label.': overtimeRateTiers must not be empty');
            self::assertNull(
                $tiers[array_key_last($tiers)]['up_to_hours'],
                $label.': the last overtime tier must be unbounded (up_to_hours = null)'
            );
            foreach ($tiers as $tier) {
                self::assertGreaterThan(1.0, $tier['multiplier'], $label.': overtime multiplier must be > 1.0 (a real premium)');
            }
        }
    }
}
