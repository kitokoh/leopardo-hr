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
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Portail client voyageur — TRAVEL-702 (#6089).
 *
 * Couvre : suivi par référence, annulation en ligne (preuve par code de
 * billet, motif obligatoire, départ futur, statut annulable), idempotence,
 * code invalide 422, cross-tenant 404, libération des sièges.
 */
class TravelShopCancelTest extends TestCase
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
     * @return array{trip: TravelTrip, class: TravelClass, booking: TravelBooking, ticket: TravelTicket, code: string}
     */
    private function confirmedBookingWithTicket(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
                'departure_date' => now()->addDays(7)->toDateString(),
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            /** @var TravelBooking $booking */
            $booking = TravelBooking::query()->create([
                'company_id' => $company->id,
                'reference' => 'GV-TRAVEL-'.uniqid('', false),
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'booking_source' => 'online',
                'total_amount_minor' => 15000,
                'currency' => 'XAF',
                'passenger_count' => 1,
                'idempotency_key' => 'ik-'.uniqid('', false),
            ]);

            // Un passager + un billet avec code de validation (hash sha256 stocké).
            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            $code = 'ABCD1234';
            /** @var TravelTicket $ticket */
            $ticket = TravelTicket::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'ticket_number' => 'TK-'.uniqid('', false),
                'validation_code' => hash('sha256', $code),
                'status' => 'issued',
            ]);

            return ['trip' => $trip, 'class' => $class, 'booking' => $booking, 'ticket' => $ticket, 'code' => $code];
        });
    }

    public function test_customer_can_track_booking_by_reference(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->confirmedBookingWithTicket($company);

        $this->getJson("/api/v1/travel/shop/bookings/{$booking->reference}")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value)
            ->assertJsonCount(1, 'data.ticket_numbers');
    }

    public function test_customer_cancels_with_valid_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'trip' => $trip, 'code' => $code] = $this->confirmedBookingWithTicket($company);

        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => $code,
            'reason' => 'Changement de programme',
        ])->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // Les sièges sont libérés.
        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function () use ($trip): int {
            return TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', SeatStatus::RESERVED)
                ->count();
        }));

        // Idempotent : re-annuler renvoie la réservation annulée (200).
        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => $code,
            'reason' => 'Encore une fois',
        ])->assertOk()->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_cancel_rejects_wrong_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking] = $this->confirmedBookingWithTicket($company);

        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => 'WRONG000',
            'reason' => 'Motif de test',
        ])->assertStatus(422)->assertJsonPath('error', 'TRAVEL_BOOKING_CODE_INVALID');
    }

    public function test_cancel_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'code' => $code] = $this->confirmedBookingWithTicket($company);

        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => $code,
        ])->assertStatus(422)->assertJsonPath('error', 'VALIDATION_ERROR');
    }

    public function test_cancel_rejects_past_departure(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'code' => $code] = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 10,
                'departure_date' => now()->subDay()->toDateString(),
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $code = 'PASCODE01';
            /** @var TravelBooking $booking */
            $booking = TravelBooking::query()->create([
                'company_id' => $company->id,
                'reference' => 'GV-PAST-'.uniqid('', false),
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'booking_source' => 'online',
                'total_amount_minor' => 1000,
                'currency' => 'XAF',
                'passenger_count' => 1,
                'idempotency_key' => 'ik-past-'.uniqid('', false),
            ]);
            /** @var TravelClass $class */
            $class = TravelClass::factory()->create();

            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);
            TravelTicket::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'ticket_number' => 'TK-PAST-1',
                'validation_code' => hash('sha256', $code),
                'status' => 'issued',
            ]);

            return ['booking' => $booking, 'code' => $code];
        });

        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => $code,
            'reason' => 'Départ déjà passé',
        ])->assertStatus(422)->assertJsonPath('error', 'TRAVEL_BOOKING_DEPARTURE_PAST');
    }

    public function test_cancel_cross_tenant_is_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'code' => $code] = $this->confirmedBookingWithTicket($company);

        // Un autre tenant ne peut ni suivre ni annuler la réservation.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->activateTravel($other);
        /** @var Employee $otherPrincipal */
        $otherPrincipal = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($otherPrincipal);

        $this->getJson("/api/v1/travel/shop/bookings/{$booking->reference}")->assertStatus(404);
        $this->postJson("/api/v1/travel/shop/bookings/{$booking->reference}/cancel", [
            'code' => $code,
            'reason' => 'Cross tenant',
        ])->assertStatus(404);
    }
}
