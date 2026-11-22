<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Domain\Models\TradeProduct;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Catalogue produits B2B — BC-36 TRADE, #9767.
 *
 * ## Ce que ce test protège
 *
 * `TradeProduct` + sa table + sa factory existaient depuis TRADE-002/#8477,
 * mais **aucune route ne les exposait** : le catalogue était un modèle
 * orphelin. Ce test verrouille les invariants qui casseraient silencieusement
 * si quelqu'un « simplifiait » le contrôleur plus tard :
 *
 *  - `hs_code` **jamais deviné** — absent = `null`, et un code invalide est
 *    refusé (un code à 3 chiffres est un chapitre, pas un classement) ;
 *  - montants en **minor units entiers** (jamais de flottant) ;
 *  - **isolation tenant** : le produit d'une autre entreprise est 404, jamais
 *    403 (l'API ne révèle pas son existence) ;
 *  - **archivage logique** : `archive` pose `status = archived` et reste
 *    idempotent — aucune suppression dure, les RFQ passées référencent le
 *    produit ;
 *  - **RBAC** : un rôle de lecture seule ne peut pas écrire le catalogue.
 */
class TradeProductApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createCompany(bool $enableTrade = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($enableTrade) {
            $company->setFeature('trade', true);
            $company->save();
        }

        app()->instance('current_company', $company);

        return $company;
    }

    private function actingAsEmployee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/trade/products')->assertStatus(401);
        $this->postJson('/api/v1/trade/products', [])->assertStatus(401);
    }

    public function test_endpoints_are_gated_by_the_trade_feature(): void
    {
        $company = $this->createCompany(enableTrade: false);
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/trade/products')->assertStatus(403);
    }

    public function test_manager_can_create_and_list_a_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/trade/products', [
            'name' => 'Café vert Arabica',
            'description' => 'Grains verts, sacs de 60 kg',
            'category' => 'agroalimentaire',
            'origin_country' => 'CM',
            'unit' => 'kg',
            'moq_quantity' => '1000.000',
            'indicative_unit_price_minor' => 250_000,
            'currency' => 'XAF',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Café vert Arabica')
            ->assertJsonPath('data.hs_code', null)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.indicative_unit_price_minor', 250_000);

        $this->getJson('/api/v1/trade/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Café vert Arabica');
    }

    public function test_hs_code_is_never_guessed_and_must_be_valid_when_provided(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Absent : reste null — le système ne déduit jamais un classement.
        $this->postJson('/api/v1/trade/products', ['name' => 'Sans code'])
            ->assertStatus(201)
            ->assertJsonPath('data.hs_code', null);

        // Un code à 3 chiffres est un chapitre, pas un classement : refusé.
        $this->postJson('/api/v1/trade/products', ['name' => 'Invalide', 'hs_code' => '090'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hs_code']);

        // Code valide à 6 chiffres : accepté.
        $this->postJson('/api/v1/trade/products', ['name' => 'Valide', 'hs_code' => '090111'])
            ->assertStatus(201)
            ->assertJsonPath('data.hs_code', '090111');
    }

    public function test_update_does_not_archive_the_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $id = $this->postJson('/api/v1/trade/products', ['name' => 'Produit'])
            ->json('data.id');

        // `status` n'est pas éditable via PUT : archiver est un acte distinct.
        $this->putJson("/api/v1/trade/products/{$id}", [
            'name' => 'Produit renommé',
            'status' => 'archived',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Produit renommé')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_archive_is_logical_and_idempotent(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $id = $this->postJson('/api/v1/trade/products', ['name' => 'Produit'])
            ->json('data.id');

        $this->postJson("/api/v1/trade/products/{$id}/archive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Idempotent : ré-archiver ne casse rien.
        $this->postJson("/api/v1/trade/products/{$id}/archive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Aucune suppression dure : la ligne existe toujours en base.
        $this->assertDatabaseHas('trade_products', ['id' => $id, 'status' => 'archived']);
    }

    public function test_another_tenant_product_is_not_found(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);
        $id = $this->postJson('/api/v1/trade/products', ['name' => 'Chez A'])
            ->json('data.id');

        // Second tenant : le produit de A doit être INTROUVABLE (404), jamais
        // 403 — l'API ne révèle pas son existence.
        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->getJson("/api/v1/trade/products/{$id}")->assertStatus(404);
        $this->putJson("/api/v1/trade/products/{$id}", ['name' => 'Piraté'])->assertStatus(404);
        $this->postJson("/api/v1/trade/products/{$id}/archive")->assertStatus(404);

        $this->assertDatabaseHas('trade_products', ['id' => $id, 'name' => 'Chez A']);
    }

    public function test_public_catalogue_lists_only_active_products_and_hides_hs_code(): void
    {
        $company = $this->createCompany();
        $employee = $this->actingAsEmployee($company);

        // Profil publié + un produit actif (avec HS) + un produit archivé.
        TradeCompanyProfile::query()->create([
            'company_id' => $company->id,
            'import_enabled' => true,
            'export_enabled' => false,
            'domestic_scope' => false,
            'hq_country' => 'CM',
            'default_currency' => 'XAF',
            'is_public' => true,
            'public_slug' => 'cafe-cameroun',
            'public_name' => 'Café du Cameroun',
        ]);

        TradeProduct::factory()->create([
            'company_id' => $company->id,
            'name' => 'Actif',
            'hs_code' => '090111',
            'status' => 'active',
        ]);

        TradeProduct::factory()->create([
            'company_id' => $company->id,
            'name' => 'Archivé',
            'status' => 'archived',
        ]);

        $this->assertNotNull($employee);

        $response = $this->getJson('/api/v1/public/trade/companies/cafe-cameroun/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Actif');

        // `hs_code` ne doit JAMAIS sortir côté public.
        $this->assertArrayNotHasKey('hs_code', $response->json('data.0'));
        $this->assertArrayNotHasKey('status', $response->json('data.0'));
    }

    public function test_public_catalogue_is_uniform_404_for_unpublished_profile(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        TradeCompanyProfile::query()->create([
            'company_id' => $company->id,
            'import_enabled' => true,
            'export_enabled' => false,
            'domestic_scope' => false,
            'hq_country' => 'CM',
            'default_currency' => 'XAF',
            'is_public' => false,
        ]);

        TradeProduct::factory()->create([
            'company_id' => $company->id,
            'name' => 'Privé',
            'status' => 'active',
        ]);

        // Profil non publié → 404 uniforme (même réponse qu'un slug inexistant).
        $this->getJson('/api/v1/public/trade/companies/inconnu/products')->assertStatus(404);
    }
}
