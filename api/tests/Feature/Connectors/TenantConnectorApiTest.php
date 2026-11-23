<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Connectors\Database\Seeders\ConnectorCatalogSeeder;
use App\Modules\Connectors\Domain\Models\ConnectorConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9848 (BC-14 INTEGRATION) — onglet « Connecteurs », surface TENANT.
 *
 * Contrat verrouillé ici :
 *   1. un tenant connecte SON site WordPress et SA page produit Facebook ;
 *   2. le catalogue tenant n'expose JAMAIS les connecteurs `audience = platform`
 *      (les banques de l'éditeur) ;
 *   3. les secrets sont chiffrés au repos et masqués en lecture ;
 *   4. isolation cross-tenant : liste vide + 404 sur accès direct ;
 *   5. un manager non `principal` lit mais n'écrit pas (403) ;
 *   6. `/overview` agrège les intégrations tenant existantes ;
 *   7. `/test` sur un site réellement joignable suit le statut HTTP.
 */
class TenantConnectorApiTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConnectorCatalogSeeder::class);
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        return $company;
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    public function test_tenant_catalog_excludes_platform_only_connectors(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/connectors/catalog');
        $response->assertOk();

        $keys = array_column($response->json('data.items'), 'key');

        // Les surfaces d'un client sont proposées…
        $this->assertContains('wordpress', $keys);
        $this->assertContains('facebook_page', $keys);
        $this->assertContains('shopify', $keys);
        $this->assertContains('stripe', $keys); // audience `both`
        $this->assertContains('stripe_connect', $keys); // audience `both`

        // …et les banques de la PLATEFORME ne le sont jamais.
        $this->assertNotContains('bank_transfer', $keys);
        $this->assertNotContains('bank_ecobank', $keys);
        $this->assertNotContains('mobile_money_wave', $keys);
    }

    public function test_tenant_connects_wordpress_site_with_encrypted_secret(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $created = $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site vitrine',
            'config' => [
                'site_url' => 'https://mon-site.com',
                'username' => 'admin',
            ],
            'secrets' => [
                'app_password' => 'abcd EFGH 1234 ijkl MNOP 5678',
            ],
        ]);

        $created->assertCreated();
        $created->assertJsonPath('data.catalog_key', 'wordpress');
        $created->assertJsonPath('data.config.site_url', 'https://mon-site.com');
        $created->assertJsonPath('data.secrets.app_password.configured', true);

        // Jamais de secret en clair dans la réponse.
        $raw = (string) $created->getContent();
        $this->assertStringNotContainsString('abcd EFGH 1234 ijkl MNOP 5678', $raw);

        // Chiffré au repos.
        $id = (int) $created->json('data.id');
        $row = DB::table('connector_connections')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('abcd EFGH 1234 ijkl MNOP 5678', (string) $row->secrets);
        $this->assertSame((string) $company->id, (string) $row->company_id);
    }

    public function test_tenant_connects_facebook_product_page(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $created = $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'facebook_page',
            'label' => 'Page produit Facebook',
            'config' => [
                'page_url' => 'https://facebook.com/ma-boutique',
                'page_id' => '1234567890',
                'catalog_id' => '987654321',
            ],
            'secrets' => [
                'access_token' => 'EAAG_token_de_page_4242',
            ],
        ]);

        $created->assertCreated();
        $created->assertJsonPath('data.catalog_key', 'facebook_page');
        $created->assertJsonPath('data.config.page_id', '1234567890');
        $created->assertJsonPath('data.secrets.access_token.mask', 'EAAG_••••4242');
    }

    public function test_platform_only_connector_cannot_be_created_by_tenant(): void
    {
        // Un tenant ne doit pas pouvoir se connecter aux banques de l'éditeur,
        // même en connaissant la clé du catalogue.
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'bank_transfer',
            'label' => 'Tentative',
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_CATALOG_KEY_UNKNOWN');
    }

    public function test_missing_required_field_returns_violations(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $response = $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site incomplet',
            // site_url manque.
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'CONNECTOR_SCHEMA_VIOLATION');
        $this->assertNotEmpty($response->json('violations'));
    }

    public function test_non_principal_manager_can_read_but_not_write(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $this->getJson('/api/v1/connectors/catalog')->assertOk();
        $this->getJson('/api/v1/connectors/connections')->assertOk();

        $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Tentative comptable',
            'config' => ['site_url' => 'https://exemple.com'],
        ])->assertForbidden();
    }

    public function test_cross_tenant_isolation_on_list_and_direct_access(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->manager($companyA));

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site du tenant A',
            'config' => ['site_url' => 'https://tenant-a.com'],
        ])->json('data.id');

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        // Liste : le tenant B ne voit rien du tenant A.
        $list = $this->getJson('/api/v1/connectors/connections');
        $list->assertOk();
        $this->assertCount(0, $list->json('data.items'));

        // Accès direct : 404 (jamais 403 — l'existence n'est pas révélée).
        $this->patchJson("/api/v1/connectors/connections/{$id}", ['label' => 'pirate'])->assertNotFound();
        $this->deleteJson("/api/v1/connectors/connections/{$id}")->assertNotFound();
        $this->postJson("/api/v1/connectors/connections/{$id}/test")->assertNotFound();
    }

    public function test_overview_aggregates_tenant_integrations(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $response = $this->getJson('/api/v1/connectors/overview');
        $response->assertOk();

        $keys = array_column($response->json('data.integrations'), 'key');

        $this->assertContains('payment_profiles', $keys);
        $this->assertContains('webhooks', $keys);
        $this->assertContains('calendar', $keys);

        // Chaque ligne renvoie vers son écran natif.
        /** @var list<array<string, mixed>> $integrations */
        $integrations = (array) $response->json('data.integrations');
        $profiles = collect($integrations)->firstWhere('key', 'payment_profiles');
        $this->assertNotNull($profiles, 'payment_profiles doit figurer dans l\'index.');
        $this->assertSame('/settings/encaissements', $profiles['href']);
    }

    public function test_url_connector_test_follows_real_http_status(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        Http::fake(['https://mon-site.com*' => Http::response('ok', 200)]);

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site joignable',
            'config' => ['site_url' => 'https://mon-site.com'],
        ])->json('data.id');

        $this->postJson("/api/v1/connectors/connections/{$id}/test")
            ->assertOk()
            ->assertJsonPath('data.check.status', 'validated');
    }

    public function test_url_connector_test_reports_unreachable_honestly(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        Http::fake(['https://site-casse.com*' => Http::response('boom', 500)]);

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site en erreur',
            'config' => ['site_url' => 'https://site-casse.com'],
        ])->json('data.id');

        $this->postJson("/api/v1/connectors/connections/{$id}/test")
            ->assertOk()
            ->assertJsonPath('data.check.status', 'invalid');
    }

    public function test_update_keeps_existing_secret_and_status_can_be_changed(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site à renommer',
            'config' => ['site_url' => 'https://mon-site.com'],
            'secrets' => ['app_password' => 'motdepasse_application_4242'],
        ])->json('data.id');

        $this->patchJson("/api/v1/connectors/connections/{$id}", ['label' => 'Site renommé'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Site renommé');

        $connection = ConnectorConnection::query()->findOrFail($id);
        $this->assertSame('motdepasse_application_4242', ($connection->secrets ?? [])['app_password'] ?? null);

        $this->patchJson("/api/v1/connectors/connections/{$id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->deleteJson("/api/v1/connectors/connections/{$id}")->assertNoContent();
        $this->assertNull(ConnectorConnection::query()->find($id));
    }
}
