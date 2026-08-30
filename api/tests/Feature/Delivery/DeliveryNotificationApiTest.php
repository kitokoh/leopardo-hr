<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use App\Modules\Delivery\Domain\Models\DeliveryRecipientOptOut;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-206 (#6290) — Notifications destinataire (contrat BC-13).
 *
 * - chaque événement de tracking planifie la notification (outbox, template
 *   versionné) ; rejeu d'événement (idempotent) → PAS de doublon ;
 * - opt-out effectif : un numéro opt-out arrête les notifications planifiées ;
 * - envoi asynchrone (job tenant-scoped, retry borné) ;
 * - RGPD : numéro masqué hors admin, aucune PII dans les logs.
 */
class DeliveryNotificationApiTest extends TestCase
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

    private function manager(string $role = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => $role,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function deliveryWithPhone(?string $phone = '+213555010203'): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-444001',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'assigned',
            'dropoff_contact' => 'Client',
            'dropoff_phone' => $phone,
            'dropoff_address' => 'Alger',
        ]);

        return $delivery;
    }

    public function test_event_schedules_notification(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->deliveryWithPhone();

        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

        $notification = DeliveryNotification::query()->where('delivery_id', $delivery->id)->first();
        self::assertNotNull($notification);
        self::assertSame('delivery.in_transit', $notification->template_key);
        self::assertSame('pending', $notification->status);
        self::assertSame('whatsapp', $notification->channel);
    }

    public function test_event_replay_does_not_duplicate_notification(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->deliveryWithPhone();

        $payload = [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
            'event_at' => '2026-09-01 10:30:00',
        ];

        $this->postJson('/api/v1/delivery/deliveries/events', $payload)->assertStatus(201);
        $this->postJson('/api/v1/delivery/deliveries/events', $payload)->assertStatus(201); // rejeu → existant

        self::assertSame(1, DeliveryNotification::query()->where('delivery_id', $delivery->id)->count());
    }

    public function test_opt_out_stops_scheduled_notifications(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->deliveryWithPhone();

        // Opt-out du numéro du destinataire.
        $this->postJson('/api/v1/delivery/deliveries/notifications/opt-out', [
            'phone' => '+213555010203',
        ])->assertStatus(201);

        // L'événement suivant ne planifie PAS de notification (skipped).
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

        $notification = DeliveryNotification::query()->where('delivery_id', $delivery->id)->first();
        self::assertNotNull($notification);
        self::assertSame('skipped', $notification->status);

        self::assertSame(1, DeliveryRecipientOptOut::query()->count());
    }

    public function test_dispatch_job_marks_sent(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->manager());
        $delivery = $this->deliveryWithPhone();

        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'arrived',
        ])->assertStatus(201);

        $notification = DeliveryNotification::query()->where('delivery_id', $delivery->id)->firstOrFail();

        // Exécution synchrone du job (seam BC-13 → succès).
        (new \App\Jobs\DispatchDeliveryNotificationJob((int) $notification->id))
            ->handle(app(\App\Modules\Delivery\Domain\Contracts\RecipientMessageContract::class));

        $notification->refresh();
        self::assertSame('sent', $notification->status);
        self::assertSame(1, $notification->attempts);
        self::assertNotNull($notification->sent_at);
    }

    public function test_list_masks_phone_for_manager_and_shows_for_admin(): void
    {
        Sanctum::actingAs($this->manager('principal'));
        $delivery = $this->deliveryWithPhone('+213555010203');
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

        // Manager principal (admin) : numéro en clair.
        $this->getJson('/api/v1/delivery/deliveries/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.recipient_phone', '+213555010203')
            ->assertJsonPath('meta.phone_masked', false);

        // Manager simple : numéro masqué (RGPD).
        Sanctum::actingAs($this->manager('rh'));
        $this->getJson('/api/v1/delivery/deliveries/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.recipient_phone', '+213…03')
            ->assertJsonPath('meta.phone_masked', true);
    }

    public function test_notifications_are_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());
        $delivery = $this->deliveryWithPhone();
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => $delivery->id,
            'type' => 'picked_up',
        ])->assertStatus(201);

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
        Sanctum::actingAs($managerB);

        $this->getJson('/api/v1/delivery/deliveries/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
