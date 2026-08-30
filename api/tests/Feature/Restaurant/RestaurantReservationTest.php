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
 * RESTO-602 (#6207) — Disponibilité de créneaux (tables, couverts, dates).
 * RESTO-603 (#6208) — Arrhes/dépôt + politique d'annulation (pénalités serveur).
 */
class RestaurantReservationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_two_reservations_cannot_overlap_on_same_table(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'capacity' => 4]);

            $payload = [
                'branch_id' => $branch->id,
                'contact_name' => 'Alice',
                'contact_phone' => '+237600000001',
                'reserved_at' => '2026-09-15 20:00:00',
                'covers' => 2,
                'table_id' => $table->id,
            ];

            $this->postJson('/api/v1/restaurant/reservations', $payload)->assertStatus(201);

            // Même table à +1h (dans la fenêtre de 2h) → 409.
            $overlap = $payload;
            $overlap['reserved_at'] = '2026-09-15 21:00:00';
            $this->postJson('/api/v1/restaurant/reservations', $overlap)->assertStatus(409);

            // Table libre 3h plus tard → OK.
            $later = $payload;
            $later['reserved_at'] = '2026-09-15 23:30:00';
            $this->postJson('/api/v1/restaurant/reservations', $later)->assertStatus(201);
        });
    }

    public function test_reservation_transitions_confirm_checkin_noshow(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->manager($company);

        $reservationId = app(TenantManager::class)->withinTenant($company, function (): int {
            $branch = RestaurantBranch::factory()->create();

            $this->postJson('/api/v1/restaurant/reservations', [
                'branch_id' => $branch->id,
                'contact_name' => 'Bob',
                'contact_phone' => '+237600000002',
                'reserved_at' => '2026-09-20 19:30:00',
                'covers' => 2,
            ])->assertStatus(201);

            return RestaurantReservation::query()->firstOrFail()->id;
        });

        $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'seated');

        $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/no-show")
            ->assertStatus(422); // seated → no-show interdit
    }

    public function test_availability_returns_free_tables_by_covers(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            $small = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'label' => 'T1', 'capacity' => 2]);
            $large = RestaurantTable::factory()->create(['branch_id' => $branch->id, 'label' => 'T2', 'capacity' => 6]);

            // Réservation confirmée sur la grande table à 20:00.
            RestaurantReservation::factory()->create([
                'branch_id' => $branch->id,
                'table_id' => $large->id,
                'reserved_at' => '2026-09-18 20:00:00',
                'status' => 'confirmed',
            ]);

            // 4 couverts : seule la grande table convient mais elle est prise → aucune.
            $this->getJson('/api/v1/restaurant/reservations/availability?branch_id='.$branch->id.'&date=2026-09-18&covers=4&start=19:00&end=21:00')
                ->assertOk()
                ->assertJsonPath('data.count', 0);

            // 2 couverts : petite table libre → disponible.
            $this->getJson('/api/v1/restaurant/reservations/availability?branch_id='.$branch->id.'&date=2026-09-18&covers=2&start=19:00&end=21:00')
                ->assertOk()
                ->assertJsonPath('data.count', 1)
                ->assertJsonPath('data.available_tables.0.table_id', $small->id);
        });
    }

    public function test_deposit_and_cancellation_policy_are_server_side(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();

            // Politique : annulation gratuite au-delà de 24 h, pénalité 25 % sinon.
            $this->putJson("/api/v1/restaurant/branches/{$branch->id}/cancellation-policy", [
                'cancel_free_hours' => 24,
                'cancel_fee_bps' => 2500,
            ])->assertOk();

            $this->postJson('/api/v1/restaurant/reservations', [
                'branch_id' => $branch->id,
                'contact_name' => 'Carol',
                'contact_phone' => '+237600000003',
                'reserved_at' => now()->addHours(2)->toDateTimeString(),
                'covers' => 2,
                'deposit_minor' => 10000,
            ])->assertStatus(201);

            $reservationId = RestaurantReservation::query()->firstOrFail()->id;

            // Dépôt déjà enregistré → 422.
            $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/deposit", ['amount_minor' => 5000])
                ->assertStatus(422);

            // Annulation à J-2h : pénalité 25 % de 10000 = 2500, remboursable 7500.
            $this->postJson("/api/v1/restaurant/reservations/{$reservationId}/cancel")
                ->assertOk()
                ->assertJsonPath('data.status', 'cancelled')
                ->assertJsonPath('penalty_minor', 2500)
                ->assertJsonPath('refundable_minor', 7500);
        });
    }
}
