<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeProduct;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * Catalogue produits branche dans les lignes de RFQ et d'offres — #9777.
 *
 * ## Ce que ce test protege
 *
 * Avant #9777, `lines.*.trade_product_id` n'etait valide que par
 * `integer|min:1`. Trois defauts en decoulaient, chacun couvert ici :
 *
 *  1. **Fuite cross-tenant a l'ecriture** — l'id d'un produit d'une AUTRE
 *     entreprise passait la validation et s'ecrivait dans la ligne. Le produit
 *     etant ensuite masque par le scope `company`, la ligne pointait dans le
 *     vide : un identifiant qui existe chez le voisin se retrouvait chez soi.
 *  2. **Produit archive selectionnable** — rien n'interdisait d'ajouter a une
 *     NOUVELLE ligne un produit retire du catalogue.
 *  3. **Identifiant opaque** — la ressource ne renvoyait qu'un entier : l'ecran
 *     ne pouvait pas afficher le nom du produit demande.
 *
 * Les assertions portent sur le VRAI chemin HTTP (FormRequest -> action ->
 * ressource), pas sur la regle isolee : c'est la chaine complete qui doit
 * tenir.
 */
class TradeProductLineBindingTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    // ── Chemin RFQ ────────────────────────────────────────────────────────

    public function test_an_rfq_line_can_reference_a_catalogue_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', [
            'name' => 'Cafe vert Arabica',
            'unit' => 'kg',
            'currency' => 'XAF',
        ])->assertStatus(201)->json('data.id');

        $response = $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ sur produit catalogue',
            'lines' => [[
                'description' => 'Cafe vert, sacs de 60 kg',
                'quantity' => 1000,
                'unit' => 'kg',
                'trade_product_id' => $productId,
            ]],
        ])->assertStatus(201);

        // La ligne porte l'id ET le produit resolu : sans le second, l'ecran
        // n'aurait qu'un entier a afficher.
        $response->assertJsonPath('data.lines.0.trade_product_id', $productId)
            ->assertJsonPath('data.lines.0.product.id', $productId)
            ->assertJsonPath('data.lines.0.product.name', 'Cafe vert Arabica')
            ->assertJsonPath('data.lines.0.product.unit', 'kg');
    }

    public function test_a_free_text_rfq_line_still_works_without_a_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // La saisie LIBRE reste permise : un acheteur qui cherche un produit
        // qu'il n'a pas encore catalogue ne doit jamais etre bloque.
        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ libre',
            'lines' => [['description' => 'Produit non catalogue', 'quantity' => 10]],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.lines.0.trade_product_id', null)
            ->assertJsonPath('data.lines.0.product', null);
    }

    public function test_an_rfq_line_cannot_reference_another_tenants_product(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);

        // Produit cree SOUS le contexte de A : `company_id` est force par
        // `BelongsToCompany` (#7646), il faut donc le contexte ad hoc.
        $foreignProductId = $this->withTenantContext($companyA, fn (): int => (int) TradeProduct::factory()
            ->create(['company_id' => $companyA->id, 'name' => 'Chez A'])
            ->id);

        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'Tentative cross-tenant',
            'lines' => [[
                'description' => 'Produit du voisin',
                'quantity' => 1,
                'trade_product_id' => $foreignProductId,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.trade_product_id']);

        // Rien n'a ete ecrit : la ligne ne doit pas exister chez B.
        $this->assertDatabaseMissing('trade_rfq_lines', ['trade_product_id' => $foreignProductId]);
    }

    public function test_an_rfq_line_cannot_reference_an_archived_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', ['name' => 'Retire du catalogue'])
            ->json('data.id');

        $this->postJson("/api/v1/trade/products/{$productId}/archive")->assertStatus(200);

        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ sur produit archive',
            'lines' => [[
                'description' => 'Produit archive',
                'quantity' => 1,
                'trade_product_id' => $productId,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.trade_product_id']);
    }

    public function test_an_unknown_product_id_is_refused(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ sur id fantome',
            'lines' => [[
                'description' => 'Produit inexistant',
                'quantity' => 1,
                'trade_product_id' => 999_999,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.trade_product_id']);
    }

    // ── Chemin offre ──────────────────────────────────────────────────────

    public function test_a_quote_line_can_reference_a_catalogue_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', [
            'name' => 'Cacao fermente',
            'unit' => 'kg',
            'currency' => 'USD',
        ])->assertStatus(201)->json('data.id');

        $response = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [[
                'description' => 'Cacao fermente',
                'quantity' => 200,
                'unit_price_minor' => 3_000,
                'trade_product_id' => $productId,
            ]],
        ])->assertStatus(201);

        $response->assertJsonPath('data.lines.0.trade_product_id', $productId)
            ->assertJsonPath('data.lines.0.product.name', 'Cacao fermente')
            // Le total reste calcule par le serveur : le branchement produit ne
            // doit pas ouvrir un chemin par lequel un prix serait repris du
            // catalogue sans que le vendeur l'ait saisi.
            ->assertJsonPath('data.lines.0.line_total_minor', 600_000);
    }

    public function test_a_quote_line_cannot_reference_another_tenants_product(): void
    {
        $companyA = $this->createCompany();
        $this->actingAsEmployee($companyA);

        $foreignProductId = $this->withTenantContext($companyA, fn (): int => (int) TradeProduct::factory()
            ->create(['company_id' => $companyA->id])
            ->id);

        $companyB = $this->createCompany();
        $this->actingAsEmployee($companyB);

        $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [[
                'description' => 'Produit du voisin',
                'quantity' => 1,
                'unit_price_minor' => 100,
                'trade_product_id' => $foreignProductId,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.trade_product_id']);

        $this->assertDatabaseMissing('trade_quote_lines', ['trade_product_id' => $foreignProductId]);
    }

    public function test_a_quote_line_cannot_reference_an_archived_product(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', ['name' => 'Fin de serie'])
            ->json('data.id');
        $this->postJson("/api/v1/trade/products/{$productId}/archive")->assertStatus(200);

        $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [[
                'description' => 'Fin de serie',
                'quantity' => 1,
                'unit_price_minor' => 100,
                'trade_product_id' => $productId,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.trade_product_id']);
    }

    // ── Lecture : listes et detail ────────────────────────────────────────

    public function test_listing_exposes_the_product_of_each_line(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', [
            'name' => 'Riz parfume',
            'unit' => 'kg',
        ])->json('data.id');

        $this->postJson('/api/v1/trade/rfqs', [
            'title' => 'RFQ listee',
            'lines' => [[
                'description' => 'Riz',
                'quantity' => 50,
                'trade_product_id' => $productId,
            ]],
        ])->assertStatus(201);

        // La LISTE doit aussi porter le produit : sans eager-load `lines.product`
        // la relation serait absente et la cle omise de la charge utile.
        $this->getJson('/api/v1/trade/rfqs')
            ->assertStatus(200)
            ->assertJsonPath('data.0.lines.0.product.name', 'Riz parfume');
    }

    public function test_the_product_payload_never_leaks_the_tenant_id(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        $productId = $this->postJson('/api/v1/trade/products', ['name' => 'Discret'])
            ->json('data.id');

        $response = $this->postJson('/api/v1/trade/quotes', [
            'currency' => 'USD',
            'lines' => [[
                'description' => 'Discret',
                'quantity' => 1,
                'unit_price_minor' => 100,
                'trade_product_id' => $productId,
            ]],
        ])->assertStatus(201);

        $line = $response->json('data.lines.0');
        self::assertIsArray($line['product']);

        // Le perimetre tenant est une affaire serveur : il ne sort jamais dans
        // une charge utile de ligne.
        self::assertArrayNotHasKey('company_id', $line['product']);
        self::assertSame(['id', 'name', 'unit', 'currency'], array_keys($line['product']));
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

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
            // `role` doit valoir `manager` : `TradeRoleResolver` lit
            // `Employee::isManager()` avant `manager_role` (lecon TRADE-003).
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        $this->actingAs($employee, 'sanctum');

        return $employee;
    }
}
