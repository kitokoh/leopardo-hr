<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Connectors\Database\Seeders\ConnectorCatalogSeeder;
use App\Modules\Connectors\Domain\Models\ConnectorAiUsage;
use App\Modules\Connectors\Domain\Models\ConnectorConnection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9925 (BC-14 INTEGRATION) — modèles d'IA dans l'onglet Connecteurs.
 *
 * Contrat verrouillé ici :
 *   1. le catalogue propose les modèles d'IA au superadmin ET au tenant
 *      (`audience = both`) ;
 *   2. un tenant ACTIVE un modèle en créant une connexion `ai_*` ;
 *   3. la consommation de tokens est suivie par connexion et par mois ;
 *   4. le budget mensuel est appliqué en fail-closed (422 au-delà) ;
 *   5. une connexion non-IA n'a pas de suivi de tokens (422 explicite) ;
 *   6. isolation cross-tenant : un tenant ne voit jamais l'usage d'un autre.
 */
class ConnectorAiModelsTest extends TestCase
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

    /**
     * Crée une connexion IA pour le tenant courant et rend son id.
     */
    private function createAiConnection(Company $company, array $config = []): int
    {
        Sanctum::actingAs($this->manager($company));

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'ai_openai',
            'label' => 'Assistant IA',
            'config' => array_merge([
                'model' => 'gpt-4o',
                'max_tokens' => '1024',
            ], $config),
            'secrets' => ['api_key' => 'sk-test-4242'],
        ])->json('data.id');

        return $id;
    }

    public function test_catalog_proposes_ai_models_to_tenant(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $keys = array_column($this->getJson('/api/v1/connectors/catalog')->json('data.items'), 'key');

        // Les modèles d'IA sont proposés au tenant (audience `both`).
        $this->assertContains('ai_openai', $keys);
        $this->assertContains('ai_anthropic', $keys);
        $this->assertContains('ai_groq', $keys);
    }

    public function test_ai_catalog_entry_has_expected_schema(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        /** @var list<array<string, mixed>> $items */
        $items = (array) $this->getJson('/api/v1/connectors/catalog')->json('data.items');
        $entry = collect($items)->firstWhere('key', 'ai_openai');

        $this->assertNotNull($entry, 'ai_openai doit figurer au catalogue.');
        $this->assertSame('ai', $entry['category']);
        $this->assertSame('both', $entry['audience']);

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) $entry['fields'];
        $byKey = collect($fields)->keyBy('key');

        // La clé API est un secret ; le modèle et le budget sont non secrets.
        /** @var array<string, mixed> $apiKey */
        $apiKey = (array) $byKey['api_key'];
        /** @var array<string, mixed> $model */
        $model = (array) $byKey['model'];
        /** @var array<string, mixed> $budget */
        $budget = (array) $byKey['monthly_token_budget'];
        $this->assertTrue($apiKey['secret']);
        $this->assertTrue($model['required']);
        $this->assertArrayNotHasKey('secret', $budget);
    }

    public function test_tenant_activates_ai_model_with_encrypted_secret(): void
    {
        $company = $this->company();
        $id = $this->createAiConnection($company);

        $connection = ConnectorConnection::query()->findOrFail($id);
        $this->assertSame('ai_openai', $connection->catalog_key);
        $this->assertSame('gpt-4o', $connection->config['model'] ?? null);

        // Le secret est chiffré au repos et jamais renvoyé en clair.
        $row = DB::table('connector_connections')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('sk-test-4242', (string) $row->secrets);
        $this->assertSame('sk-test-4242', ($connection->secrets ?? [])['api_key'] ?? null);
    }

    public function test_usage_is_tracked_per_connection_and_month(): void
    {
        $company = $this->company();
        $id = $this->createAiConnection($company);

        // Deux appels : 100+50 puis 200+80 tokens.
        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 100,
            'output_tokens' => 50,
        ])->assertOk()->assertJsonPath('data.total_tokens', 150);

        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 200,
            'output_tokens' => 80,
        ])->assertOk()->assertJsonPath('data.total_tokens', 430);

        // Une seule ligne pour le mois : on incrémente, on ne duplique pas.
        $this->assertSame(1, ConnectorAiUsage::query()->where('connection_id', $id)->count());

        $usage = $this->getJson("/api/v1/connectors/connections/{$id}/usage");
        $usage->assertOk()
            ->assertJsonPath('data.used_tokens', 430)
            ->assertJsonPath('data.budget_tokens', null);
    }

    public function test_monthly_budget_is_enforced_fail_closed(): void
    {
        $company = $this->company();
        $id = $this->createAiConnection($company, ['monthly_token_budget' => '1000']);

        // Sous le budget : accepté.
        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 600,
            'output_tokens' => 200,
        ])->assertOk()->assertJsonPath('data.total_tokens', 800);

        // On consomme jusqu'à atteindre le budget (800 + 200 = 1000).
        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 200,
            'output_tokens' => 0,
        ])->assertOk()->assertJsonPath('data.total_tokens', 1000);

        // Budget atteint : le prochain appel est refusé, et RIEN n'est comptabilisé.
        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 500,
            'output_tokens' => 0,
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_AI_BUDGET_EXCEEDED');

        // Le cumul reste à 1000 : l'appel refusé n'a pas été enregistré.
        $this->getJson("/api/v1/connectors/connections/{$id}/usage")
            ->assertOk()
            ->assertJsonPath('data.used_tokens', 1000)
            ->assertJsonPath('data.budget_tokens', 1000);
    }

    public function test_non_ai_connection_has_no_token_tracking(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $id = (int) $this->postJson('/api/v1/connectors/connections', [
            'catalog_key' => 'wordpress',
            'label' => 'Site vitrine',
            'config' => ['site_url' => 'https://mon-site.com'],
        ])->json('data.id');

        $this->getJson("/api/v1/connectors/connections/{$id}/usage")
            ->assertStatus(422)
            ->assertJsonPath('error', 'CONNECTOR_NOT_AI');

        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 10,
            'output_tokens' => 10,
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_NOT_AI');
    }

    public function test_negative_token_counts_are_rejected(): void
    {
        $company = $this->company();
        $id = $this->createAiConnection($company);

        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => -5,
            'output_tokens' => 10,
        ])->assertStatus(422)->assertJsonPath('error', 'CONNECTOR_AI_USAGE_INVALID');
    }

    public function test_usage_is_isolated_between_tenants(): void
    {
        $companyA = $this->company();
        $idA = $this->createAiConnection($companyA);

        $this->postJson("/api/v1/connectors/connections/{$idA}/usage", [
            'input_tokens' => 100,
            'output_tokens' => 100,
        ])->assertOk();

        // Le tenant B ne voit ni la connexion ni son usage.
        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->getJson("/api/v1/connectors/connections/{$idA}/usage")->assertNotFound();
        $this->postJson("/api/v1/connectors/connections/{$idA}/usage", [
            'input_tokens' => 1,
            'output_tokens' => 1,
        ])->assertNotFound();
    }

    public function test_non_principal_manager_cannot_record_usage(): void
    {
        $company = $this->company();
        $id = $this->createAiConnection($company);

        // Un manager non principal lit mais n'écrit pas.
        Sanctum::actingAs($this->manager($company, 'comptable'));

        $this->getJson("/api/v1/connectors/connections/{$id}/usage")->assertOk();
        $this->postJson("/api/v1/connectors/connections/{$id}/usage", [
            'input_tokens' => 10,
            'output_tokens' => 10,
        ])->assertForbidden();
    }
}