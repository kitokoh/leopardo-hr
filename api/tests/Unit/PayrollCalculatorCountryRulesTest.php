<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
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
 * CEDEAO members and Canada are now covered too (PA2-COUNTRY-008/009),
 * so this test locks in the currently-supported set rather than the
 * previously-documented gaps.
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
            // CEDEAO/UEMOA zone: single CedeaoPayrollRules class, scoped per
            // member state, registered under each member's own ISO country
            // code (Senegal excluded: it has its own dedicated class, see
            // test_senegal_is_the_one_cedeao_member_with_supported_rules()).
            'Cote d Ivoire (CI, CEDEAO)' => ['CI', CedeaoPayrollRules::class, 'XOF'],
            'Mali (ML, CEDEAO)' => ['ML', CedeaoPayrollRules::class, 'XOF'],
            'Burkina Faso (BF, CEDEAO)' => ['BF', CedeaoPayrollRules::class, 'XOF'],
            'Benin (BJ, CEDEAO)' => ['BJ', CedeaoPayrollRules::class, 'XOF'],
            'Togo (TG, CEDEAO)' => ['TG', CedeaoPayrollRules::class, 'XOF'],
            'Niger (NE, CEDEAO)' => ['NE', CedeaoPayrollRules::class, 'XOF'],
            // Canada: single ISO country code CA, federal defaults.
            'Canada (CA)' => ['CA', CanadaPayrollRules::class, 'CAD'],
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
     * Locks in the current documented behavior for a country code that
     * CountryDefaults does not know about at all: a payroll run must fail
     * loudly with InvalidArgumentException rather than silently using the
     * wrong country's rules or a default currency.
     */
    public function test_default_rules_map_rejects_country_codes_without_payroll_rules(): void
    {
        $calculator = new PayrollCalculator;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No payroll rules for country: XX');

        $calculator->getRules('XX');
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
