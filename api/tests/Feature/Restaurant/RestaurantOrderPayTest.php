<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Infrastructure\Services\PaymentCallbackSigner;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-407 (#6194) — Encaissement POST /orders/{order}/pay + callback signé.
 *
 * Couvre : montant vérifié serveur (mismatch → 422), double paiement
 * impossible (409), rejeu idempotent (même idempotency_key → même paiement),
 * événements outbox (payment.confirmed + order.paid), et le callback signé
 * HMAC : rejeu du callback 2× → 1 seul paiement confirmé (critère
 * d'acceptation), signature invalide → 401, montant incohérent → échec.
 */
class RestaurantOrderPayTest extends TestCase
{
    use RefreshTenantDatabase;

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{branch: RestaurantBranch, order: RestaurantOrder, totalMinor: int}
     */
    private function makePayableOrder(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();
            $taxRate = RestaurantTaxRate::factory()->create(['rate_bps' => 0]);
            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 1500,
                'currency' => $branch->currency,
                'tax_rate_id' => $taxRate->id,
                'is_available' => true,
            ]);
            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'served',
                'currency' => $branch->currency,
            ]);

            // Une ligne active pour un total cohérent.
            \App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price_minor' => 1500,
                'line_total_minor' => 1500,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            $order->forceFill([
                'subtotal_minor' => 1500,
                'tax_minor' => 0,
                'total_minor' => 1500,
            ])->save();

            return ['branch' => $branch, 'order' => $order, 'totalMinor' => 1500];
        });
    }

    public function test_cash_payment_confirms_and_marks_order_paid(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1500,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.amount_minor', 1500);

        $order->refresh();
        $this->assertSame('paid', $order->status->value);

        // Événements outbox publiés.
        $events = app(TenantManager::class)->withinTenant($company, fn (): array => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->whereIn('event_type', ['restaurant.payment.confirmed.v1', 'restaurant.order.paid.v1'])
            ->pluck('event_type')->all());

        $this->assertContains('restaurant.payment.confirmed.v1', $events);
        $this->assertContains('restaurant.order.paid.v1', $events);
    }

    public function test_amount_mismatch_is_rejected_422(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 999,
        ])->assertStatus(422);
    }

    public function test_double_payment_is_impossible(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", ['provider_code' => 'cash', 'amount_minor' => 1500])->assertStatus(201);

        $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", ['provider_code' => 'cash', 'amount_minor' => 1500])
            ->assertStatus(409);
    }

    public function test_replay_with_same_idempotency_key_returns_same_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $key = (string) \Illuminate\Support\Str::uuid();

        $first = $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1500,
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $replay = $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'cash',
            'amount_minor' => 1500,
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
    }

    public function test_mobile_money_callback_confirms_payment_once(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        // Initiation mobile money → pending + référence provider.
        $payment = $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'mobile_money',
            'amount_minor' => 1500,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $paymentId = $payment->json('data.id');
        $providerReference = $payment->json('data.provider_reference');

        $order->refresh();
        $this->assertSame('served', $order->status->value); // pas encore payée

        // Callback signé (payload JSON + en-tête HMAC).
        $payload = json_encode([
            'company_id' => $company->id,
            'provider_reference' => $providerReference,
            'amount_minor' => 1500,
            'currency' => 'DZD',
            'status' => 'confirmed',
        ], JSON_THROW_ON_ERROR);

        $signer = app(PaymentCallbackSigner::class);
        $signature = $signer->sign($payload, $company->id);

        $headers = ['X-Leopardo-Signature' => $signature];

        $this->postJson("/api/v1/restaurant/payments/{$paymentId}/callback", json_decode($payload, true), $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        // Rejeu du callback → toujours 1 seul paiement confirmé.
        $this->postJson("/api/v1/restaurant/payments/{$paymentId}/callback", json_decode($payload, true), $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $confirmedCount = app(TenantManager::class)->withinTenant($company, fn (): int => RestaurantOrderPayment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->count());

        $this->assertSame(1, $confirmedCount);

        $order->refresh();
        $this->assertSame('paid', $order->status->value);
    }

    public function test_callback_with_invalid_signature_is_rejected_401(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $payment = $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'mobile_money',
            'amount_minor' => 1500,
        ])->assertStatus(201)->json('data');

        $payload = json_encode([
            'company_id' => $company->id,
            'provider_reference' => $payment['provider_reference'],
            'amount_minor' => 1500,
            'status' => 'confirmed',
        ], JSON_THROW_ON_ERROR);

        $this->postJson("/api/v1/restaurant/payments/{$payment['id']}/callback", json_decode($payload, true), [
            'X-Leopardo-Signature' => 'sha256=forged',
        ])->assertStatus(401);
    }

    public function test_callback_with_amount_mismatch_fails_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['order' => $order] = $this->makePayableOrder($company);

        $payment = $this->postJson("/api/v1/restaurant/orders/{$order->id}/pay", [
            'provider_code' => 'mobile_money',
            'amount_minor' => 1500,
        ])->assertStatus(201)->json('data');

        $payload = json_encode([
            'company_id' => $company->id,
            'provider_reference' => $payment['provider_reference'],
            'amount_minor' => 1,
            'status' => 'confirmed',
        ], JSON_THROW_ON_ERROR);

        $signer = app(PaymentCallbackSigner::class);

        $this->postJson("/api/v1/restaurant/payments/{$payment['id']}/callback", json_decode($payload, true), [
            'X-Leopardo-Signature' => $signer->sign($payload, $company->id),
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'failed');
    }
}
