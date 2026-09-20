<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Infrastructure\Services\AccountingSettingsDefaults;
use Tests\TestCase;

/**
 * Issue #7928 — mentions légales de facture par pays : couverture étendue
 * CEMAC (CM GA CG TD CF GQ), CEDEAO (BF ML TG BJ NE), CA, GB, US, et
 * non-régression des 7 pays historiques (DZ MA TN SN CI FR TR).
 */
class AccountingLegalMentionsCoverageTest extends TestCase
{
    private function mentionFor(string $country): ?string
    {
        $mentions = AccountingSettingsDefaults::for($country)['legal_mentions'];

        return is_string($mentions) ? $mentions : null;
    }

    public function test_all_expected_countries_have_a_default_legal_mention(): void
    {
        $expected = [
            'DZ', 'MA', 'TN', 'SN', 'CI', 'FR', 'TR',
            'CM', 'GA', 'CG', 'TD', 'CF', 'GQ',
            'BF', 'ML', 'TG', 'BJ', 'NE',
            'CA', 'GB', 'US',
        ];

        $this->assertEqualsCanonicalizing($expected, AccountingSettingsDefaults::legalMentionCountries());

        foreach ($expected as $country) {
            $this->assertNotNull($this->mentionFor($country), $country);
        }
    }

    public function test_cemac_countries_carry_rccm_and_their_tax_identifier(): void
    {
        // NIU : Cameroun (DGI) et Congo (niu.cg) ; NIF : GA/TD/CF/GQ.
        foreach (['CM' => 'NIU {niu}', 'GA' => 'NIF {nif}', 'CG' => 'NIU {niu}', 'TD' => 'NIF {nif}', 'CF' => 'NIF {nif}', 'GQ' => 'NIF {nif}'] as $country => $identifier) {
            $mention = (string) $this->mentionFor($country);

            $this->assertStringContainsString('RCCM {rccm}', $mention, $country);
            $this->assertStringContainsString($identifier, $mention, $country);
            $this->assertStringContainsString('XAF', $mention, $country);
        }
    }

    public function test_cedeao_countries_carry_rccm_and_ifu_or_nif(): void
    {
        // IFU : Burkina (DGI) et Bénin (ifu.impots.bj) ; NIF : ML/TG/NE.
        foreach (['BF' => 'IFU {ifu}', 'ML' => 'NIF {nif}', 'TG' => 'NIF {nif}', 'BJ' => 'IFU {ifu}', 'NE' => 'NIF {nif}'] as $country => $identifier) {
            $mention = (string) $this->mentionFor($country);

            $this->assertStringContainsString('RCCM {rccm}', $mention, $country);
            $this->assertStringContainsString($identifier, $mention, $country);
            $this->assertStringContainsString('XOF', $mention, $country);
        }
    }

    public function test_canada_uk_and_us_mentions(): void
    {
        $ca = (string) $this->mentionFor('CA');
        $this->assertStringContainsString('BN {bn}', $ca);
        $this->assertStringContainsString('TPS/TVH {gst_hst}', $ca);
        $this->assertStringContainsString('TVQ {tvq}', $ca);

        $gb = (string) $this->mentionFor('GB');
        $this->assertStringContainsString('Company No {company_no}', $gb);
        $this->assertStringContainsString('VAT Registration No {vat_no}', $gb);

        $this->assertSame('EIN {ein}', $this->mentionFor('US'));
    }

    public function test_historical_seven_countries_are_unchanged(): void
    {
        $golden = [
            'DZ' => 'RC {rc} — NIF {nif} — Article 54 de la loi de finances (TVA) — Capital social : {capital} DZD',
            'MA' => 'RC {rc} — IF {if} — ICE {ice} — Patente : {patente} — Capital social : {capital} MAD',
            'TN' => 'Matricule fiscal {matricule} — Registre de commerce {rc} — Capital social : {capital} TND',
            'SN' => 'RCCM {rccm} — NINEA {ninea} — Capital social : {capital} XOF',
            'CI' => 'RCCM {rccm} — NIF {nif} — Capital social : {capital} XOF',
            'FR' => 'SIRET {siret} — TVA intracommunautaire : {tva_intra} — Capital social : {capital} EUR',
            'TR' => 'Vergi No {vergi_no} — Ticaret Sicil No {sicil_no} — Sermaye : {capital} TRY',
        ];

        foreach ($golden as $country => $mention) {
            $this->assertSame($mention, $this->mentionFor($country), $country);
        }
    }

    public function test_unknown_country_has_no_default_mention(): void
    {
        $this->assertNull($this->mentionFor('XX'));
    }
}
