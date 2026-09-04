<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Infrastructure\Services\TravelAgentPushConsumer;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-703/704 (#6090/#6091) — Push agents (FCM) + sync offline mobile.
 *
 * - 703 : à la création/confirmation d'une réservation, les agents du
 *   tenant (rôles manage) reçoivent un push FCM.
 * - 704 : la file offline est rejouée IDEMPOTEMENT (rejeu → duplicate,
 *   jamais de doublon).
 */
class TravelMobileApiTest extends TestCase
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

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function publishedTrip(Company $company): TravelTrip
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 20]);
            app(GenerateTripSeatsAction::class)->execute($trip);
            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            return $trip;
        });
    }

    public function test_agents_receive_push_on_booking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Deux agents manage (rh + principal) — pas d'employé simple.
        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            Employee::factory()->create(['company_id' => $company->id, 'role' => 'manager', 'manager_role' => 'rh']);
            Employee::factory()->create(['company_id' => $company->id, 'role' => 'manager', 'manager_role' => 'principal']);
        });

        $trip = $this->publishedTrip($company);
        $classId = $trip->prices()->value('class_id');

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'push-1',
            'passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $classId]],
        ])->assertStatus(201)->json('data');

        // Consumer testé en isolation avec un mock du service push : tous les
        // agents manage du tenant reçoivent un push FCM (TRAVEL-703).
        // 3 agents : le principal du test + rh + principal créés ci-dessus.
        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToUser')->times(3);

        $consumer = new TravelAgentPushConsumer($push);
        $this->assertTrue($consumer->supports('travel.booking.pending.v1'));
        $this->assertFalse($consumer->supports('travel.trip.published.v1'));

        $consumer->handle([
            'company_id' => $company->id,
            'event_id' => 77,
            'event_type' => 'travel.booking.pending.v1',
            'booking_reference' => $booking['reference'],
        ]);

        // Le mock vérifie les 2 appels (expectation times(2)).
        $this->addToAssertionCount(1);
    }

    public function test_offline_sync_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $trip = $this->publishedTrip($company);
        $classId = $trip->prices()->value('class_id');

        $operation = [
            'type' => 'booking.create',
            'idempotency_key' => 'offline-1',
            'payload' => [
                'trip_id' => $trip->id,
                'passengers' => [['full_name' => 'Agent Offline', 'age_category' => 'adult', 'class_id' => $classId]],
                'contact_email' => 'agent@example.com',
            ],
        ];

        $payload = ['operations' => [$operation]];

        $first = $this->postJson('/api/v1/travel/mobile/sync', $payload)
            ->assertOk()
            ->json('data');

        $this->assertSame('created', $first[0]['status']);
        $this->assertArrayHasKey('booking_reference', $first[0]);

        // Rejeu : duplicate, aucune nouvelle réservation.
        $second = $this->postJson('/api/v1/travel/mobile/sync', $payload)
            ->assertOk()
            ->json('data');

        $this->assertSame('duplicate', $second[0]['status']);
        $this->assertSame($first[0]['booking_reference'], $second[0]['booking_reference']);
        $this->assertSame(1, TravelBooking::query()->where('company_id', $company->id)->count());
    }

    public function test_offline_sync_reports_invalid_operations(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $result = $this->postJson('/api/v1/travel/mobile/sync', [
            'operations' => [
                ['type' => 'booking.create', 'idempotency_key' => 'bad-1', 'payload' => ['trip_id' => 99999999]],
                ['type' => 'unknown.op', 'idempotency_key' => 'bad-2', 'payload' => ['x' => 1]],
            ],
        ])->assertOk()->json('data');

        $this->assertSame('error', $result[0]['status']);
        $this->assertSame('error', $result[1]['status']);
    }
}
