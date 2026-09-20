<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Shared\Events\RetailOnlineOrderConfirmed;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 → BC-26 (#7811) — Handoff livraison des commandes en ligne
 * Leopardo Marché : la confirmation (`pending → confirmed`) émet
 * `RetailOnlineOrderConfirmed` et le listener Delivery crée la livraison
 * `source = retail_online` (COD reporté), idempotente sur
 * (company_id, source, source_reference). Isolation tenant, annulation sans
 * livraison, gating par feature flag `delivery`, et suivi public enrichi de
 * l'état de livraison (DTO fail-closed).
 */
class RetailDeliveryHandoffTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyA->setFeature('retail', true);
        $companyA->setFeature('delivery', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('retail', true);
        $companyB->setFeature('delivery', true);
        $companyB->save();
        $this->companyB = $companyB;

        $this->principalA = $this->principal($this->companyA);
        $this->principalB = $this->principal($this->companyB);
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * Prépare un vendeur prêt à vendre : boutique en ligne activée,
     * emplacement, produit publié en ligne. Retourne l'id produit.
     */
    private function readySeller(Employee $actor, Company $company): int
    {
        $this->actingAsUser($actor);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique '.$company->slug,
            'city' => 'Dakar',
            'currency' => (string) $company->currency,
        ])->assertStatus(200);

        $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-'.Str::upper(Str::random(4)),
        ])->assertStatus(201);

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'price_minor' => 1_500,
            'currency' => (string) $company->currency,
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        return (int) $product['id'];
    }

    /**
     * Checkout invité public → payload {reference, tracking_token, ...}.
     *
     * @return array<string, mixed>
     */
    private function checkout(Company $company, int $productId, int $quantity = 2): array
    {
        return $this->postJson('/api/v1/public/market/orders', [
            'seller' => (string) $company->slug,
            'items' => [['product_id' => $productId, 'quantity' => $quantity]],
            'customer' => [
                'name' => 'Awa Ndiaye',
                'phone' => '+221770000000',
                'email' => 'awa@example.test',
            ],
            'delivery' => [
                'address' => '12 rue des Manguiers',
                'city' => 'Dakar',
                'notes' => 'Appeler avant livraison',
            ],
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201)->json('data');
    }

    private function orderId(Company $company, string $reference): int
    {
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $company->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return (int) $order->id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Delivery>
     */
    private function deliveriesFor(Company $company, string $reference): \Illuminate\Database\Eloquent\Builder
    {
        return Delivery::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $company->id)
            ->where('source', 'retail_online')
            ->where('source_reference', $reference);
    }

    public function test_confirmation_creates_retail_online_delivery_with_cod(): void
    {
        $productId = $this->readySeller($this->principalA, $this->companyA);
        $order = $this->checkout($this->companyA, $productId, 2);
        $reference = (string) $order['reference'];

        // pending : aucune livraison.
        $this->assertSame(0, (int) $this->deliveriesFor($this->companyA, $reference)->count());

        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($this->companyA, $reference).'/confirm')
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'confirmed');

        /** @var Delivery $delivery */
        $delivery = $this->deliveriesFor($this->companyA, $reference)->firstOrFail();

        $this->assertSame('retail_online', $delivery->source);
        $this->assertSame($reference, $delivery->source_reference);
        $this->assertSame('created', $delivery->status);
        $this->assertSame('parcel', $delivery->type);
        $this->assertStringStartsWith('DLV-', $delivery->reference);
        // COD v1 : montant à encaisser = total serveur (2 × 1500) + devise.
        $this->assertSame(3_000, $delivery->cod_amount_minor);
        $this->assertSame('XOF', $delivery->cod_currency);
        $this->assertSame('Awa Ndiaye', $delivery->dropoff_contact);
        $this->assertSame('+221770000000', $delivery->dropoff_phone);
        $this->assertSame('12 rue des Manguiers, Dakar', $delivery->dropoff_address);
    }

    public function test_replayed_confirmation_event_never_duplicates_the_delivery(): void
    {
        $productId = $this->readySeller($this->principalA, $this->companyA);
        $order = $this->checkout($this->companyA, $productId, 1);
        $reference = (string) $order['reference'];
        $orderId = $this->orderId($this->companyA, $reference);

        $this->actingAsUser($this->principalA);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(200);

        // Double confirm : transition refusée (422), aucun nouvel événement.
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(422);

        // Rejeu BRUT de l'événement (retry, redélivrance) : l'idempotence
        // (company_id, source, source_reference) retourne l'existante.
        /** @var TenantManager $tenants */
        $tenants = app(TenantManager::class);
        $tenants->withinTenant($this->companyA, function () use ($reference): void {
            event(new RetailOnlineOrderConfirmed(
                companyId: (string) $this->companyA->id,
                orderId: 0,
                reference: $reference,
                totalMinor: 1_500,
                currency: 'XOF',
                customerName: 'Awa Ndiaye',
                customerPhone: '+221770000000',
                deliveryAddress: '12 rue des Manguiers',
                deliveryCity: 'Dakar',
                deliveryNotes: null,
            ));
        });

        $this->assertSame(1, (int) $this->deliveriesFor($this->companyA, $reference)->count());
    }

    public function test_cancellation_of_pending_order_creates_no_delivery(): void
    {
        $productId = $this->readySeller($this->principalA, $this->companyA);
        $order = $this->checkout($this->companyA, $productId, 1);
        $reference = (string) $order['reference'];

        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($this->companyA, $reference).'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'cancelled');

        $this->assertSame(0, (int) $this->deliveriesFor($this->companyA, $reference)->count());
        $this->assertSame(0, (int) Delivery::query()->withoutGlobalScope('company')->count());
    }

    public function test_seller_without_delivery_feature_gets_no_delivery(): void
    {
        // Vendeur dédié SANS module BC-26 (feature `delivery` jamais activée).
        /** @var Company $companyC */
        $companyC = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $companyC->setFeature('retail', true);
        $companyC->save();
        $principalC = $this->principal($companyC);

        $productId = $this->readySeller($principalC, $companyC);
        $order = $this->checkout($companyC, $productId, 1);
        $reference = (string) $order['reference'];

        $this->actingAsUser($principalC);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($companyC, $reference).'/confirm')
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'confirmed');

        // La confirmation aboutit, mais aucune livraison n'est créée.
        $this->assertSame(0, (int) Delivery::query()->withoutGlobalScope('company')->count());
    }

    public function test_delivery_is_isolated_per_tenant(): void
    {
        $productA = $this->readySeller($this->principalA, $this->companyA);
        $productB = $this->readySeller($this->principalB, $this->companyB);

        $orderA = $this->checkout($this->companyA, $productA, 1);
        $orderB = $this->checkout($this->companyB, $productB, 1);

        $referenceA = (string) $orderA['reference'];
        $referenceB = (string) $orderB['reference'];

        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($this->companyA, $referenceA).'/confirm')
            ->assertStatus(200);

        $this->actingAsUser($this->principalB);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($this->companyB, $referenceB).'/confirm')
            ->assertStatus(200);

        // Chaque tenant a SA livraison, aucune fuite croisée.
        $this->assertSame(1, (int) $this->deliveriesFor($this->companyA, $referenceA)->count());
        $this->assertSame(1, (int) $this->deliveriesFor($this->companyB, $referenceB)->count());
        $this->assertSame(0, (int) $this->deliveriesFor($this->companyA, $referenceB)->count());
        $this->assertSame(0, (int) $this->deliveriesFor($this->companyB, $referenceA)->count());
    }

    public function test_public_tracking_exposes_delivery_status_fail_closed(): void
    {
        $productId = $this->readySeller($this->principalA, $this->companyA);
        $order = $this->checkout($this->companyA, $productId, 1);
        $reference = (string) $order['reference'];
        $token = (string) $order['tracking_token'];

        // Avant confirmation : champ `delivery` null (optionnel, défensif).
        $this->getJson('/api/v1/public/market/orders/'.$reference.'?token='.$token)
            ->assertStatus(200)
            ->assertJsonPath('data.delivery', null);

        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/retail/online/orders/'.$this->orderId($this->companyA, $reference).'/confirm')
            ->assertStatus(200);

        $tracked = $this->getJson('/api/v1/public/market/orders/'.$reference.'?token='.$token)
            ->assertStatus(200)
            ->assertJsonPath('data.delivery.status', 'created');

        // DTO fail-closed : statut + horodatages publics, RIEN d'autre
        // (ni id, ni montants COD, ni coordonnées, ni référence interne).
        $this->assertSame(
            ['status', 'created_at', 'delivered_at', 'failed_at', 'returned_at'],
            array_keys((array) $tracked->json('data.delivery'))
        );
    }
}
