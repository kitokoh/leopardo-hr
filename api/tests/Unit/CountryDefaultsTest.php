<?php

namespace Tests\Unit;

use App\Support\CountryDefaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PA2-COUNTRY-001: regression coverage for the country catalogue exposed via
 * CountryDefaults. The acceptance criteria explicitly lists DZ, MA, TN, FR,
 * TR, CEMAC, CEDEAO and CA; this test locks in that every one of those
 * country codes actually resolves to a real entry instead of silently
 * falling back to the DZ default.
 */
class CountryDefaultsTest extends TestCase
{
    /**
     * @return array<int, array{0: string}>
     */
    public static function acceptanceCriteriaCountryCodesProvider(): array
    {
        return [
            'Algeria (DZ)' => ['DZ'],
            'Morocco (MA)' => ['MA'],
            'Tunisia (TN)' => ['TN'],
            'France (FR)' => ['FR'],
            'Turkey (TR)' => ['TR'],
            // CEMAC zone members.
            'Cameroon (CM, CEMAC)' => ['CM'],
            'Central African Republic (CF, CEMAC)' => ['CF'],
            'Chad (TD, CEMAC)' => ['TD'],
            'Congo (CG, CEMAC)' => ['CG'],
            'Gabon (GA, CEMAC)' => ['GA'],
            'Equatorial Guinea (GQ, CEMAC)' => ['GQ'],
            // CEDEAO/UEMOA members present in the catalogue.
            'Senegal (SN, CEDEAO)' => ['SN'],
            'Cote d Ivoire (CI, CEDEAO)' => ['CI'],
            'Mali (ML, CEDEAO)' => ['ML'],
            'Burkina Faso (BF, CEDEAO)' => ['BF'],
            'Benin (BJ, CEDEAO)' => ['BJ'],
            'Togo (TG, CEDEAO)' => ['TG'],
            'Niger (NE, CEDEAO)' => ['NE'],
            // Canada, explicitly required by the PA2-COUNTRY-001 acceptance
            // criteria but missing until this fix.
            'Canada (CA)' => ['CA'],
        ];
    }

    #[DataProvider('acceptanceCriteriaCountryCodesProvider')]
    public function test_acceptance_criteria_country_codes_resolve_to_their_own_entry(string $countryCode): void
    {
        $result = CountryDefaults::for($countryCode);

        self::assertSame($countryCode, $result['country']);
        self::assertNotSame('', $result['currency']);
        self::assertNotSame('', $result['timezone']);
        self::assertNotSame('', $result['language']);
        self::assertNotSame('', $result['label']);
    }

    public function test_canada_exposes_expected_currency_and_timezone(): void
    {
        $result = CountryDefaults::for('CA');

        self::assertSame('CA', $result['country']);
        self::assertSame('CAD', $result['currency']);
        self::assertSame('America/Toronto', $result['timezone']);
        self::assertSame('en', $result['language']);
    }

    public function test_for_is_case_insensitive_and_trims_input(): void
    {
        $result = CountryDefaults::for(' ca ');

        self::assertSame('CA', $result['country']);
    }

    public function test_unknown_or_empty_country_falls_back_to_algeria_default(): void
    {
        self::assertSame('DZ', CountryDefaults::for(null)['country']);
        self::assertSame('DZ', CountryDefaults::for('')['country']);
        self::assertSame('DZ', CountryDefaults::for('ZZ')['country']);
    }

    public function test_all_includes_canada_exactly_once(): void
    {
        $countryCodes = array_column(CountryDefaults::all(), 'country');

        self::assertSame(1, count(array_filter($countryCodes, static fn (string $code): bool => $code === 'CA')));
    }
}
