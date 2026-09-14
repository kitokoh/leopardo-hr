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
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7395 — Espace voyageur : le portail passager consomme la surface PUBLIQUE.
 *
 * Constat d'origine : `front/web/src/app/(dashboard)/travel/portal/page.tsx`
 * appelait les endpoints STAFF (`/travel/shop/bookings/{ref}`,
 * `/travel/tickets/{id}/pdf`, `POST /travel/shop/bookings/{ref}/cancel`) →
 * 401 pour un passager (aucun compte), et le paramètre `code` était ignoré par
 * `TravelShopController::track` (faux contrôle d'accès).
 *
 * Ici : le passager n'a QUE sa référence + le code de validation du billet —
 * pas de compte, pas de jeton boutique du tenant. Le suivi, le téléchargement
 * du PDF et l'annulation en ligne passent par `/public/travel/...`, où le code
 * est réellement vérifié avant toute donnée.
 */
class TravelPublicShopPassengerPortalTest extends TestCase
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
     * Réservation confirmée, 1 passager, 1 billet ÉMIS (code canonique) et un
     * siège réservé rattaché — de quoi prouver suivi, PDF et annulation.
     *
     * @return array{trip: TravelTrip, booking: TravelBooking, ticket: TravelTicket, code: string}
     */
    private function bookingWithIssuedTicket(Company $company, string $departure = '+7 days'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $departure): array {
            /** @var TravelTrip $trip */
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
                'departure_date' => Carbon::parse($departure)->toDateString(),
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
                'reference' => 'GV-'.strtoupper(uniqid('', false)),
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'booking_source' => 'online',
                'total_amount_minor' => 15000,
                'currency' => 'XAF',
                'passenger_count' => 1,
                'idempotency_key' => 'ik-'.uniqid('', false),
            ]);

            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            $seat = TravelTripSeat::query()->where('trip_id', $trip->id)->firstOrFail();
            $seat->forceFill([
                'status' => SeatStatus::RESERVED,
                'booking_id' => $booking->id,
            ])->save();

            /** @var TravelTicket $ticket */
            $ticket = TravelTicket::query()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
            ]);

            $code = $ticket->issueValidationCode();
            $ticket->save();

            return ['trip' => $trip, 'booking' => $booking, 'ticket' => $ticket, 'code' => $code];
        });
    }

    private function shopToken(Company $company): string
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): string {
            $plain = 'tshop_test_'.random_int(100000, 999999);
            TravelPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => TravelPublicShopToken::hash($plain),
                'name' => 'Test',
                'active' => true,
            ]);

            return $plain;
        });
    }

    public function test_passenger_tracks_booking_without_shop_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company);

        // Sans jeton boutique : la référence + le code suffisent (le passager
        // n'a jamais eu le jeton tenant).
        $this->getJson("/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}?code=".urlencode($fx['code']))
            ->assertOk()
            ->assertJsonPath('data.reference', $fx['booking']->reference)
            ->assertJsonPath('data.tickets.0.id', $fx['ticket']->id)
            ->assertJsonPath('data.tickets.0.ticket_number', $fx['ticket']->ticket_number);

        // Sans code : la preuve de possession est exigée.
        $this->getJson("/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}")
            ->assertStatus(422);

        // Code erroné : 404 (aucune donnée servie).
        $this->getJson("/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}?code=ZZZZ-ZZZZ-ZZZZ")
            ->assertStatus(404);

        // Référence inconnue : 404, jamais 401 (le tenant est résolu par la
        // ressource, l'absence de ressource n'est pas un défaut de jeton).
        $this->getJson('/api/v1/public/travel/shop/bookings/GV-INCONNUE?code=ZZZZ-ZZZZ-ZZZZ')
            ->assertStatus(404);
    }

    public function test_passenger_downloads_ticket_pdf_without_shop_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company);

        $this->getJson("/api/v1/public/travel/tickets/{$fx['ticket']->id}/pdf?code=".urlencode($fx['code']))
            ->assertOk()
            ->assertJsonPath('data.ticket_number', $fx['ticket']->ticket_number)
            ->assertJsonPath('data.pdf_url', fn ($value) => is_string($value) && str_contains($value, 'travel/tickets'));

        // Code erroné → 403 (le billet existe, la preuve est fausse).
        $this->getJson("/api/v1/public/travel/tickets/{$fx['ticket']->id}/pdf?code=ZZZZ-ZZZZ-ZZZZ")
            ->assertStatus(403);

        // Billet inconnu → 404.
        $this->getJson('/api/v1/public/travel/tickets/99999999/pdf?code=ZZZZ-ZZZZ-ZZZZ')
            ->assertStatus(404);
    }

    public function test_passenger_cancels_booking_without_shop_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company);
        $url = "/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}/cancel";

        // Code erroné → refus explicite (aucune annulation).
        $this->postJson($url, ['code' => 'ZZZZ-ZZZZ-ZZZZ', 'reason' => 'Changement de programme'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'TRAVEL_BOOKING_CODE_INVALID');

        // Motif obligatoire (audit).
        $this->postJson($url, ['code' => $fx['code']])
            ->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');

        // Cas nominal : le passager annule en ligne avec son code.
        $this->postJson($url, ['code' => $fx['code'], 'reason' => 'Changement de programme'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // Les sièges de la réservation sont libérés.
        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function () use ($fx): int {
            return TravelTripSeat::query()
                ->where('trip_id', $fx['trip']->id)
                ->where('status', SeatStatus::RESERVED->value)
                ->count();
        }));

        // Idempotent : rejouer ne casse rien.
        $this->postJson($url, ['code' => $fx['code'], 'reason' => 'Encore une fois'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);
    }

    public function test_passenger_cancel_rejects_past_departure(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company, '-1 day');

        $this->postJson("/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}/cancel", [
            'code' => $fx['code'],
            'reason' => 'Départ déjà passé',
        ])->assertStatus(422)->assertJsonPath('error', 'TRAVEL_BOOKING_DEPARTURE_PAST');
    }

    public function test_unbounded_public_routes_still_require_the_shop_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company);
        $token = $this->shopToken($company);

        // Recherche et réservation : le jeton tenant reste obligatoire.
        $this->getJson('/api/v1/public/travel/shop/trips')->assertStatus(401);
        $this->postJson('/api/v1/public/travel/shop/bookings', [])->assertStatus(401);

        // …et fonctionnent avec le jeton (aucune régression du tunnel public).
        $this->getJson('/api/v1/public/travel/shop/trips', ['X-Travel-Shop-Token' => $token])->assertOk();

        // Un AUTRE tenant ne peut pas atteindre la réservation : le jeton
        // résout son propre tenant, la référence y est inconnue → 404.
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->activateTravel($other);
        $otherToken = $this->shopToken($other);

        $this->getJson(
            "/api/v1/public/travel/shop/bookings/{$fx['booking']->reference}?code=".urlencode($fx['code']),
            ['X-Travel-Shop-Token' => $otherToken],
        )->assertStatus(404);
    }

    public function test_staff_shop_track_no_longer_accepts_a_code_parameter(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->bookingWithIssuedTicket($company);

        // Suivi staff : autorisé (employé authentifié du tenant)…
        $this->getJson("/api/v1/travel/shop/bookings/{$fx['booking']->reference}")
            ->assertOk()
            ->assertJsonPath('data.reference', $fx['booking']->reference)
            ->assertJsonMissingPath('data.validation_code');

        // …mais le `code` n'y est plus un « contrôle » : il est refusé au lieu
        // d'être ignoré en silence (#7395, faux contrôle d'accès).
        $this->getJson("/api/v1/travel/shop/bookings/{$fx['booking']->reference}?code=".urlencode($fx['code']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'TRAVEL_SHOP_CODE_NOT_SUPPORTED');
    }

    public function test_staff_shop_track_is_not_a_passenger_surface(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $fx = $this->bookingWithIssuedTicket($company);

        // Le passager qui tenterait l'endpoint staff (celui que la page
        // utilisait avant #7395) reçoit 401 : c'est la surface employé.
        $this->getJson("/api/v1/travel/shop/bookings/{$fx['booking']->reference}?code=".urlencode($fx['code']))
            ->assertStatus(401)
            ->assertJsonPath('error', 'UNAUTHENTICATED');
    }
}
