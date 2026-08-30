<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryTrackingShare;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-204 (#6288) — Suivi temps réel : événements idempotents + lien
 * public borné.
 *
 * - événements : transitions via la machine à états, `delivered` exige POD ;
 * - idempotence : rejeu (idempotency_key ET doublon type/event_at) → 1 événement ;
 * - lien public : token expirant, anti-énumération, sans auth, champs limités ;
 * - RBAC 401/403 + isolation tenant.
 */
class DeliveryTrackingApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function createDelivery(?string $status = 'assigned'): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'source' => 'manual',
            'type' => 'parcel',
            'status' => $status,
            'cod_amount_minor' => 5000,
            'dropoff_contact' => 'Client',
            'dropoff_address' => '12 Rue Didouche, Alger',
        ]);

        return $delivery;
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => 1,
            'type' => 'picked_up',
        ])->assertStatus(401);
    }

    public function test_event_drives_state_machine_transition(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
            'latitude' => 36.7538,
            'longitude' => 3.0588,
            'origin' => 'mobile',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.type', 'picked_up');

        $this->assertDatabaseHas('delivery_deliveries', [
            'id' => $delivery->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_event_rejects_illegal_transition(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        // created → delivered : saut d'étape interdit (et POD absente).
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'delivered',
        ])->assertStatus(409);
    }

    public function test_delivered_requires_proof(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery(status: 'arrived');

        // Sans proof_document_id → refus explicite.
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'delivered',
        ])->assertStatus(409)
            ->assertJson(['message' => 'PROOF_REQUIRED']);

        // Avec POD → livré.
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'delivered',
            'proof_document_id' => 777,
        ])->assertStatus(201)
            ->assertJsonPath('data.payload.proof_document_id', 777);

        $this->assertDatabaseHas('delivery_deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
        ]);
    }

    public function test_event_replay_with_idempotency_key_returns_single_event(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        $payload = [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
            'idempotency_key' => '9b2f4c1e-0000-4000-8000-000000000001',
        ];

        $first = $this->postJson('/api/v1/delivery/deliveries/events', $payload)->assertStatus(201);
        $second = $this->postJson('/api/v1/delivery/deliveries/events', $payload)->assertStatus(201);

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, Delivery::query()->find($delivery->id)->events()->count());
    }

    public function test_event_duplicate_type_and_event_at_is_deduped(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        $body = [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
            'event_at' => '2026-09-01 10:30:00',
        ];

        $this->postJson('/api/v1/delivery/deliveries/events', $body)->assertStatus(201);
        $this->postJson('/api/v1/delivery/deliveries/events', $body)->assertStatus(201);

        self::assertSame(1, $delivery->events()->count());
    }

    public function test_tracking_link_and_public_timeline(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
            'event_at' => '2026-09-01 10:30:00',
        ])->assertStatus(201);

        $link = $this->postJson(sprintf('/api/v1/delivery/deliveries/%d/tracking-link', $delivery->id))
            ->assertOk()
            ->json('data');

        self::assertStringContainsString('/api/v1/deliveries/tracking/', $link['tracking_url']);
        self::assertNotNull($link['expires_at']);

        // Consultation publique SANS auth — champs limités au suivi.
        $token = (string) str($link['tracking_url'])->afterLast('/');
        $this->getJson('/api/v1/deliveries/tracking/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'picked_up')
            ->assertJsonPath('data.dropoff_address', '12 Rue Didouche, Alger')
            ->assertJsonCount(1, 'data.events')
            ->assertJsonMissing(['cod_amount_minor' => 5000]);

        // Anti-énumération : token aléatoire → 404.
        $this->getJson('/api/v1/deliveries/tracking/'.str_repeat('a', 64))->assertStatus(404);
    }

    public function test_public_link_expires(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        $share = DeliveryTrackingShare::query()->create([
            'company_id' => $this->company->id,
            'delivery_id' => $delivery->id,
            'share_token' => str_repeat('b', 64),
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->getJson('/api/v1/deliveries/tracking/'.$share->share_token)
            ->assertStatus(404)
            ->assertJson(['error' => 'RESOURCE_NOT_FOUND']);
    }

    public function test_events_are_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->createDelivery();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        // Un événement sur une livraison d'un AUTRE tenant → 404 sûr.
        Sanctum::actingAs($managerB);
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(404);

        // La ligne du temps interne est scopée tenant, elle aussi.
        $this->getJson(sprintf('/api/v1/delivery/deliveries/%d/tracking', $delivery->id))->assertStatus(404);
    }
}
