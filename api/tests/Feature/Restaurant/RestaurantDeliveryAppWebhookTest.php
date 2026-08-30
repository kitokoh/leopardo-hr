<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\GlovoDeliveryAppAdapter;
use App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps\UberEatsDeliveryAppAdapter;
use Illuminate\Testing\TestResponse;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-806 (#6227) — Intégrations apps de livraison (adaptateurs Uber Eats /
 * Glovo, webhooks entrants signés).
 *
 * Critère d'acceptation : « commande marketplace → même workflow interne ».
 * Couvre : signature HMAC fail-closed (signature absente/invalide → 401),
 * fournisseur inconnu → 422, création de commande via le workflow public
 * (prix du référentiel, jamais ceux de la marketplace), rejeu du même
 * webhook → même commande (pas de doublon).
 */
class RestaurantDeliveryAppWebhookTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{company: Company, branch: RestaurantBranch, product: RestaurantProduct, secret: string}
     */
    private function context(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);

        $ctx = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create(['currency' => 'XAF']);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'code' => 'BURGER-CLASSIC',
                'price_minor' => 2500,
                'currency' => 'XAF',
                'is_available' => true,
            ]);

            return ['branch' => $branch, 'product' => $product];
        });

        $adapter = new UberEatsDeliveryAppAdapter();

        return [
            'company' => $company,
            'branch' => $ctx['branch'],
            'product' => $ctx['product'],
            'secret' => $this->secretFor($adapter, (string) $company->id),
        ];
    }

    private function secretFor(UberEatsDeliveryAppAdapter $adapter, string $companyId): string
    {
        $reflection = new \ReflectionMethod($adapter, 'secretFor');
        $reflection->setAccessible(true);

        return (string) $reflection->invoke($adapter, $companyId);
    }

    private function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * POST brut (corps JSON exact) — la signature HMAC est calculée sur le
     * corps brut ; postJson ré-encoderait le tableau et casserait la
     * vérification.
     */
    private function webhook(string $provider, string $payload, ?string $signature = null): TestResponse
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($signature !== null) {
            $headers['HTTP_X_LEOPARDO_DELIVERY_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/v1/restaurant/webhooks/delivery-apps/'.$provider, [], [], [], $headers, $payload);
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $ctx = $this->context();

        $payload = json_encode(['company_id' => $ctx['company']->id], JSON_THROW_ON_ERROR);

        $this->webhook('deliveroo', $payload)->assertStatus(422);
    }

    public function test_invalid_or_missing_signature_is_rejected(): void
    {
        $ctx = $this->context();

        $payload = json_encode([
            'company_id' => $ctx['company']->id,
            'order' => [
                'external_id' => 'UE-1',
                'items' => [['code' => 'BURGER-CLASSIC', 'quantity' => 1]],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->webhook('uber_eats', $payload, 'sha256=deadbeef')->assertStatus(401);
        $this->webhook('uber_eats', $payload)->assertStatus(401);
    }

    public function test_marketplace_order_enters_internal_workflow_and_replay_does_not_duplicate(): void
    {
        $ctx = $this->context();

        $payload = json_encode([
            'company_id' => $ctx['company']->id,
            'order' => [
                'external_id' => 'UE-42',
                'items' => [['code' => 'BURGER-CLASSIC', 'quantity' => 2]],
                'customer' => ['phone' => '+237600000000'],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->sign($payload, $ctx['secret']);

        $first = $this->webhook('uber_eats', $payload, $signature)
            ->assertStatus(201)
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.total_minor', 5000);

        $reference = $first->json('data.reference');
        $this->assertIsString($reference);

        $this->webhook('uber_eats', $payload, $signature)
            ->assertStatus(200)
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.reference', $reference);
    }

    public function test_adapters_are_registered_with_stable_codes(): void
    {
        $this->assertSame('uber_eats', (new UberEatsDeliveryAppAdapter())->providerCode());
        $this->assertSame('glovo', (new GlovoDeliveryAppAdapter())->providerCode());
    }
}
