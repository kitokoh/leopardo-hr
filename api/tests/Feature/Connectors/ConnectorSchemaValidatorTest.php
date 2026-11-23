<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use App\Modules\Connectors\Infrastructure\Services\ConnectorConnectionTester;
use App\Modules\Connectors\Infrastructure\Services\ConnectorSchemaValidator;
use App\Modules\Connectors\Domain\Exceptions\ConnectorSchemaViolationException;
use Tests\TestCase;

/**
 * #9838 (BC-14 INTEGRATION) — le socle qui rend l'onglet « Connecteurs »
 * dynamique : validation d'une saisie CONTRE le schéma du catalogue.
 *
 * Ce que ces tests verrouillent :
 *   1. un champ ABSENT du schéma est ignoré (on ne peut pas écrire une clé
 *      arbitraire dans `config` ou `secrets`) ;
 *   2. un champ `required` manquant est un 422 explicite ;
 *   3. le type est respecté (url, email, number, tel) ;
 *   4. un `select` est un champ FERMÉ : une valeur hors options est refusée ;
 *   5. les secrets et la config sont séparés correctement ;
 *   6. la validation IBAN (mod-97) est correcte — c'est le seul contrôle réel
 *      qu'on peut faire sur un compte bancaire sans API bancaire.
 */
class ConnectorSchemaValidatorTest extends TestCase
{
    private function entry(array $fields, string $category = 'website'): ConnectorCatalogEntry
    {
        $entry = new ConnectorCatalogEntry([
            'key' => 'test_connector',
            'category' => $category,
            'audience' => 'tenant',
            'label' => 'Connecteur de test',
            'is_active' => true,
        ]);
        $entry->field_schema = $fields;

        return $entry;
    }

    public function test_unknown_fields_are_ignored(): void
    {
        $entry = $this->entry([
            ['key' => 'site_url', 'label' => 'URL', 'type' => 'url', 'required' => true],
        ]);

        $result = (new ConnectorSchemaValidator())->validate(
            $entry,
            ['site_url' => 'https://exemple.com', 'champ_pirate' => 'valeur'],
            ['secret_pirate' => 'valeur'],
        );

        $this->assertSame(['site_url' => 'https://exemple.com'], $result['config']);
        $this->assertSame([], $result['secrets']);
    }

    public function test_required_field_missing_is_a_violation(): void
    {
        $entry = $this->entry([
            ['key' => 'site_url', 'label' => 'URL du site', 'type' => 'url', 'required' => true],
        ]);

        $this->expectException(ConnectorSchemaViolationException::class);

        (new ConnectorSchemaValidator())->validate($entry, [], []);
    }

    public function test_violation_lists_every_faulty_field(): void
    {
        $entry = $this->entry([
            ['key' => 'site_url', 'label' => 'URL du site', 'type' => 'url', 'required' => true],
            ['key' => 'contact', 'label' => 'E-mail de contact', 'type' => 'email', 'required' => true],
        ]);

        try {
            (new ConnectorSchemaValidator())->validate($entry, ['site_url' => 'pas-une-url'], []);
            $this->fail('Une violation était attendue.');
        } catch (ConnectorSchemaViolationException $exception) {
            $violations = $exception->violations();
            $this->assertCount(2, $violations);
            $this->assertStringContainsString('URL du site', $violations[0]);
            $this->assertStringContainsString('E-mail de contact', $violations[1]);
        }
    }

