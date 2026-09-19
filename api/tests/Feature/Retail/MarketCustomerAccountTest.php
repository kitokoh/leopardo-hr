<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7814 — Comptes acheteurs grand public de Leopardo Marché.
 *
 * Critères d'acceptation couverts :
 * - inscription : mot de passe HASHÉ, token Sanctum du guard DÉDIÉ
 *   `market_customer` (jamais le guard employés), rattachement des commandes
 *   en ligne existantes portant le même e-mail client ;
 * - connexion : identifiants invalides → 401 indifférencié, verrouillage
 *   après échecs répétés (423) ;
 * - « mes commandes » : cross-boutiques mais STRICTEMENT bornées au compte
 *   (isolation acheteur), nom public de boutique seul (pas de company_id) ;
 * - commande créée connecté → rattachée au compte à la création ;
 * - le checkout invité reste possible (commande sans compte).
 */
class MarketCustomerAccountTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'marche-secret-2026';

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->sellerCompany('SN', 'XOF');
        $this->companyB = $this->sellerCompany('MA', 'MAD');
        $this->principalA = $this->principal($this->companyA);
        $this->principalB = $this->principal($this->companyB);
    }

    private function sellerCompany(string $country, string $currency): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);
        $company->setFeature('retail', true);
        $company->save();

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    /**
     * Boutique en ligne activée + produit public + stock, via l'API vendeur.
     *
     * @return array{product_id: int, slug: string}
     */
    private function publicProduct(Employee $actor, Company $company, string $sku, string $currency): array
    {
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique '.$sku,
            'city' => 'Dakar',
            'currency' => $currency,
        ])->assertStatus(200);

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Produit '.$sku,
            'sku' => $sku,
            'price_minor' => 2_000,
            'currency' => $currency,
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique '.$sku,
            'code' => 'STORE-'.$sku,
        ])->assertStatus(201)->json('data');

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => (int) $location['id'],
            'product_id' => (int) $product['id'],
            'quantity_delta' => 25,
            'reason_code' => 'purchase',
        ])->assertStatus(201);

        // Fin de la session vendeur : les appels suivants sont publics.
        app('auth')->forgetGuards();

        return ['product_id' => (int) $product['id'], 'slug' => (string) $company->slug];
    }

    /**
     * Checkout invité public (sans compte).
     *
     * @return array<string, mixed>
     */
    private function guestOrder(string $sellerSlug, int $productId, string $email, string $key, array $headers = []): array
    {
        return $this->postJson('/api/v1/public/market/orders', [
            'seller' => $sellerSlug,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'customer' => ['name' => 'Awa Client', 'phone' => '+221770000000', 'email' => $email],
            'delivery' => ['address' => '12 rue des Manguiers', 'city' => 'Dakar'],
            'payment_method' => 'cash',
            'idempotency_key' => $key,
        ], $headers)->assertStatus(201)->json('data');
    }

    /**
     * @return array{token: string, account: array<string, mixed>, claimed: int}
     */
    private function register(string $email, string $name = 'Awa Client'): array
    {
        $data = $this->postJson('/api/v1/public/market/account/register', [
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ])->assertStatus(201)->json('data');

        return [
            'token' => (string) $data['token'],
            'account' => $data['account'],
            'claimed' => (int) $data['claimed_orders'],
        ];
    }

    public function test_register_hashes_password_and_claims_past_orders_by_email(): void
    {
        $fxA = $this->publicProduct($this->principalA, $this->companyA, 'SKU-A1', 'XOF');
        $fxB = $this->publicProduct($this->principalB, $this->companyB, 'SKU-B1', 'MAD');

        // Commandes invitées passées, chez DEUX boutiques, même e-mail
        // (casse différente : rattachement insensible à la casse).
        $this->guestOrder($fxA['slug'], $fxA['product_id'], 'Client@Example.test', 'acc-7814-a');
        $this->guestOrder($fxB['slug'], $fxB['product_id'], 'client@example.test', 'acc-7814-b');
        // Commande d'un AUTRE acheteur : jamais rattachée.
        $this->guestOrder($fxA['slug'], $fxA['product_id'], 'autre@example.test', 'acc-7814-c');

        $result = $this->register('client@example.test');

        $this->assertSame(2, $result['claimed']);
        $this->assertNotSame('', $result['token']);

        /** @var MarketCustomerAccount $account */
        $account = MarketCustomerAccount::query()->where('email', 'client@example.test')->firstOrFail();

        // Mot de passe HASHÉ, jamais en clair.
        $this->assertNotSame(self::PASSWORD, $account->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $account->password));

        // Les deux commandes (cross-boutiques) sont rattachées au compte.
        $attached = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('customer_account_id', $account->id)
            ->count();
        $this->assertSame(2, $attached);

        // E-mail déjà pris (même en changeant la casse) → 422.
        $this->postJson('/api/v1/public/market/account/register', [
            'name' => 'Doublon',
            'email' => 'CLIENT@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(422);
    }

    public function test_login_locks_after_repeated_failures(): void
    {
        $this->register('login@example.test');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/public/market/account/login', [
                'email' => 'login@example.test',
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        // Verrouillé, même avec le BON mot de passe (423).
        $this->postJson('/api/v1/public/market/account/login', [
            'email' => 'login@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(423);

        // Un autre compte n'est pas affecté.
        $other = $this->register('libre@example.test');
        $this->assertNotSame('', $other['token']);
    }

    public function test_my_orders_are_strictly_bounded_to_the_account_and_expose_no_tenant_data(): void
    {
        $fxA = $this->publicProduct($this->principalA, $this->companyA, 'SKU-A2', 'XOF');
        $fxB = $this->publicProduct($this->principalB, $this->companyB, 'SKU-B2', 'MAD');

        $this->guestOrder($fxA['slug'], $fxA['product_id'], 'mine@example.test', 'ord-7814-a');
        $this->guestOrder($fxB['slug'], $fxB['product_id'], 'mine@example.test', 'ord-7814-b');
        $this->guestOrder($fxA['slug'], $fxA['product_id'], 'other@example.test', 'ord-7814-c');

        $mine = $this->register('mine@example.test');
        $this->register('other@example.test', 'Autre Acheteur');

        $response = $this->getJson('/api/v1/public/market/account/orders', [
            'Authorization' => 'Bearer '.$mine['token'],
        ])->assertStatus(200);

        $response->assertJsonCount(2, 'data');
        // Nom public de boutique seul : jamais de company_id.
        $this->assertStringNotContainsString((string) $this->companyA->id, $response->getContent() ?: '');
        $this->assertStringNotContainsString((string) $this->companyB->id, $response->getContent() ?: '');

        // Sans token → 401.
        $this->getJson('/api/v1/public/market/account/orders')->assertStatus(401);
    }

    public function test_checkout_while_logged_in_attaches_the_order_and_guest_checkout_still_works(): void
    {
        $fx = $this->publicProduct($this->principalA, $this->companyA, 'SKU-A3', 'XOF');

        $result = $this->register('attach@example.test');

        $order = $this->guestOrder($fx['slug'], $fx['product_id'], 'attach@example.test', 'att-7814-a', [
            'Authorization' => 'Bearer '.$result['token'],
        ]);

        /** @var RetailOrder $stored */
        $stored = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $order['reference'])
            ->firstOrFail();

        $this->assertSame((int) $result['account']['id'], (int) $stored->customer_account_id);

        // Checkout invité (sans token) : toujours possible, non rattaché.
        $guest = $this->guestOrder($fx['slug'], $fx['product_id'], 'guest@example.test', 'att-7814-b');

        /** @var RetailOrder $guestStored */
        $guestStored = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('reference', (string) $guest['reference'])
            ->firstOrFail();

        $this->assertNull($guestStored->customer_account_id);
    }

    public function test_me_and_logout_require_the_dedicated_guard(): void
    {
        $result = $this->register('profil@example.test');
        $headers = ['Authorization' => 'Bearer '.$result['token']];

        $this->getJson('/api/v1/public/market/account/me', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.account.email', 'profil@example.test');

        // Un token EMPLOYÉ ne résout jamais un compte acheteur.
        Sanctum::actingAs($this->principalA);
        $this->getJson('/api/v1/public/market/account/me')->assertStatus(401);
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/public/market/account/logout', [], $headers)->assertStatus(200);
        $this->getJson('/api/v1/public/market/account/me', $headers)->assertStatus(401);
    }
}
