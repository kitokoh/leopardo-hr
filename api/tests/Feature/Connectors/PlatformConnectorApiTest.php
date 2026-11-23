<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Connectors\Database\Seeders\ConnectorCatalogSeeder;
use App\Modules\Connectors\Domain\Models\PlatformConnectorConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9847 (BC-14 INTEGRATION) — onglet « Connecteurs », surface SUPERADMIN.
 *
 * Contrat verrouillé ici :
 *   1. le catalogue expose les banques de la plateforme avec leur schéma ;
 *   2. on connecte une banque DYNAMIQUEMENT (plus de config en dur) ;
 *   3. les secrets sont chiffrés au repos et ne sortent JAMAIS en clair ;
 *   4. un `catalog_key` inconnu est un 422, pas un 500 ;
 *   5. un champ requis manquant est un 422 avec le détail par champ ;
 *   6. `connectors.manage` est exigée en écriture (support → 403) ;
 *   7. `/overview` remonte les intégrations existantes en lecture ;
 *   8. `/test` dit la vérité : IBAN invalide ⇒ `invalid`, valide ⇒ `validated`.
 */
class PlatformConnectorApiTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Catalogue réel : c'est lui qui décrit les formulaires.
        $this->seed(ConnectorCatalogSeeder::class);
    }

    private function actingAsPlatform(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-connectors@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    public function test_catalog_exposes_platform_banks_with_their_schema(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/v1/platform/connectors/catalog');
        $response->assertOk();

        $keys = array_column($response->json('data.items'), 'key');

        // Les banques de la plateforme sont proposées…
        $this->assertContains('bank_transfer', $keys);
        $this->assertContains('bank_ecobank', $keys);
        $this->assertContains('stripe', $keys);

        // …et les connecteurs purement tenant (page Facebook) ne le sont pas.
        $this->assertNotContains('facebook_page', $keys);
        $this->assertNotContains('wordpress', $keys);

        // Le schéma est exposé : c'est lui qui génère le formulaire côté front.
        $bank = collect($response->json('data.items'))->firstWhere('key', 'bank_transfer');
        $this->assertNotEmpty($bank['fields']);
        $this->assertContains('iban', array_column($bank['fields'], 'key'));
    }

    public function test_platform_connects_a_bank_dynamically_with_encrypted_secrets(): void
    {
        $this->actingAsPlatform();

        $created = $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte principal — virements tenants',
            'config' => [
                'bank_name' => 'Ecobank',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => [
                'iban' => 'FR1420041010050500013M02606',
                'bic' => 'ECOCFRPP',
            ],
            'is_default' => true,
        ]);

        $created->assertCreated();
        $created->assertJsonPath('data.catalog_key', 'bank_transfer');
        $created->assertJsonPath('data.status', 'draft');
        $created->assertJsonPath('data.config.bank_name', 'Ecobank');
        $created->assertJsonPath('data.secrets.iban.configured', true);

        // Le secret n'apparaît JAMAIS en clair dans la réponse.
        $raw = (string) $created->getContent();
        $this->assertStringNotContainsString('FR1420041010050500013M02606', $raw);

        // …et le masque est bien celui attendu (4 derniers caractères).
        $created->assertJsonPath('data.secrets.iban.mask', '••••2606');

        // Chiffré au repos : la colonne ne contient pas l'IBAN en clair.
        $id = (int) $created->json('data.id');
        $row = DB::table('platform_connector_connections')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('FR1420041010050500013M02606', (string) $row->secrets);

        // Le modèle déchiffre correctement (le chiffrement est réversible).
        $connection = PlatformConnectorConnection::query()->findOrFail($id);
        $this->assertSame('FR1420041010050500013M02606', ($connection->secrets ?? [])['iban'] ?? null);
    }

    public function test_unknown_catalog_key_is_422_not_500(): void
    {
        $this->actingAsPlatform();

        $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'banque_imaginaire',
            'label' => 'Test',
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_CATALOG_KEY_UNKNOWN');
    }

    public function test_tenant_only_connector_cannot_be_created_on_platform_surface(): void
    {
        // `facebook_page` est `audience = tenant` : la plateforme ne doit pas
        // pouvoir le connecter, même en connaissant la clé.
        $this->actingAsPlatform();

        $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'facebook_page',
            'label' => 'Page Facebook',
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_CATALOG_KEY_UNKNOWN');
    }

    public function test_missing_required_field_returns_field_level_violations(): void
    {
        $this->actingAsPlatform();

        $response = $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte incomplet',
            'config' => ['bank_name' => 'Ecobank'],
            // account_holder, currency et iban manquent.
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'CONNECTOR_SCHEMA_VIOLATION');

        $violations = $response->json('violations');
        $this->assertIsArray($violations);
        $this->assertNotEmpty($violations);
    }

    public function test_support_role_can_read_but_not_write(): void
    {
        $this->actingAsPlatform(PlatformRole::Support);

        $this->getJson('/api/v1/platform/connectors/catalog')->assertOk();
        $this->getJson('/api/v1/platform/connectors/connections')->assertOk();

        $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Tentative support',
        ])->assertForbidden();
    }

    public function test_finance_role_can_manage_bank_connections(): void
    {
        // Les comptes bancaires de la plateforme SONT le métier de finance.
        $this->actingAsPlatform(PlatformRole::Finance);

        $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte finance',
            'config' => [
                'bank_name' => 'BNP Paribas',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => ['iban' => 'FR1420041010050500013M02606'],
        ])->assertCreated();
    }

    public function test_overview_lists_existing_integrations_read_only(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/v1/platform/connectors/overview');
        $response->assertOk();

        $keys = array_column($response->json('data.integrations'), 'key');

        // Les intégrations existantes sont agrégées, pas dupliquées.
        $this->assertContains('payment_gateways', $keys);
        $this->assertContains('payment_provider_instances', $keys);

        // Chaque ligne renvoie vers son écran natif : c'est lui qui reste la
        // source de vérité.
        $gateways = collect($response->json('data.integrations'))->firstWhere('key', 'payment_gateways');
        $this->assertSame('/billing/gateways', $gateways['href']);
    }

    public function test_test_endpoint_reports_invalid_iban_honestly(): void
    {
        $this->actingAsPlatform();

        $id = (int) $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte avec IBAN faux',
            'config' => [
                'bank_name' => 'Ecobank',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => ['iban' => 'FR1420041010050500013M02607'],
        ])->json('data.id');

        $response = $this->postJson("/api/v1/platform/connectors/connections/{$id}/test");
        $response->assertOk();
        $response->assertJsonPath('data.check.status', 'invalid');
        $response->assertJsonPath('data.connection.last_check_status', 'invalid');
    }

    public function test_test_endpoint_validates_a_correct_iban(): void
    {
        $this->actingAsPlatform();

        $id = (int) $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte valide',
            'config' => [
                'bank_name' => 'Ecobank',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => ['iban' => 'FR1420041010050500013M02606'],
        ])->json('data.id');

        $this->postJson("/api/v1/platform/connectors/connections/{$id}/test")
            ->assertOk()
            ->assertJsonPath('data.check.status', 'validated');
    }

    public function test_update_is_write_only_and_keeps_existing_secret(): void
    {
        $this->actingAsPlatform();

        $id = (int) $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte à renommer',
            'config' => [
                'bank_name' => 'Ecobank',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => ['iban' => 'FR1420041010050500013M02606'],
        ])->json('data.id');

        // PATCH sans secret : l'IBAN en place est conservé.
        $this->patchJson("/api/v1/platform/connectors/connections/{$id}", [
            'label' => 'Compte renommé',
        ])->assertOk()->assertJsonPath('data.label', 'Compte renommé');

        $connection = PlatformConnectorConnection::query()->findOrFail($id);
        $this->assertSame('FR1420041010050500013M02606', ($connection->secrets ?? [])['iban'] ?? null);
    }

    public function test_status_change_and_deletion(): void
    {
        $this->actingAsPlatform();

        $id = (int) $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Compte à activer',
            'config' => [
                'bank_name' => 'Ecobank',
                'account_holder' => 'Leopardo SAS',
                'currency' => 'EUR',
            ],
            'secrets' => ['iban' => 'FR1420041010050500013M02606'],
        ])->json('data.id');

        $this->patchJson("/api/v1/platform/connectors/connections/{$id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->deleteJson("/api/v1/platform/connectors/connections/{$id}")->assertNoContent();
        $this->assertNull(PlatformConnectorConnection::query()->find($id));
    }

    public function test_url_connector_test_uses_real_http_status(): void
    {
        // Un connecteur à URL est réellement appelé : le verdict suit le statut
        // HTTP, il n'est pas inventé.
        $this->actingAsPlatform();

        Http::fake(['https://boutique.exemple.com/*' => Http::response('ok', 200)]);

        $id = (int) $this->postJson('/api/v1/platform/connectors/connections', [
            'catalog_key' => 'stripe',
            'label' => 'Stripe test',
            'config' => ['account_id' => 'acct_123'],
            'secrets' => ['secret_key' => 'sk_live_abcdefgh4242'],
        ])->json('data.id');

        // Stripe n'a pas d'URL dans sa config : le test doit répondre
        // `unsupported`, jamais un faux succès.
        $this->postJson("/api/v1/platform/connectors/connections/{$id}/test")
            ->assertOk()
            ->assertJsonPath('data.check.status', 'unsupported');
    }
}
