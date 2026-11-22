<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeProduct;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Recherche transverse import-export — BC-36 TRADE, #9781.
 *
 * ## Ce que ce test protège
 *
 *  - **trois groupes peuplés** : contreparties, produits, dossiers — la
 *    recherche ne doit pas silencieusement oublier un référentiel ;
 *  - **isolation tenant** : une contrepartie d'une autre entreprise ne doit
 *    JAMAIS apparaître dans les résultats (le scope `BelongsToCompany` est la
 *    seule barrière — un `where` oublié serait invisible en production) ;
 *  - **jokers SQL échappés** : `%` est un caractère, pas « tout » ;
 *  - **requête trop courte = 422** : pas de dump de la base déguisé en
 *    recherche ;
 *  - **plafond `limit`** : un écran de recherche n'est pas un export.
 */
class TradeSearchApiTest extends TestCase
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

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/trade/search?q=acier')->assertStatus(401);
    }

    public function test_search_is_gated_by_the_trade_feature(): void
    {
        $company = $this->createCompany(enableTrade: false);
        $this->actingAsEmployee($company);

        $this->getJson('/api/v1/trade/search?q=acier')->assertStatus(403);
    }

    public function test_search_returns_the_three_groups(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        TradeCounterparty::factory()->create([
            'company_id' => $company->id,
            'name' => 'Acier Nord SARL',
        ]);
        TradeProduct::factory()->create([
            'company_id' => $company->id,
            'name' => 'Tôle acier 2mm',
        ]);
        TradeDeal::factory()->create([
            'company_id' => $company->id,
            'title' => 'Import acier Turquie',
        ]);

        $response = $this->getJson('/api/v1/trade/search?q=acier');

        $response->assertStatus(200)
            ->assertJsonPath('query', 'acier')
            ->assertJsonCount(1, 'groups.counterparties')
            ->assertJsonCount(1, 'groups.products')
            ->assertJsonCount(1, 'groups.deals')
            ->assertJsonPath('total', 3);

        $this->assertSame('Acier Nord SARL', $response->json('groups.counterparties.0.name'));
        $this->assertSame('Tôle acier 2mm', $response->json('groups.products.0.name'));
        $this->assertSame('Import acier Turquie', $response->json('groups.deals.0.title'));
    }

    public function test_search_never_leaks_another_tenant(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        TradeCounterparty::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Acier Secret SARL',
        ]);
        TradeProduct::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Acier Secret',
        ]);

        $this->getJson('/api/v1/trade/search?q=acier')
            ->assertStatus(200)
            ->assertJsonCount(0, 'groups.counterparties')
            ->assertJsonCount(0, 'groups.products')
            ->assertJsonPath('total', 0);
    }

    public function test_short_query_is_rejected(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        // Une recherche d'un caractère ramènerait presque tout le répertoire :
        // ce n'est pas une recherche, c'est un export déguisé.
        $this->getJson('/api/v1/trade/search?q=a')->assertStatus(422);
        $this->getJson('/api/v1/trade/search?q=')->assertStatus(422);
        $this->getJson('/api/v1/trade/search')->assertStatus(422);
    }

    public function test_sql_wildcards_are_escaped(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        TradeCounterparty::factory()->create(['company_id' => $company->id, 'name' => 'Alpha SARL']);
        TradeCounterparty::factory()->create(['company_id' => $company->id, 'name' => 'Beta SARL']);

        // `%` est un CARACTÈRE : sans échappement, cette recherche ramènerait
        // les deux contreparties.
        $this->getJson('/api/v1/trade/search?q=%25%25')
            ->assertStatus(200)
            ->assertJsonCount(0, 'groups.counterparties');
    }

    public function test_limit_is_capped(): void
    {
        $company = $this->createCompany();
        $this->actingAsEmployee($company);

        foreach (range(1, 5) as $index) {
            TradeCounterparty::factory()->create([
                'company_id' => $company->id,
                'name' => "Acier {$index}",
            ]);
        }

        $this->getJson('/api/v1/trade/search?q=acier&limit=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'groups.counterparties');

        // Un `limit` démesuré est ramené au plafond du service, jamais honoré.
        $this->getJson('/api/v1/trade/search?q=acier&limit=9999')
            ->assertStatus(200)
            ->assertJsonCount(5, 'groups.counterparties');
    }

    public function test_read_only_role_can_search(): void
    {
        $company = $this->createCompany();
        // `comptable` n'obtient que `reports` : chercher n'est pas une
        // opération commerciale, il doit pouvoir le faire.
        $this->actingAsEmployee($company, 'comptable');

        $this->getJson('/api/v1/trade/search?q=acier')->assertStatus(200);
    }
}
