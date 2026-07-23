<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PA2-COUNTRY-011: dedicated test coverage for PayrollCalculator's default
 * country/currency wiring (getRules()), explicitly covering the DZ, FR, TR,
 * CEMAC, CEDEAO and CA cases named in the acceptance criteria.
 *
 * PayrollCalculator::getRules() is what actually decides, per country code,
 * which CountryRulesInterface implementation (and therefore which currency,
 * via that implementation's currency()) a payroll run uses. This complements
 * PayrollCountryRulesTest (which exercises each CountryRulesInterface
 * implementation directly) by locking in the wiring/lookup layer itself,
 * including the documented gaps (CEDEAO members other than SN, and Canada)
 * where no rules exist yet per docs/PLAN_ACTION2/16_LIMITES_LEGALES_REGLES_PAYS.md.
 */
class PayrollCalculatorCountryRulesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: class-string<object>, 2: string}>
     */
    public static function supportedCountryCodesProvider(): array
    {
        return [
            'Algeria (DZ)' => ['DZ', AlgeriaPayrollRules::class, 'DZD'],
            'France (FR)' => ['FR', FrancePayrollRules::class, 'EUR'],
            'Turkey (TR)' => ['TR', TurkeyPayrollRules::class, 'TRY'],
            // CEMAC zone: single CemacPayrollRules class, scoped per member
            // state, registered under each member's own ISO country code.
            'Cameroon (CM, CEMAC)' => ['CM', CemacPayrollRules::class, 'XAF'],
            'Central African Republic (CF, CEMAC)' => ['CF', CemacPayrollRules::class, 'XAF'],
            'Chad (TD, CEMAC)' => ['TD', CemacPayrollRules::class, 'XAF'],
            'Congo (CG, CEMAC)' => ['CG', CemacPayrollRules::class, 'XAF'],
            'Gabon (GA, CEMAC)' => ['GA', CemacPayrollRules::class, 'XAF'],
            'Equatorial Guinea (GQ, CEMAC)' => ['GQ', CemacPayrollRules::class, 'XAF'],
        ];
    }

    /**
     * @param  class-string<object>  $expectedRulesClass
     */
    #[DataProvider('supportedCountryCodesProvider')]
    public function test_default_rules_map_resolves_supported_country_codes_to_the_expected_class_and_currency(
        string $countryCode,
        string $expectedRulesClass,
        string $expectedCurrency
    ): void {
        $calculator = new PayrollCalculator;

        $rules = $calculator->getRules($countryCode);

        self::assertInstanceOf($expectedRulesClass, $rules);
        self::assertSame($countryCode, $rules->countryCode());
        self::assertSame($expectedCurrency, $rules->currency());
    }

    /**
     * CEMAC members must each resolve to their own scoped instance (correct
     * country code / minimum wage / timezone), not all six sharing a single
     * Cameroon-defaulted instance.
     */
    public function test_cemac_members_resolve_to_distinct_scoped_instances(): void
    {
        $calculator = new PayrollCalculator;

        $gabon = $calculator->getRules('GA');
        $chad = $calculator->getRules('TD');

        self::assertInstanceOf(CemacPayrollRules::class, $gabon);
        self::assertInstanceOf(CemacPayrollRules::class, $chad);
        self::assertSame('GA', $gabon->countryCode());
        self::assertSame('TD', $chad->countryCode());
        self::assertNotSame($gabon->minimumWage(), $chad->minimumWage());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsupportedCountryCodesProvider(): array
    {
        return [
            // CEDEAO/UEMOA members other than Senegal: CountryDefaults
            // exposes currency/timezone metadata for these, but no
            // CountryRulesInterface implementation exists yet (PA2-COUNTRY-008,
            // not delivered). Documented explicitly in
            // docs/PLAN_ACTION2/16_LIMITES_LEGALES_REGLES_PAYS.md.
            'Cote d Ivoire (CI, CEDEAO)' => ['CI'],
            'Mali (ML, CEDEAO)' => ['ML'],
            'Burkina Faso (BF, CEDEAO)' => ['BF'],
            'Benin (BJ, CEDEAO)' => ['BJ'],
            'Togo (TG, CEDEAO)' => ['TG'],
            'Niger (NE, CEDEAO)' => ['NE'],
            // Canada: CountryDefaults exposes currency/timezone metadata,
            // but no CountryRulesInterface implementation exists yet
            // (PA2-COUNTRY-009, not delivered).
            'Canada (CA)' => ['CA'],
        ];
    }

    /**
     * Locks in the current documented behavior for countries that
     * CountryDefaults knows about but PayrollCalculator does not: a payroll
     * run must fail loudly with InvalidArgumentException rather than
     * silently using the wrong country's rules or a default currency.
     */
    #[DataProvider('unsupportedCountryCodesProvider')]
    public function test_default_rules_map_rejects_country_codes_without_payroll_rules(string $countryCode): void
    {
        $calculator = new PayrollCalculator;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No payroll rules for country: {$countryCode}");

        $calculator->getRules($countryCode);
    }

    /**
     * Senegal is the one CEDEAO/UEMOA member state that does have a
     * dedicated CountryRulesInterface implementation today (SenegalPayrollRules),
     * unlike its neighbours in unsupportedCountryCodesProvider() above.
     */
    public function test_senegal_is_the_one_cedeao_member_with_supported_rules(): void
    {
        $calculator = new PayrollCalculator;

        $rules = $calculator->getRules('SN');

        self::assertSame('SN', $rules->countryCode());
        self::assertSame('XOF', $rules->currency());
    }

    /**
     * An explicitly injected rules map (as PayrollCalculator's constructor
     * supports for dependency injection/testing) must be used verbatim
     * instead of falling back to the built-in default map.
     */
    public function test_explicit_rules_map_overrides_the_default_map(): void
    {
        $calculator = new PayrollCalculator([new AlgeriaPayrollRules]);

        self::assertInstanceOf(AlgeriaPayrollRules::class, $calculator->getRules('DZ'));

        $this->expectException(\InvalidArgumentException::class);
        $calculator->getRules('FR');
    }
}
