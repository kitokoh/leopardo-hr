<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-601 (#6206) — Réservations CRUD + check-in/no-show + conflit 409.
 *
 * Couvre : création (référence RSV-, idempotente), conflit de créneau ±2h
 * sur la même table → 409 (critère d'acceptation), transitions
 * confirm/check-in/no-show/cancel, événement reservation.confirmed.v1 et
 * isolation cross-tenant (404 sûr).
 */
class RestaurantReservationTest extends TestCase
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
     * @return array{branch: RestaurantBranch, table: RestaurantTable}
     */
    private function makeBranchTable(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id]);

            return ['branch' => $branch, 'table' => $table];
        });
    }

    public function test_server_can_create_and_confirm_reservation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch, 'table' => $table] = $this->makeBranchTable($company);

        $reservationId = $this->postJson('/api/v1/restaurant/reservations', [
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'contact_name' => 'Jean Dupont',
            'contact_phone' => '+237600000000',
            'reserved_at' => now()->addDays(2)->setTime(20, 0)->toIso8601String(),
            'covers' => 4,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['reference' => []]])
            ->json('data.id');

        $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        // Événement outbox publié à la confirmation.
        $eventCount = app(TenantManager::class)->withinTenant($company, fn (): int => \App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent::query()
            ->where('event_type', 'restaurant.reservation.confirmed.v1')
            ->count());

        $this->assertSame(1, $eventCount);

        $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/check-in")->assertStatus(200)->assertJsonPath('data.status', 'seated');
    }

    public function test_overlapping_slot_on_same_table_is_refused_409(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch, 'table' => $table] = $this->makeBranchTable($company);

        $slot = now()->addDays(3)->setTime(19, 30);

        $payload = [
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'contact_name' => 'Alice',
            'contact_phone' => '+237611111111',
            'reserved_at' => $slot->toIso8601String(),
            'covers' => 2,
        ];

        $this->postJson('/api/v1/restaurant/reservations', $payload)->assertStatus(201);

        // Même table, +1h30 (dans la fenêtre ±2h) → 409.
        $this->postJson('/api/v1/restaurant/reservations', array_merge($payload, [
            'contact_name' => 'Bob',
            'reserved_at' => $slot->copy()->addHours(1)->addMinutes(30)->toIso8601String(),
        ]))->assertStatus(409);

        // Hors fenêtre (+3h) → OK.
        $this->postJson('/api/v1/restaurant/reservations', array_merge($payload, [
            'contact_name' => 'Bob',
            'reserved_at' => $slot->copy()->addHours(3)->toIso8601String(),
        ]))->assertStatus(201);
    }

    public function test_reservation_replay_with_same_idempotency_key_returns_same(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch] = $this->makeBranchTable($company);

        $key = (string) \Illuminate\Support\Str::uuid();

        $first = $this->postJson('/api/v1/restaurant/reservations', [
            'branch_id' => $branch->id,
            'contact_name' => 'Alice',
            'contact_phone' => '+237622222222',
            'reserved_at' => now()->addDays(4)->setTime(19, 0)->toIso8601String(),
            'covers' => 2,
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $replay = $this->postJson('/api/v1/restaurant/reservations', [
            'branch_id' => $branch->id,
            'contact_name' => 'Alice',
            'contact_phone' => '+237622222222',
            'reserved_at' => now()->addDays(4)->setTime(19, 0)->toIso8601String(),
            'covers' => 2,
            'idempotency_key' => $key,
        ])->assertStatus(200);

        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
    }

    public function test_no_show_and_cancel_transitions(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch] = $this->makeBranchTable($company);

        $id = $this->postJson('/api/v1/restaurant/reservations', [
            'branch_id' => $branch->id,
            'contact_name' => 'Carol',
            'contact_phone' => '+237633333333',
            'reserved_at' => now()->addDays(5)->setTime(20, 30)->toIso8601String(),
            'covers' => 3,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/reservations/{$id}/no-show")->assertStatus(200)->assertJsonPath('data.status', 'no_show');

        // no_show → cancel est hors workflow : 409.
        $this->postJson("/api/v1/restaurant/reservations/{$id}/cancel")->assertStatus(409);

        // Seated → complete.
        $id2 = $this->postJson('/api/v1/restaurant/reservations', [
            'branch_id' => $branch->id,
            'contact_name' => 'Dave',
            'contact_phone' => '+237644444444',
            'reserved_at' => now()->addDays(6)->setTime(19, 0)->toIso8601String(),
            'covers' => 2,
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/restaurant/reservations/{$id2}/confirm")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/reservations/{$id2}/check-in")->assertStatus(200);
        $this->postJson("/api/v1/restaurant/reservations/{$id2}/cancel")->assertStatus(409);
    }

    public function test_other_tenant_reservation_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $otherReservationId = app(TenantManager::class)->withinTenant(
            Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']),
            fn (): int => RestaurantReservation::factory()->create()->id
        );

        $this->getJson("/api/v1/restaurant/reservations/{$otherReservationId}")->assertStatus(404);
    }
}
