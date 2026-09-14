<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7394 — Le code de validation doit être DÉLIVRÉ au passager.
 *
 * Constat de recette : `issueValidationCode()` générait un code, n'en
 * persistait que le SHA-256, et le code en clair disparaissait à la fin de la
 * requête. Il était donc :
 *   - absent de la réponse d'émission,
 *   - absent du PDF (qui imprimait le NUMÉRO DE BILLET sous l'étiquette
 *     « Code de contrôle »),
 *   - jamais envoyé.
 *
 * Or `TravelPublicShopController::track` exige ce code et le compare par hash :
 * le suivi public ne pouvait QUE échouer en 404, et le portail « Espace
 * voyageur » était inutilisable pour son public cible.
 *
 * Le test existant (`TravelPublicShopApiTest`) ne l'a pas vu parce qu'il
 * CONTOURAIT le défaut : il capturait le code en mémoire au moment de l'émission
 * — ce qu'aucun passager ne peut faire. Ce fichier-ci part du principe inverse :
 * on émet, on RELIT le code depuis le billet (comme le ferait une réimpression
 * d'e-billet), et on vérifie que le suivi public fonctionne.
 */
class TravelTicketValidationCodeDeliveryTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);

        $company->setFeature('travelagency', true);
        $company->save();

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);
    }

    public function test_the_delivered_code_stays_retrievable_after_issuance(): void
    {
        $ticket = $this->issueTicketBelongingToAConfirmedBooking();

        // Avant #7394 : `plainValidationCode()` n'existait pas et le clair était
        // perdu dès la sortie de la requête.
        $delivered = $ticket->plainValidationCode();

        self::assertNotNull(
            $delivered,
            "Le code de validation doit rester délivrable après l'émission (sinon le passager ne l'a jamais).",
        );
        self::assertSame(
            16,
            strlen($delivered),
            'Le code délivré doit avoir le format émis (16 caractères majuscules).',
        );
        self::assertTrue(
            $ticket->validationCodeMatches($delivered),
            'Le code relu doit vérifier contre le hash stocké.',
        );
    }

    public function test_the_ciphertext_is_never_serialized(): void
    {
        $ticket = $this->issueTicketBelongingToAConfirmedBooking();

        $payload = $ticket->toArray();

        self::assertArrayNotHasKey('validation_code_ciphertext', $payload);
        self::assertArrayNotHasKey('validation_code', $payload);
    }

    public function test_the_e_ticket_shows_the_validation_code_the_portal_asks_for(): void
    {
        $ticket = $this->issueTicketBelongingToAConfirmedBooking();

        $ticket->load('passenger', 'booking.trip.route');

        $html = View::make(TravelTicketPdfGenerator::TEMPLATE, [
            'ticket' => $ticket,
            'passenger' => $ticket->passenger,
            'booking' => $ticket->booking,
            'trip' => $ticket->booking?->trip,
            'route' => $ticket->booking?->trip?->route,
            'validationCode' => $ticket->plainValidationCode(),
        ])->render();

        self::assertStringContainsString(
            (string) $ticket->plainValidationCode(),
            $html,
            "L'e-billet doit imprimer le code de validation (le portail le présente comme « sur votre e-billet »).",
        );
        self::assertStringNotContainsString(
            'Non disponible pour ce billet',
            $html,
            "Un billet émis après #7394 doit toujours porter un code.",
        );
    }

    public function test_passenger_can_track_booking_with_the_code_read_from_the_ticket(): void
    {
        $ticket = $this->issueTicketBelongingToAConfirmedBooking();

        $plainToken = 'tshop_test_'.random_int(1000, 9999);
        $this->tenants->withinTenant($this->company, function () use ($plainToken): void {
            TravelPublicShopToken::query()->create([
                'company_id' => $this->company->id,
                'token_hash' => TravelPublicShopToken::hash($plainToken),
                'name' => 'Test',
                'active' => true,
            ]);
        });

        $reference = $ticket->booking->reference;
        $delivered = (string) $ticket->plainValidationCode();

        // Le passager lit son code sur l'e-billet, puis suit sa réservation.
        $this->getJson(
            "/api/v1/public/travel/shop/bookings/{$reference}?code={$delivered}",
            ['X-Travel-Shop-Token' => $plainToken],
        )
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);

        // Contrôle négatif : le code protège réellement l'accès.
        $this->getJson(
            "/api/v1/public/travel/shop/bookings/{$reference}?code=CODEINCORRECT1234",
            ['X-Travel-Shop-Token' => $plainToken],
        )->assertStatus(404);

        // Et le numéro de billet, qui était imprimé à tort comme « code de
        // contrôle » avant #7394, ne doit PAS ouvrir la réservation.
        $this->getJson(
            "/api/v1/public/travel/shop/bookings/{$reference}?code=".urlencode((string) $ticket->ticket_number),
            ['X-Travel-Shop-Token' => $plainToken],
        )->assertStatus(404);
    }

    public function test_legacy_tickets_without_ciphertext_degrade_gracefully(): void
    {
        // Billet émis AVANT le correctif : hash seul, pas de copie chiffrée.
        $ticket = $this->tenants->withinTenant($this->company, function (): TravelTicket {
            $class = TravelClass::factory()->create(['company_id' => $this->company->id]);
            $booking = TravelBooking::factory()->create(['company_id' => $this->company->id]);
            $passenger = $booking->passengers()->create([
                'company_id' => $this->company->id,
                'full_name' => 'Ancien Billet',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 10000,
            ]);

            return TravelTicket::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        });

        self::assertNull(
            $ticket->plainValidationCode(),
            'Un billet legacy ne doit pas inventer de code (le template affiche une mention explicite).',
        );
    }

    private function issueTicketBelongingToAConfirmedBooking(): TravelTicket
    {
        return $this->tenants->withinTenant($this->company, function (): TravelTicket {
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
                // NOT NULL en base : le tarif unitaire est calculé côté serveur
                // en production (CreateBookingAction), on le pose ici.
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

            // Chemin d'émission réel.
            $ticket->issueValidationCode();
            $ticket->save();

            return $ticket->refresh();
        });
    }
}
