<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7395 — « Espace voyageur » : le portail du CLIENT FINAL.
 *
 * Avant ce correctif, la page appelait les endpoints STAFF
 * (`/api/v1/travel/shop/...`, groupe `auth:sanctum` + `tenant`) : un passager
 * — qui n'a ni compte Leopardo ni jeton boutique — recevait
 * `401 UNAUTHENTICATED`. Le portail était donc inaccessible à son public
 * cible, et le champ « code de validation » n'était même pas vérifié par
 * `TravelShopController::track`.
 *
 * Cette surface publique s'authentifie par ce que le passager possède
 * réellement : la référence de réservation + le code de validation de son
 * e-billet (#7394). Les tests vérifient autant le chemin heureux que les
 * propriétés de sécurité (pas d'oracle, pas de fuite cross-tenant, pas de PII).
 */
class TravelPassengerPortalTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    private TravelBooking $booking;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);

        [$this->booking, $this->code] = $this->tenants->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'published',
                'total_seats' => 10,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create(['company_id' => $this->company->id]);
            TravelTripPrice::factory()->create([
                'company_id' => $this->company->id,
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            $booking = TravelBooking::factory()->create([
                'company_id' => $this->company->id,
                'trip_id' => $trip->id,
                'status' => 'confirmed',
            ]);

            $passenger = $booking->passengers()->create([
                'company_id' => $this->company->id,
                'full_name' => 'Fatou Ndiaye',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'seat_number' => 3,
                'unit_price_minor' => 10000,
            ]);

            $ticket = TravelTicket::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
                'valid_from' => now(),
                'valid_until' => now()->addDay(),
            ]);

            $code = $ticket->issueValidationCode();
            $ticket->save();

            return [$booking->refresh(), $code];
        });
    }

    public function test_passenger_tracks_booking_without_any_authentication(): void
    {
        // Aucun en-tête d'authentification, aucun jeton boutique : c'est le
        // cas réel du passager. Avant #7395 : 401 UNAUTHENTICATED.
        $this->getJson("/api/v1/public/travel/passenger/bookings/{$this->booking->reference}?code={$this->code}")
            ->assertOk()
            ->assertJsonPath('data.reference', $this->booking->reference)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_tracking_never_exposes_passenger_pii(): void
    {
        $response = $this->getJson(
            "/api/v1/public/travel/passenger/bookings/{$this->booking->reference}?code={$this->code}"
        )->assertOk();

        $body = $response->getContent();

        self::assertIsString($body);
        self::assertStringNotContainsString('Fatou', $body, 'Le nom du passager ne doit pas être exposé.');
        self::assertStringNotContainsString('Ndiaye', $body, 'Le nom du passager ne doit pas être exposé.');
        self::assertStringNotContainsString('national_id', $body);
    }

    public function test_wrong_code_is_rejected_exactly_like_an_unknown_reference(): void
    {
        // Pas d'oracle : mauvais code et référence inconnue doivent produire la
        // MÊME réponse, sinon on peut énumérer les références valides.
        $wrongCode = $this->getJson(
            "/api/v1/public/travel/passenger/bookings/{$this->booking->reference}?code=CODEINCORRECT1234"
        );
        $unknownRef = $this->getJson(
            '/api/v1/public/travel/passenger/bookings/GV-INEXISTANT00?code=CODEINCORRECT1234'
        );

        $wrongCode->assertStatus(404);
        $unknownRef->assertStatus(404);
        self::assertSame($wrongCode->getStatusCode(), $unknownRef->getStatusCode());
    }

    public function test_missing_code_is_rejected(): void
    {
        $this->getJson("/api/v1/public/travel/passenger/bookings/{$this->booking->reference}")
            ->assertStatus(422);
    }

    public function test_another_tenant_booking_is_not_reachable(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        [$otherBooking, $otherCode] = $this->tenants->withinTenant($other, function () use ($other): array {
            $trip = TravelTrip::factory()->create(['company_id' => $other->id, 'status' => 'published', 'total_seats' => 5]);
            $class = TravelClass::factory()->create(['company_id' => $other->id]);
            TravelTripPrice::factory()->create([
                'company_id' => $other->id,
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 5000,
            ]);
            $booking = TravelBooking::factory()->create([
                'company_id' => $other->id,
                'trip_id' => $trip->id,
                'status' => 'confirmed',
            ]);
            $passenger = $booking->passengers()->create([
                'company_id' => $other->id,
                'full_name' => 'Autre Client',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 5000,
            ]);
            $ticket = TravelTicket::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
            $code = $ticket->issueValidationCode();
            $ticket->save();

            return [$booking->refresh(), $code];
        });

        // Le code du client d'un AUTRE tenant ne doit pas ouvrir la réservation
        // de ce tenant-ci, et réciproquement.
        $this->getJson("/api/v1/public/travel/passenger/bookings/{$otherBooking->reference}?code={$otherCode}")
            ->assertOk()
            ->assertJsonPath('data.reference', $otherBooking->reference);

        $this->getJson("/api/v1/public/travel/passenger/bookings/{$this->booking->reference}?code={$otherCode}")
            ->assertStatus(404);
    }

    public function test_passenger_downloads_own_ticket_but_not_another_bookings_ticket(): void
    {
        $ticketNumber = $this->tenants->withinTenant(
            $this->company,
            fn (): string => (string) TravelTicket::query()
                ->where('booking_id', $this->booking->id)
                ->value('ticket_number')
        );

        $this->tenants->withinTenant($this->company, function () use (&$otherTicketNumber): void {
            $booking = TravelBooking::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'confirmed',
            ]);
            $class = TravelClass::factory()->create(['company_id' => $this->company->id]);
            $passenger = $booking->passengers()->create([
                'company_id' => $this->company->id,
                'full_name' => 'Autre Passager',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 10000,
            ]);
            $ticket = TravelTicket::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
            $ticket->issueValidationCode();
            $ticket->save();
            $otherTicketNumber = $ticket->ticket_number;
        });

        // Son propre billet : 200 (le numéro contient un `#` — il passe en
        // paramètre de requête, un `#` en segment de chemin étant interprété
        // comme un fragment par les proxies).
        $this->getJson(
            "/api/v1/public/travel/passenger/bookings/{$this->booking->reference}/ticket"
            .'?number='.urlencode($ticketNumber).'&code='.$this->code
        )->assertOk()->assertJsonPath('data.ticket_number', $ticketNumber);

        // Le billet d'une AUTRE réservation du même tenant, avec le bon code
        // du passager : refusé (le billet doit appartenir à CETTE réservation).
        $this->getJson(
            "/api/v1/public/travel/passenger/bookings/{$this->booking->reference}/ticket"
            .'?number='.urlencode((string) $otherTicketNumber).'&code='.$this->code
        )->assertStatus(403);

        // Mauvais code sur son propre billet : refusé.
        $this->getJson(
            "/api/v1/public/travel/passenger/bookings/{$this->booking->reference}/ticket"
            .'?number='.urlencode($ticketNumber).'&code=CODEINCORRECT1234'
        )->assertStatus(403);
    }
}
