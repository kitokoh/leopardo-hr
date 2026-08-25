<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Domain\Registries\AccountingChartOfAccounts;
use App\Support\CountryDefaults;
use Tests\TestCase;

/**
 * Issue #5422 — plan comptable par pays du module Comptabilité.
 *
 * Registre immuable : couverture de tous les pays CountryDefaults, familles
 * de comptes complètes ×21 pays, golden par référentiel (PCG, SYSCOHADA,
 * Tekdüzen, UK, US, CA) et cohérence de la méthode `for()`.
 */
class AccountingChartOfAccountsTest extends TestCase
{
    public function test_all_countrydefaults_countries_are_covered(): void
    {
        foreach (array_keys(CountryDefaults::all()) as $country) {
            $chart = AccountingChartOfAccounts::for($country);

            foreach (AccountingChartOfAccounts::ACCOUNT_FAMILIES as $family) {
                $this->assertArrayHasKey(
                    $family,
                    $chart,
                    "Plan comptable {$country} : famille {$family} manquante.",
                );
                $this->assertNotSame('', $chart[$family]['code'], "{$country}.{$family} : code vide.");
                $this->assertNotSame('', $chart[$family]['label'], "{$country}.{$family} : libellé vide.");
                $this->assertContains($chart[$family]['confidence'], ['production', 'pilot']);
            }
        }
    }

    public function test_pcg_family_golden_dz_ma_tn_fr(): void
    {
        foreach (['DZ', 'MA', 'TN', 'FR'] as $country) {
            $chart = AccountingChartOfAccounts::for($country);

            $this->assertSame('411', $chart['clients']['code'], $country);
            $this->assertSame('4457', $chart['vat_collected']['code'], $country);
            $this->assertSame('706', $chart['sales_revenue']['code'], $country);
            $this->assertSame('512', $chart['bank']['code'], $country);
            $this->assertSame('101', $chart['paid_in_capital']['code'], $country);
            $this->assertSame('production', $chart['clients']['confidence'], $country);
        }
    }

    public function test_ohada_golden_sn_ci_cm(): void
    {
        foreach (['SN', 'CI', 'CM'] as $country) {
            $chart = AccountingChartOfAccounts::for($country);

            $this->assertSame('521', $chart['bank']['code'], $country);
            $this->assertSame('571', $chart['cash']['code'], $country);
            $this->assertSame('44571', $chart['vat_collected']['code'], $country);
            $this->assertSame('44566', $chart['vat_deductible']['code'], $country);
            $this->assertSame('production', $chart['bank']['confidence'], $country);
        }
    }

    public function test_turkey_tekduzen_golden(): void
    {
        $chart = AccountingChartOfAccounts::for('TR');

        $this->assertSame('120', $chart['clients']['code']);
        $this->assertSame('391', $chart['vat_collected']['code']);
        $this->assertSame('600', $chart['sales_revenue']['code']);
        $this->assertSame('Alıcılar', $chart['clients']['label']);
        $this->assertSame('pilot', $chart['clients']['confidence']);
    }

    public function test_uk_golden(): void
    {
        $chart = AccountingChartOfAccounts::for('GB');

        $this->assertSame('1100', $chart['clients']['code']);
        $this->assertSame('Trade debtors', $chart['clients']['label']);
        $this->assertSame('2200', $chart['vat_collected']['code']);
        $this->assertSame('pilot', $chart['clients']['confidence']);
    }

    public function test_us_golden(): void
    {
        $chart = AccountingChartOfAccounts::for('US');

        $this->assertSame('1100', $chart['clients']['code']);
        $this->assertSame('Accounts receivable', $chart['clients']['label']);
        $this->assertSame('2300', $chart['vat_collected']['code']);
        $this->assertSame('pilot', $chart['clients']['confidence']);
    }

    public function test_canada_golden(): void
    {
        $chart = AccountingChartOfAccounts::for('CA');

        $this->assertSame('2250', $chart['vat_collected']['code']);
        $this->assertSame('GST/HST payable', $chart['vat_collected']['label']);
        $this->assertSame('pilot', $chart['clients']['confidence']);
    }

    public function test_unknown_country_falls_back_to_pcg_family(): void
    {
        $chart = AccountingChartOfAccounts::for('XX');

        $this->assertSame('411', $chart['clients']['code']);
        $this->assertSame('production', $chart['clients']['confidence']);
    }

    public function test_assert_complete_passes_for_all_supported_countries(): void
    {
        foreach (AccountingChartOfAccounts::supportedCountries() as $country) {
            AccountingChartOfAccounts::assertComplete($country);
            $this->assertTrue(true);
        }
    }
}
