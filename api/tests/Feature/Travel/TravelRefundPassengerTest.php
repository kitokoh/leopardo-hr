<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRefund;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-808 (#6098) + TRAVEL-813 (#6103) — Remboursements partiels et
 * politiques d'annulation configurables.
 *
 * Pénalité calculée serveur (élasticité par défaut, surclassée par la
 * politique trajet/classe) ; rejeu sans double remboursement ; libération du
 * siège ; réservation refunded quand tous les passagers sont remboursés.
 */
class TravelRefundPassengerTest extends TestCase
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

    /**
     * Trajet publié dans 2 h (pénalité < 12 h → 25 %) ou dans 48 h (0 %).
     *
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTrip(Company $company, bool $soon = false): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($soon): array {
            $departureDate = $soon ? now()->toDateString() : now()->addDays(2)->toDateString();
            $departureTime = $soon ? now()->addHours(2)->format('H:i') : '08:00';
            $arrivalDate = $soon ? now()->toDateString() : now()->addDays(2)->toDateString();
            $arrivalTime = $soon ? now()->addHours(5)->format('H:i') : '11:00';

            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
                'departure_date' => $departureDate,
                'departure_time' => $departureTime,
                'arrival_date' => $arrivalDate,
                'arrival_time' => $arrivalTime,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    /**
     * Réservation confirmée avec 2 passagers.
     *
     * @return array{booking: TravelBooking, passengerA: int, passengerB: int}
     */
    private function confirmedBooking(Company $company, bool $soon = false): array
    {
        ['trip' => $trip, 'class' => $class] = $this->publishedTrip($company, $soon);

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'refund-bk-'.uniqid(),
            'passengers' => [
                ['full_name' => 'Passager A', 'age_category' => 'adult', 'class_id' => $class->id],
                ['full_name' => 'Passager B', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201);

        $bookingId = $booking->json('data.id');
        $this->postJson("/api/v1/travel/bookings/{$bookingId}/confirm")->assertOk();

        return [
            'booking' => $booking->json('data'),
            'passengerA' => (int) $booking->json('data.passengers.0.id'),
            'passengerB' => (int) $booking->json('data.passengers.1.id'),
        ];
    }

    public function test_partial_refund_without_penalty_when_far_from_departure(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company);

        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-1',
        ])->assertStatus(201)
            ->assertJsonPath('data.amount_minor', 15000)
            ->assertJsonPath('data.penalty_minor', 0);

        // La réservation reste confirmée (remboursement partiel) ; siège libéré.
        $this->assertSame(BookingStatus::CONFIRMED->value, $setup['booking']['status']);
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRefund::query()->count();
        }));
    }

    public function test_replay_with_same_refund_key_does_not_double_refund(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company);

        $payload = [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-dup',
        ];

        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRefund::query()->count();
        }));
    }

    public function test_penalty_applies_within_twelve_hours_of_departure(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company, soon: true);

        // Départ < 12 h → pénalité 25 % : 15 000 − 3 750 = 11 250.
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-soon',
        ])->assertStatus(201)
            ->assertJsonPath('data.amount_minor', 11250)
            ->assertJsonPath('data.penalty_minor', 3750);
    }

    public function test_cancellation_policy_overrides_default_elasticity(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company);

        // Politique tenant par défaut : 50 % de pénalité sous 48 h.
        $this->postJson('/api/v1/travel/cancellation-policies', [
            'trip_id' => null,
            'class_id' => null,
            'hours_before_departure' => 48,
            'penalty_percent' => 50,
            'refundable' => true,
        ])->assertStatus(201);

        // Départ à J+2 (< 48 h selon la politique) → 50 % : 15 000 − 7 500.
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-policy',
        ])->assertStatus(201)
            ->assertJsonPath('data.amount_minor', 7500)
            ->assertJsonPath('data.penalty_minor', 7500);
    }

    public function test_refunding_all_passengers_marks_booking_refunded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company);

        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-all-a',
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerB'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-all-b',
        ])->assertStatus(201);

        $status = app(TenantManager::class)->withinTenant($company, function () use ($setup): string {
            return TravelBooking::query()->findOrFail($setup['booking']['id'])->status->value;
        });

        $this->assertSame(BookingStatus::REFUNDED->value, $status);

        // Sièges libérés.
        $freeSeats = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTripSeat::query()
                ->where('status', SeatStatus::FREE->value)
                ->count();
        });

        $this->assertGreaterThanOrEqual(2, $freeSeats);
    }

    public function test_cancellation_policy_crud_and_non_refundable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->confirmedBooking($company);

        // Non remboursable → pénalité 100 %.
        $this->postJson('/api/v1/travel/cancellation-policies', [
            'trip_id' => null,
            'class_id' => null,
            'hours_before_departure' => 24,
            'penalty_percent' => 100,
            'refundable' => false,
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/refund-passenger', [
            'passenger_id' => $setup['passengerA'],
            'reason' => 'Voyage annulé',
            'refund_key' => 'refund-nonref',
        ])->assertStatus(201)
            ->assertJsonPath('data.amount_minor', 0)
            ->assertJsonPath('data.penalty_minor', 15000);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCancellationPolicy::query()->count();
        }));
    }
}
