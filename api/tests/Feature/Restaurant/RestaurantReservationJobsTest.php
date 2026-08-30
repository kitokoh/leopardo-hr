<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-608 (#6213) — Jobs no-show + rappels (notification).
 *
 * Couvre le passage no-show unique (idempotent) et le rappel dédupliqué par
 * (réservation, jour) — pas de double rappel.
 */
class RestaurantReservationJobsTest extends TestCase
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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_no_show_is_unique_and_reminder_deduplicated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $branch = RestaurantBranch::factory()->create();

            // Réservation confirmée en retard (grace 2h dépassée) → no-show.
            RestaurantReservation::factory()->create([
                'branch_id' => $branch->id,
                'reserved_at' => now()->subHours(3),
                'status' => 'confirmed',
            ]);

            // Réservation à venir dans la fenêtre de rappel (24 h).
            RestaurantReservation::factory()->create([
                'branch_id' => $branch->id,
                'reserved_at' => now()->addHours(6),
                'status' => 'confirmed',
            ]);

            $this->artisan('leopardo:restaurant:reservation-jobs', ['company' => $company->id])
                ->assertSuccessful();

            $this->assertSame(1, RestaurantReservation::query()->where('status', 'no_show')->count());
            $this->assertSame(1, RestaurantOutboxEvent::query()
                ->where('event_type', 'restaurant.reservation.reminder.v1')
                ->count());

            // Rejeu → aucun doublon (no-show déjà passé, rappel dédupliqué).
            $this->artisan('leopardo:restaurant:reservation-jobs', ['company' => $company->id])
                ->assertSuccessful();

            $this->assertSame(1, RestaurantReservation::query()->where('status', 'no_show')->count());
            $this->assertSame(1, RestaurantOutboxEvent::query()
                ->where('event_type', 'restaurant.reservation.reminder.v1')
                ->count());
        });
    }
}
