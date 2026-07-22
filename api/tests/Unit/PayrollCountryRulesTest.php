<?php

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
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
    /**
     * PA2-COUNTRY-005: Morocco and Tunisia must expose the same country
     * metadata contract (timezone, weekly rest days, supported pay cycles,
     * public-holiday source disclosure, confidence level) that the other
     * CountryRulesInterface implementations already provide, so platform
     * admin/company provisioning can rely on it without special-casing MA/TN.
     */
    public function test_morocco_and_tunisia_expose_country_metadata_for_provisioning(): void
    {
        $morocco = new MoroccoPayrollRules;

        self::assertSame('Africa/Casablanca', $morocco->timezone());
        self::assertSame([7], $morocco->weeklyRestDays());
        self::assertSame(['daily', 'weekly', 'monthly'], $morocco->supportedPayCycles());
        self::assertStringContainsString('placeholder', $morocco->publicHolidaysSource());
        self::assertSame('pilot', $morocco->confidenceLevel());

        $tunisia = new TunisiaPayrollRules;

        self::assertSame('Africa/Tunis', $tunisia->timezone());
        self::assertSame([7], $tunisia->weeklyRestDays());
        self::assertSame(['daily', 'weekly', 'monthly'], $tunisia->supportedPayCycles());
        self::assertStringContainsString('placeholder', $tunisia->publicHolidaysSource());
        self::assertSame('pilot', $tunisia->confidenceLevel());
    }

    /**
     * Every CountryRulesInterface implementation must expose the country
     * metadata contract, not just Morocco/Tunisia — regression guard so a
     * future country addition can't skip it silently.
     */
    public function test_every_country_rules_implementation_exposes_country_metadata(): void
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
            self::assertNotSame('', $rules->timezone(), $rules->countryCode().': timezone must not be empty');
            self::assertNotEmpty($rules->weeklyRestDays(), $rules->countryCode().': weeklyRestDays must not be empty');
            self::assertNotEmpty($rules->supportedPayCycles(), $rules->countryCode().': supportedPayCycles must not be empty');
            self::assertNotSame('', $rules->publicHolidaysSource(), $rules->countryCode().': publicHolidaysSource must not be empty');
            self::assertContains(
                $rules->confidenceLevel(),
                ['production', 'pilot', 'placeholder'],
                $rules->countryCode().': confidenceLevel must be one of the documented values'
            );
        }
    }

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
     * PA2-COUNTRY-007: CEMAC zone (CM, CF, TD, CG, GA, GQ) must be covered by
     * a single CemacPayrollRules class, scoped per member state via
     * forMemberCountry(), so payroll can be run for any of the six members
     * with the correct ISO country code, timezone and minimum wage.
     */
    public function test_cemac_defaults_to_cameroon_and_exposes_member_country_codes(): void
    {
        $default = new CemacPayrollRules;

        self::assertSame('CM', $default->countryCode());
        self::assertSame('XAF', $default->currency());
        self::assertSame('Africa/Douala', $default->timezone());
        self::assertSame(['CM', 'CF', 'TD', 'CG', 'GA', 'GQ'], CemacPayrollRules::MEMBER_COUNTRY_CODES);
    }

    public function test_cemac_for_member_country_scopes_currency_timezone_and_minimum_wage_per_member(): void
    {
        $expected = [
            'CM' => ['Africa/Douala', 41875.0],
            'CF' => ['Africa/Bangui', 35000.0],
            'TD' => ['Africa/Ndjamena', 60000.0],
            'CG' => ['Africa/Brazzaville', 90000.0],
            'GA' => ['Africa/Libreville', 150000.0],
            'GQ' => ['Africa/Malabo', 128000.0],
        ];

        foreach ($expected as $memberCode => [$timezone, $minimumWage]) {
            $rules = (new CemacPayrollRules)->forMemberCountry($memberCode);

            self::assertSame($memberCode, $rules->countryCode());
            self::assertSame('XAF', $rules->currency());
            self::assertSame($timezone, $rules->timezone());
            self::assertSame($minimumWage, $rules->minimumWage());
            self::assertSame([7], $rules->weeklyRestDays());
            self::assertSame(['monthly'], $rules->supportedPayCycles());
            self::assertSame('placeholder', $rules->confidenceLevel());
            self::assertStringContainsString('placeholder', $rules->publicHolidaysSource());
            self::assertNotEmpty($rules->socialContributions());
        }
    }

    public function test_cemac_for_member_country_ignores_unknown_codes(): void
    {
        $rules = (new CemacPayrollRules)->forMemberCountry('XX');

        self::assertSame('CM', $rules->countryCode());
    }

    public function test_cemac_calculates_social_charges_and_progressive_income_tax(): void
    {
        $rules = (new CemacPayrollRules)->forMemberCountry('GA');

        $charges = $rules->calculateSocialCharges(1000);
        self::assertEqualsWithDelta(42.0, $charges['employee'], 0.01);
        self::assertEqualsWithDelta(162.0, $charges['employer'], 0.01);

        self::assertSame(0.0, $rules->calculateIncomeTax(500000 / 12));
        self::assertSame(4166.67, $rules->calculateIncomeTax(1000000 / 12));
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
