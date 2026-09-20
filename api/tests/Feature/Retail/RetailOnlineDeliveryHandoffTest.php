<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7811) — Handoff livraison BC-26 : a la confirmation d'une
 * commande en ligne Leopardo Marche, la livraison est creee automatiquement
 * par EVENEMENT (`RetailOnlineOrderConfirmed` → listener Delivery), avec
 * `source=retail_online`, `source_reference=WEB-…`, COD = solde non
 * encaisse, anti-doublon par l'unicite (company, source, source_reference).
 * Le retour `RetailOnlineOrderDeliveryCreated` stocke la reference DLV-…
 * sur la commande Retail et la page de suivi publique l'expose.
 */
class RetailOnlineDeliveryHandoffTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $company->setFeature('retail', true);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;
    }

    /**
     * Prepare boutique + produit en ligne + stock, passe un checkout invite
     * public et retourne [orderId, reference, trackingToken].
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function placeOnlineOrder(): array
    {
        Sanctum::actingAs($this->principal);

        $this->putJson('/api/v1/retail/online/settings', [
            'enabled' => true,
            'shop_name' => 'Boutique Handoff',
            'city' => 'Dakar',
            'currency' => 'XOF',
        ])->assertStatus(200);

        $location = $this->postJson('/api/v1/retail/locations', [
            'name' => 'Boutique centre-ville',
            'code' => 'STORE-01',
        ])->assertStatus(201)->json('data');

        $product = $this->postJson('/api/v1/retail/products', [
            'name' => 'Jus de bissap 50cl',
            'sku' => 'SKU-BISSAP-50',
            'price_minor' => 1_500,
            'currency' => 'XOF',
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/retail/products/{$product['id']}/publish")->assertStatus(200);
        $this->postJson("/api/v1/retail/products/{$product['id']}/publish-online")->assertStatus(200);

        $this->postJson('/api/v1/retail/stock/movements', [
            'location_id' => $location['id'],
            'product_id' => $product['id'],
            'quantity_delta' => 10,
            'reason_code' => 'purchase',
        ])->assertStatus(201);

        $data = $this->postJson('/api/v1/public/market/orders', [
            'seller' => (string) $this->company->slug,
            'items' => [['product_id' => (int) $product['id'], 'quantity' => 2]],
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

        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->company->id)
            ->where('reference', $data['reference'])
            ->firstOrFail();

        return [(int) $order->id, (string) $order->reference, (string) $order->tracking_token];
    }

    public function test_confirmation_creates_bc26_delivery_with_cod_and_shares_reference(): void
    {
        [$orderId, $reference, $token] = $this->placeOnlineOrder();

        Sanctum::actingAs($this->principal);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'confirmed');

        /** @var Delivery $delivery */
        $delivery = Delivery::query()
            ->where('company_id', (string) $this->company->id)
            ->where('source', 'retail_online')
            ->where('source_reference', $reference)
            ->firstOrFail();

        $this->assertSame('created', $delivery->status);
        $this->assertSame(3_000, (int) $delivery->cod_amount_minor);
        $this->assertSame(3_000, (int) $delivery->declared_value_minor);
        $this->assertSame('Awa Ndiaye', $delivery->dropoff_contact);
        $this->assertStringContainsString('12 rue des Manguiers', (string) $delivery->dropoff_address);
        $this->assertStringContainsString('Dakar', (string) $delivery->dropoff_address);

        // Retour d'evenement : la reference DLV-… est stockee cote Retail…
        /** @var RetailOrder $order */
        $order = RetailOrder::query()
            ->where('company_id', (string) $this->company->id)
            ->findOrFail($orderId);
        $this->assertSame($delivery->reference, $order->delivery_reference);

        // …et partagee sur la page de suivi publique.
        $this->getJson("/api/v1/public/market/orders/{$reference}?token={$token}")
            ->assertStatus(200)
            ->assertJsonPath('data.delivery.reference', $delivery->reference);
    }

    public function test_handoff_is_idempotent_no_duplicate_delivery(): void
    {
        [$orderId, $reference] = $this->placeOnlineOrder();

        Sanctum::actingAs($this->principal);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")->assertStatus(200);

        // Rejeu de l'evenement (webhook/retry) : la livraison existante est
        // retournee, aucune seconde ligne (unicite company/source/reference).
        event(new \App\Events\RetailOnlineOrderConfirmed(
            companyId: (string) $this->company->id,
            orderId: $orderId,
            reference: $reference,
            totalMinor: 3_000,
            currency: 'XOF',
            codAmountMinor: 3_000,
            customerName: 'Awa Ndiaye',
            customerPhone: '+221770000000',
            deliveryAddress: '12 rue des Manguiers',
            deliveryCity: 'Dakar',
            deliveryNotes: null,
        ));

        $this->assertSame(1, Delivery::query()
            ->where('company_id', (string) $this->company->id)
            ->where('source', 'retail_online')
            ->where('source_reference', $reference)
            ->count());
    }

    public function test_no_delivery_when_tenant_has_no_delivery_module(): void
    {
        $this->company->setFeature('delivery', false);
        $this->company->save();

        [$orderId, $reference] = $this->placeOnlineOrder();

        Sanctum::actingAs($this->principal);
        $this->postJson("/api/v1/retail/online/orders/{$orderId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.fulfillment_status', 'confirmed');

        $this->assertSame(0, Delivery::query()
            ->where('company_id', (string) $this->company->id)
            ->where('source', 'retail_online')
            ->where('source_reference', $reference)
            ->count());
    }
}
