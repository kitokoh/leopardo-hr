<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7985 — barrière N+1 sur les surfaces publiques Restaurant.
 *
 * Les endpoints de SUIVI public (`items → product`) affichaient le nom du
 * produit de chaque ligne en chargement paresseux : 1 requête par article
 * (plus 1 pour les lignes) à chaque appel. Ces tests verrouillent la
 * propriété qui compte — le nombre de requêtes exécutées est CONSTANT quel
 * que soit le nombre d'articles du panier.
 *
 * Mesure par le journal de requêtes de la connexion
 * (`flushQueryLog()` + `enableQueryLog()`) et non `DB::listen()` : un
 * écouteur n'est jamais retiré et fausserait la mesure suivante (leçon
 * #7339, pattern `PlatformCompanyHealthApiTest` / `AccountingPerformanceTest`).
 */
class RestaurantPublicPerformanceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * Tenant + jeton boutique + branche + `$productCount` produits servis
     * par la branche (surfaces RESTO-805, jeton signé).
     *
     * @return array{token: string, branch: RestaurantBranch, codes: list<string>}
     */
    private function shopContext(Company $company, int $productCount): array
    {
        $plain = 'rshop_'.bin2hex(random_bytes(20));

        return app(TenantManager::class)->withinTenant($company, function () use ($plain, $company, $productCount): array {
            RestaurantPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => RestaurantPublicShopToken::hash($plain),
                'name' => 'perf',
                'active' => true,
            ]);

            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);

            $codes = [];

            for ($i = 1; $i <= $productCount; $i++) {
                /** @var RestaurantProduct $product */
                $product = RestaurantProduct::factory()->create([
                    'branch_id' => $branch->getAttribute('id'),
                    'code' => 'PERF-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'name' => 'Plat '.$i,
                    'price_minor' => 1500,
                    'currency' => 'XAF',
                    'tax_rate_id' => null,
                    'is_available' => true,
                    'status' => 'active',
                ]);

                $codes[] = (string) $product->code;
            }

            return ['token' => $plain, 'branch' => $branch, 'codes' => $codes];
        });
    }

    /**
     * Branche publique par slug + `$productCount` produits publiés en ligne.
     *
     * @return array{slug: string, codes: list<string>}
     */
    private function slugContext(Company $company, int $productCount): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($productCount): array {
            RestaurantBranch::factory()->create([
                'public_slug' => 'perf-leo',
                'is_public' => true,
                'status' => 'active',
                'currency' => 'XAF',
            ]);

            $codes = [];

            for ($i = 1; $i <= $productCount; $i++) {
                /** @var RestaurantProduct $product */
                $product = RestaurantProduct::factory()->create([
                    'branch_id' => null,
                    'code' => 'SLUG-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'name' => 'Plat slug '.$i,
                    'price_minor' => 2500,
                    'currency' => 'XAF',
                    'tax_rate_id' => null,
                    'is_published_online' => true,
                    'is_available' => true,
                    'status' => 'active',
                ]);

                $codes[] = (string) $product->code;
            }

            return ['slug' => 'perf-leo', 'codes' => $codes];
        });
    }

    /**
     * Nombre de requêtes MÉTIER exécutées PENDANT `$callback` (#7985).
     *
     * `SHOW search_path` / `SET search_path` sont de la plomberie de
     * connexion (bascule de tenant) : leur nombre varie avec l'état de la
     * connexion et ne mesure pas le N+1 — on ne compte que les requêtes
     * réellement émises vers les tables.
     */
    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return count(array_filter(
            $log,
            static function (array $query): bool {
                $sql = strtoupper(ltrim((string) $query['query']));

                return ! str_starts_with($sql, 'SHOW') && ! str_starts_with($sql, 'SET');
            },
        ));
    }

    public function test_shop_order_tracking_query_count_does_not_grow_with_items(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        $ctx = $this->shopContext($company, 5);

        $reference = function (array $codes) use ($ctx): string {
            $items = array_map(
                static fn (string $code): array => ['product_code' => $code, 'quantity' => 1],
                $codes,
            );

            $reference = $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
                ->postJson('/api/v1/public/restaurant/shop/orders', [
                    'branch_id' => $ctx['branch']->getAttribute('id'),
                    'items' => $items,
                ])
                ->assertStatus(201)
                ->json('data.reference');

            $this->assertIsString($reference);

            return $reference;
        };

        $singleReference = $reference([$ctx['codes'][0]]);
        $manyReference = $reference($ctx['codes']);

        $oneItem = $this->queryCount(function () use ($ctx, $singleReference): void {
            $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
                ->getJson('/api/v1/public/restaurant/shop/orders/'.$singleReference)
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.product_code', 'PERF-01')
                ->assertJsonPath('data.items.0.name', 'Plat 1');
        });

        $fiveItems = $this->queryCount(function () use ($ctx, $manyReference): void {
            $this->withHeader('X-Restaurant-Shop-Token', $ctx['token'])
                ->getJson('/api/v1/public/restaurant/shop/orders/'.$manyReference)
                ->assertOk()
                ->assertJsonCount(5, 'data.items')
                ->assertJsonPath('data.items.4.product_code', 'PERF-05')
                ->assertJsonPath('data.items.4.name', 'Plat 5');
        });

        $this->assertSame(
            $oneItem,
            $fiveItems,
            "Le suivi public (jeton boutique) doit rester à nombre de requêtes CONSTANT : 1 article = {$oneItem} requêtes, 5 articles = {$fiveItems} (N+1 → eager loading `items.product`).",
        );
    }

    public function test_slug_order_tracking_query_count_does_not_grow_with_items(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        $ctx = $this->slugContext($company, 5);

        $reference = function (array $codes) use ($ctx): string {
            $items = array_map(
                static fn (string $code): array => ['product_code' => $code, 'quantity' => 1],
                $codes,
            );

            $reference = $this->postJson('/api/v1/public/restaurants/'.$ctx['slug'].'/orders', [
                'customer_name' => 'Awa Ndiaye',
                'customer_phone' => '+237690000001',
                'order_type' => 'pickup',
                'items' => $items,
            ])
                ->assertStatus(201)
                ->json('data.reference');

            $this->assertIsString($reference);

            return $reference;
        };

        $singleReference = $reference([$ctx['codes'][0]]);
        $manyReference = $reference($ctx['codes']);

        $oneItem = $this->queryCount(function () use ($ctx, $singleReference): void {
            $this->getJson('/api/v1/public/restaurants/'.$ctx['slug'].'/orders/'.$singleReference)
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.name', 'Plat slug 1');
        });

        $fiveItems = $this->queryCount(function () use ($ctx, $manyReference): void {
            $this->getJson('/api/v1/public/restaurants/'.$ctx['slug'].'/orders/'.$manyReference)
                ->assertOk()
                ->assertJsonCount(5, 'data.items')
                ->assertJsonPath('data.items.4.name', 'Plat slug 5');
        });

        $this->assertSame(
            $oneItem,
            $fiveItems,
            "Le suivi public par slug doit rester à nombre de requêtes CONSTANT : 1 article = {$oneItem} requêtes, 5 articles = {$fiveItems} (N+1 → eager loading `items.product`).",
        );
    }
}