    public function test_select_rejects_value_outside_options(): void
    {
        $entry = $this->entry([
            ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'test', 'label' => 'Test'],
                ['value' => 'live', 'label' => 'Live'],
            ]],
        ]);

        $validator = new ConnectorSchemaValidator();

        $ok = $validator->validate($entry, ['mode' => 'live'], []);
        $this->assertSame(['mode' => 'live'], $ok['config']);

        $this->expectException(ConnectorSchemaViolationException::class);
        $validator->validate($entry, ['mode' => 'production'], []);
    }

    public function test_secrets_and_config_are_separated(): void
    {
        $entry = $this->entry([
            ['key' => 'site_url', 'label' => 'URL', 'type' => 'url', 'required' => true],
            ['key' => 'api_key', 'label' => 'Clé API', 'type' => 'password', 'secret' => true, 'required' => true],
        ]);

        $result = (new ConnectorSchemaValidator())->validate(
            $entry,
            ['site_url' => 'https://exemple.com'],
            ['api_key' => 'sk_test_123'],
        );

        $this->assertSame(['site_url' => 'https://exemple.com'], $result['config']);
        $this->assertSame(['api_key' => 'sk_test_123'], $result['secrets']);
    }

    public function test_empty_secret_is_not_required_to_be_resent(): void
    {
        // Write-only : un secret vide au PATCH signifie « conserver la valeur
        // en place ». Il ne doit donc pas être renvoyé dans la sortie, et ne
        // doit pas déclencher de violation s'il n'est pas requis.
        $entry = $this->entry([
            ['key' => 'api_key', 'label' => 'Clé API', 'type' => 'password', 'secret' => true, 'required' => false],
        ]);

        $result = (new ConnectorSchemaValidator())->validate($entry, [], ['api_key' => '']);

        $this->assertSame([], $result['secrets']);
    }

    public function test_iban_validation_uses_mod_97(): void
    {
        $tester = new ConnectorConnectionTester();

        // IBAN de test publics, valides par construction.
        $this->assertTrue($tester->isValidIban('FR1420041010050500013M02606'));
        $this->assertTrue($tester->isValidIban('DE89370400440532013000'));
        $this->assertTrue($tester->isValidIban('GB82WEST12345698765432'));

        // Une seule clé de contrôle fausse suffit à invalider.
        $this->assertFalse($tester->isValidIban('FR1420041010050500013M02607'));
        $this->assertFalse($tester->isValidIban('DE89370400440532013001'));
        $this->assertFalse($tester->isValidIban('pas-un-iban'));
        $this->assertFalse($tester->isValidIban(''));
    }

    public function test_bank_test_reports_invalid_iban_honestly(): void
    {
        $entry = $this->entry([
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'secret' => true, 'required' => true],
        ], 'bank');

        $result = (new ConnectorConnectionTester())->test(
            $entry,
            ['iban' => 'FR1420041010050500013M02607'],
            [],
        );

        $this->assertSame('invalid', $result['status']);
        $this->assertNotEmpty($result['checks']);
        $this->assertFalse($result['checks'][0]['ok']);
    }

    public function test_bank_test_validates_a_correct_iban(): void
    {
        $entry = $this->entry([
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'secret' => true, 'required' => true],
        ], 'bank');

        $result = (new ConnectorConnectionTester())->test(
            $entry,
            ['iban' => 'FR1420041010050500013M02606', 'account_holder' => 'Leopardo SAS'],
            [],
        );

        $this->assertSame('validated', $result['status']);
        // Le message doit dire ce que le contrôle NE prouve PAS : un IBAN
        // valide en format ne garantit pas que le compte est ouvert.
        $this->assertStringContainsString('ne confirme pas', $result['message']);
    }

    public function test_connector_without_verifiable_surface_is_unsupported_not_fake_success(): void
    {
        // Une page Facebook ne peut pas être vérifiée sans appel authentifié à
        // l'API Meta : on répond `unsupported`, jamais `validated`.
        $entry = $this->entry([
            ['key' => 'page_id', 'label' => 'Page ID', 'type' => 'text', 'required' => true],
        ], 'social');

        $result = (new ConnectorConnectionTester())->test($entry, ['page_id' => '123'], []);

        $this->assertSame('unsupported', $result['status']);
        $this->assertStringContainsString('ne peut pas être vérifié', $result['message']);
    }
}
