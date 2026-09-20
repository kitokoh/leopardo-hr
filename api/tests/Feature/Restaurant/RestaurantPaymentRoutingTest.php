<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Shared\Contracts\Payments\TenantPaymentProfileResolverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7728 (BC-25 RESTAURANT / BC-21) — routage des gateways Restaurant sur les
 * PROFILS DE PAIEMENT du tenant (#7727).
 *
 * Couvre les critères d'acceptation :
 * - commande publique payée par carte avec profil Stripe tenant actif →
 *   checkout créé sur le COMPTE DU TENANT (fake gateway HTTP : la clé
 *   utilisée est celle du profil) ;
 * - sans profil actif : fail-closed PROPRE (422 message utilisateur +
 *   fallback paiement sur place — jamais de 500) ;
 * - providers sur place (cash/carte terminal) refusés sur la surface
 *   publique ;
 * - écran restaurateur : état de configuration relié aux profils.
 */
class RestaurantPaymentRoutingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private RestaurantBranch $branch;

    private RestaurantMenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'CI',
            'currency' => 'XOF',
            'features' => ['restaurantmanager' => true],
        ]);
        $this->company = $company;

        app(TenantManager::class)->withinTenant($company, function (): void {
            /** @var RestaurantBranch $branch */
            $branch = RestaurantBranch::factory()->create(['currency' => 'XOF']);
            $this->branch = $branch;

            /** @var RestaurantProduct $product */
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 12000,
                'currency' => 'XOF',
                'is_available' => true,
            ]);

            /** @var RestaurantMenu $menu */
            $menu = RestaurantMenu::factory()->create([
                'branch_id' => $branch->id,
                'currency' => 'XOF',
            ]);

            /** @var RestaurantMenuItem $menuItem */
            $menuItem = RestaurantMenuItem::factory()->create([
                'menu_id' => $menu->id,
                'product_id' => $product->id,
            ]);
            $this->menuItem = $menuItem;
        });
    }

    /**
     * Double de test du contrat partagé #7727 : profils de paiement tenant
     * contrôlés par le test (aucune table Billing requise).
     *
     * @param  array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}|null  $stripe
     * @param  array{profile_id: int, operator: string, phone_number: string}|null  $mobileMoney
     */
    private function fakeProfiles(?array $stripe, ?array $mobileMoney = null): void
    {
        $resolver = new class($stripe, $mobileMoney) implements TenantPaymentProfileResolverInterface
        {
            /**
             * @param  array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}|null  $stripe
             * @param  array{profile_id: int, operator: string, phone_number: string}|null  $mobileMoney
             */
            public function __construct(
                private readonly ?array $stripe,
                private readonly ?array $mobileMoney,
            ) {}

            public function activeStripeCredentials(): ?array
            {
                return $this->stripe;
            }

            public function stripeWebhookSecretForCompany(string $companyId): ?string
            {
                return $this->stripe['webhook_secret'] ?? null;
            }

            public function stripeCredentialsForCompany(string $companyId): ?array
            {
                return $this->stripe;
            }

            public function activeMobileMoneyProfileForCompany(string $companyId): ?array
            {
                return $this->mobileMoney;
            }
        };

        $this->app->instance(TenantPaymentProfileResolverInterface::class, $resolver);
        // Le registre singleton capture le resolver à sa construction :
        // on repart d'un registre neuf pour chaque scénario.
        $this->app->forgetInstance(\App\Modules\RestaurantManager\Infrastructure\Services\PaymentGatewayRegistry::class);
    }

    /**
     * @return array{profile_id: int, secret_key: string, webhook_secret: string, stripe_account_id: string|null}
     */
    private function tenantStripeProfile(): array
    {
        return [
            'profile_id' => 42,
            'secret_key' => 'sk_test_tenant_resto',
            'webhook_secret' => 'whsec_tenant_resto',
            'stripe_account_id' => null,
        ];
    }

    private function createPublicOrder(): RestaurantOrder
    {
        $storeUrl = URL::temporarySignedRoute(
            'restaurant.public.orders.store',
            now()->addHour(),
            ['company' => (string) $this->company->id]
        );

        $created = $this->postJson($storeUrl, [
            'branch_id' => (int) $this->branch->getAttribute('id'),
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => (int) $this->menuItem->getAttribute('id'), 'quantity' => 1]],
            'consent' => true,
        ])->assertStatus(201);

        /** @var RestaurantOrder $order */
        $order = RestaurantOrder::query()
            ->withoutGlobalScopes()
            ->where('reference', $created->json('data.reference'))
            ->firstOrFail();

        return $order;
    }

    private function payUrl(RestaurantOrder $order): string
    {
        return URL::temporarySignedRoute(
            'restaurant.public.orders.pay',
            now()->addHour(),
            ['company' => (string) $this->company->id, 'order' => (int) $order->getAttribute('id')]
        );
    }

    public function test_public_card_online_payment_charges_the_tenant_stripe_account(): void
    {
        $this->fakeProfiles($this->tenantStripeProfile());

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_resto_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_resto_123',
            ]),
        ]);

        $order = $this->createPublicOrder();

        $this->postJson($this->payUrl($order), ['provider_code' => 'card_online'])
            ->assertStatus(201)
            ->assertJsonPath('data.provider_code', 'card_online')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider_reference', 'cs_test_resto_123')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_resto_123');

        // Critère #7728 : le checkout part sur le compte Stripe DU TENANT
        // (clé du profil, jamais celle de la plateforme) + traçabilité profil.
        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer sk_test_tenant_resto')
                && $request['metadata[tenant_payment_profile_id]'] === '42'
                && $request['metadata[company_id]'] === (string) $this->company->id;
        });
    }

    public function test_public_pay_defaults_to_card_online_when_stripe_profile_is_active(): void
    {
        $this->fakeProfiles($this->tenantStripeProfile());

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_default_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_default_1',
            ]),
        ]);

        $order = $this->createPublicOrder();

        // Sans provider_code : la carte en ligne (profil actif) prime sur le
        // mobile money sandbox.
        $this->postJson($this->payUrl($order), [])
            ->assertStatus(201)
            ->assertJsonPath('data.provider_code', 'card_online');
    }

    public function test_public_pay_without_active_profile_fails_closed_with_on_site_fallback(): void
    {
        $this->fakeProfiles(null, null);
        config()->set('restaurantmanager.mobile_money.sandbox', false);

        $order = $this->createPublicOrder();

        // Fail-closed PROPRE : 422 message utilisateur + fallback sur place,
        // jamais un 500 (critère d'acceptation #7728).
        $this->postJson($this->payUrl($order), [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'online_payment_not_configured')
            ->assertJsonPath('fallback', 'pay_on_site');

        $this->postJson($this->payUrl($order), ['provider_code' => 'card_online'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'online_payment_not_configured');

        $this->postJson($this->payUrl($order), ['provider_code' => 'mobile_money'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'online_payment_not_configured');

        // Aucun paiement fantôme n'a été créé.
        $this->assertSame(0, RestaurantOrderPayment::query()
            ->withoutGlobalScopes()
            ->where('order_id', $order->getAttribute('id'))
            ->count());
    }

    public function test_public_pay_rejects_on_site_providers(): void
    {
        $this->fakeProfiles($this->tenantStripeProfile());

        $order = $this->createPublicOrder();

        // cash / carte terminal = guichet authentifié uniquement (le fallback
        // public est le paiement sur place, pas un enregistrement direct).
        $this->postJson($this->payUrl($order), ['provider_code' => 'cash'])
            ->assertStatus(422);
        $this->postJson($this->payUrl($order), ['provider_code' => 'card'])
            ->assertStatus(422);
    }

    public function test_mobile_money_sandbox_flow_is_preserved(): void
    {
        $this->fakeProfiles(null, null);

        $order = $this->createPublicOrder();

        // Sandbox (défaut) : comportement historique inchangé (pending,
        // confirmation par le callback signé HMAC RESTO-407).
        $this->postJson($this->payUrl($order), ['provider_code' => 'mobile_money'])
            ->assertStatus(201)
            ->assertJsonPath('data.provider_code', 'mobile_money')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_public_menu_exposes_online_payment_state(): void
    {
        $this->fakeProfiles($this->tenantStripeProfile());

        $menuUrl = URL::temporarySignedRoute(
            'restaurant.public.menu',
            now()->addHour(),
            ['company' => (string) $this->company->id]
        );

        $this->getJson($menuUrl)
            ->assertOk()
            ->assertJsonPath('data.payments.pay_on_site', true)
            ->assertJsonPath('data.payments.online_providers.0', 'card_online');
    }

    public function test_restaurateur_payment_configuration_endpoint_reflects_profiles(): void
    {
        $this->fakeProfiles($this->tenantStripeProfile(), [
            'profile_id' => 7,
            'operator' => 'orange_money',
            'phone_number' => '+2250700000000',
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/restaurant/payments/configuration')
            ->assertOk()
            ->assertJsonPath('data.online_enabled', true)
            ->assertJsonPath('data.card_online.configured', true)
            ->assertJsonPath('data.mobile_money.configured', true)
            ->assertJsonPath('data.mobile_money.profile_active', true)
            ->assertJsonPath('data.pay_on_site', true);
    }

    public function test_restaurateur_payment_configuration_without_profiles_is_disabled(): void
    {
        $this->fakeProfiles(null, null);
        config()->set('restaurantmanager.mobile_money.sandbox', false);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/restaurant/payments/configuration')
            ->assertOk()
            ->assertJsonPath('data.online_enabled', false)
            ->assertJsonPath('data.card_online.configured', false)
            ->assertJsonPath('data.mobile_money.configured', false);
    }
}
