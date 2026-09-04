<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-608 (#6213) — Jobs no-show et rappels de réservation.
 *
 * Couvre l'exécution et l'idempotence : « pas de double rappel ; no-show
 * unique » (critère d'acceptation RESTO-608).
 */
class RestaurantJobsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_no_show_expire_marks_overdue_confirmed_reservations_once(): void
    {
        $company = $this->company();

        /** @var RestaurantReservation $overdue */
        $overdue = RestaurantReservation::factory()->create([
            'company_id' => $company->id,
            'status' => 'confirmed',
            'reserved_at' => now()->subHours(3),
        ]);

        /** @var RestaurantReservation $future */
        $future = RestaurantReservation::factory()->create([
            'company_id' => $company->id,
            'status' => 'confirmed',
            'reserved_at' => now()->addHours(3),
        ]);

        /** @var RestaurantReservation $cancelled */
        $cancelled = RestaurantReservation::factory()->create([
            'company_id' => $company->id,
            'status' => 'cancelled',
            'reserved_at' => now()->subHours(5),
        ]);

        $this->artisan('restaurant:no-show-expire', ['--grace-minutes' => 60])->assertSuccessful();

        $this->assertSame('no_show', $overdue->refresh()->status->value);
        $this->assertSame('confirmed', $future->refresh()->status->value, 'Une réservation future reste confirmée.');
        $this->assertSame('cancelled', $cancelled->refresh()->status->value, 'Une réservation annulée reste annulée.');

        // Rejeu du job → aucun changement supplémentaire (no-show unique).
        $this->artisan('restaurant:no-show-expire', ['--grace-minutes' => 60])->assertSuccessful();

        $this->assertSame('no_show', $overdue->refresh()->status->value);
        $this->assertSame(1, RestaurantReservation::query()->where('status', 'no_show')->count());
    }

    public function test_reminder_job_publishes_once_per_reservation(): void
    {
        $company = $this->company();

        /** @var RestaurantReservation $due */
        $due = RestaurantReservation::factory()->create([
            'company_id' => $company->id,
            'status' => 'confirmed',
            'reserved_at' => now()->addHours(20),
        ]);

        /** @var RestaurantReservation $far */
        $far = RestaurantReservation::factory()->create([
            'company_id' => $company->id,
            'status' => 'confirmed',
            'reserved_at' => now()->addDays(5),
        ]);

        $this->artisan('restaurant:send-reminders', ['--window-hours' => 24])->assertSuccessful();

        $this->assertNotNull($due->refresh()->reminder_sent_at, 'La réservation due sous 24 h est rappelée.');
        $this->assertNull($far->refresh()->reminder_sent_at, 'Une réservation lointaine n\'est pas rappelée.');

        $events = RestaurantOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', 'restaurant.reservation.reminder.v1')
            ->count();

        $this->assertSame(1, $events, 'Un événement de rappel publié.');

        // Rejeu du job → aucun second rappel (flag reminder_sent_at).
        $this->artisan('restaurant:send-reminders', ['--window-hours' => 24])->assertSuccessful();

        $eventsAfter = RestaurantOutboxEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', 'restaurant.reservation.reminder.v1')
            ->count();

        $this->assertSame(1, $eventsAfter, 'Pas de double rappel.');
    }
}
