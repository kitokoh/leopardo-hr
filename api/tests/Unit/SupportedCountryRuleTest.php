<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\SupportedCountry;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Issue #1951 — la règle « pays supporté » valide le registre d'affichage
 * ET la disponibilité des règles de paie (plus de divergence : GB/US étaient
 * acceptés à la validation puis échouaient au calcul).
 *
 * Testé via `Validator::make` (chemin d'invocation réel de Laravel) :
 * la règle chaîne `$fail('clé')->translate([...])` — un test unitaire nu
 * appelant `validate()` avec une closure maison ne reproduit pas le
 * `PotentiallyTranslatedString` fourni par le framework.
 */
class SupportedCountryRuleTest extends TestCase
{
    private function assertFails(string $value): void
    {
        $validator = Validator::make(
            ['country_code' => $value],
            ['country_code' => [new SupportedCountry]],
        );

        $this->assertTrue(
            $validator->fails(),
            "Expected '{$value}' to be rejected.",
        );
    }

    private function assertPasses(string $value): void
    {
        $validator = Validator::make(
            ['country_code' => $value],
            ['country_code' => [new SupportedCountry]],
        );

        $this->assertFalse(
            $validator->fails(),
            "Expected '{$value}' to be accepted.",
        );
    }

    public function test_country_with_payroll_rules_is_accepted(): void
    {
        // Depuis #5255, GB/US ont leurs règles de paie (pilot 2026-27) : la
        // divergence #1951 (« display sans règles ») est fermée.
        foreach (['DZ', 'CI', 'SN', 'CM', 'BF', 'CA', 'FR', 'MA', 'TN', 'TR', 'GB', 'US'] as $code) {
            $this->assertPasses($code);
        }
    }

    public function test_country_without_payroll_rules_is_rejected(): void
    {
        // Aucun code CountryDefaults ne manque plus de règles de paie depuis
        // #5255 — la garde reste vérifiée avec un code TOTALEMENT inconnu du
        // registre (jamais affiché ni résolu) : rejeté par la règle (#1951).
        foreach (['XX', 'ZZ'] as $code) {
            $this->assertFails($code);
        }
    }

    public function test_unknown_country_is_rejected(): void
    {
        // (La chaîne vide relève de `required`, pas de la règle pays.)
        foreach (['XX', 'ZZ'] as $code) {
            $this->assertFails($code);
        }
    }

    public function test_lowercase_country_is_normalized_by_resolver(): void
    {
        // Le resolver uppercasse ; les codes CEDEAO/CEMAC membres restent
        // résolubles quelle que soit la casse.
        $this->assertPasses('ci');
        $this->assertPasses('DZ');
    }
}
