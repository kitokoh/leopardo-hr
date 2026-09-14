<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
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
 * #7394 — Le code de validation du billet est réellement délivré au passager.
 *
 * Avant : `IssueTicketsAction` générait un code, n'en persistait que le hash
 * et ne le renvoyait à personne ; le PDF imprimait le NUMÉRO DE BILLET sous
 * l'étiquette « Code de contrôle », donc le passager saisissait un code que
 * `TravelPublicShopController::track` refusait (404).
 *
 * Après : le code est délivré UNE SEULE FOIS par la réponse d'émission, il est
 * imprimé sur l'e-billet, et c'est lui — et lui seul — qui ouvre le suivi
 * public (secret partagé du billet). Seul le hash vit en base.
 */
class TravelTicketValidationCodeDeliveryTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Forme canonique imprimée sur le billet : 3 groupes de 4, alphabet sans ambiguïté. */
    private const CODE_PATTERN = '/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/';

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
     * @return array{trip: TravelTrip, class: int, shop_token: string}
     */
    private function tripFixture(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            /** @var TravelTrip $trip */
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 20,
                'departure_date' => now()->addDays(7)->toDateString(),
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            $plainToken = 'tshop_test_'.random_int(10000, 99999);
            TravelPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => TravelPublicShopToken::hash($plainToken),
                'name' => 'Test',
                'active' => true,
            ]);

            return ['trip' => $trip, 'class' => $class->id, 'shop_token' => $plainToken];
        });
    }

    /**
     * Parcours d'émission complet : réservation → confirmation → billets.
     *
     * @return array{booking: array<string, mixed>, tickets: list<array<string, mixed>>, shop_token: string}
     */
    private function issuedTickets(Company $company, string $idempotencyKey): array
    {
        $fixture = $this->tripFixture($company);

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $fixture['trip']->id,
            'booking_source' => 'office',
            'idempotency_key' => $idempotencyKey,
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $fixture['class']],
            ],
        ])->assertStatus(201)->json('data');

        $this->postJson("/api/v1/travel/bookings/{$booking['id']}/confirm")->assertOk();

        /** @var list<array<string, mixed>> $tickets */
        $tickets = $this->postJson("/api/v1/travel/bookings/{$booking['id']}/issue-ticket")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        return ['booking' => $booking, 'tickets' => $tickets, 'shop_token' => $fixture['shop_token']];
    }

    public function test_issuing_a_ticket_delivers_the_validation_code_exactly_once(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $issued = $this->issuedTickets($company, 'issue-code-once');
        $ticket = $issued['tickets'][0];

        // 1. Le code EN CLAIR est délivré par la réponse d'émission.
        $this->assertArrayHasKey('validation_code', $ticket);
        $this->assertIsString($ticket['validation_code']);
        $this->assertMatchesRegularExpression(self::CODE_PATTERN, $ticket['validation_code']);

        $code = $ticket['validation_code'];

        // 2. En base : le hash seul (jamais le clair), et ce hash correspond au
        //    code délivré (sinon le passager retomberait sur le bug #7394).
        $stored = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelTicket => TravelTicket::query()->findOrFail($ticket['id']),
        );

        $this->assertNotSame($code, $stored->validation_code);
        $this->assertSame(TravelTicket::hashValidationCode($code), $stored->validation_code);
        $this->assertTrue($stored->validationCodeMatches($code));
        $this->assertFalse($stored->validationCodeMatches('ZZZZ-ZZZZ-ZZZZ'));

        // La saisie « humaine » (minuscules, sans tirets) est tolérée.
        $this->assertTrue($stored->validationCodeMatches(strtolower(str_replace('-', '', $code))));

        // 3. Un rejeu d'émission ne réémet rien → aucun code n'est redélivré.
        $this->postJson("/api/v1/travel/bookings/{$issued['booking']['id']}/issue-ticket")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 4. Une route de LECTURE ne réexpose jamais le code.
        $checkIn = $this->postJson("/api/v1/travel/tickets/{$ticket['id']}/check-in")->assertOk();
        $this->assertArrayNotHasKey('validation_code', $checkIn->json('data'));
    }

    /**
     * Test de non-régression exigé par #7394 : émettre un billet, récupérer le
     * code délivré, puis suivre la réservation avec ce code → 200 ; un code
     * erroné → 404.
     */
    public function test_public_tracking_accepts_the_delivered_code_and_rejects_a_wrong_one(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $issued = $this->issuedTickets($company, 'issue-code-public-track');
        $reference = $issued['booking']['reference'];
        $code = $issued['tickets'][0]['validation_code'];

        $headers = ['X-Travel-Shop-Token' => $issued['shop_token']];

        // Sans code : la preuve de possession est exigée (422).
        $this->getJson("/api/v1/public/travel/shop/bookings/{$reference}", $headers)
            ->assertStatus(422);

        // Code délivré : le suivi public fonctionne (200) — le bug #7394 est mort.
        $this->getJson("/api/v1/public/travel/shop/bookings/{$reference}?code=".urlencode($code), $headers)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);

        // Code erroné (ex. le numéro de billet, ce que le PDF imprimait avant) : 404.
        $this->getJson("/api/v1/public/travel/shop/bookings/{$reference}?code=".urlencode($issued['tickets'][0]['ticket_number']), $headers)
            ->assertStatus(404);

        $this->getJson("/api/v1/public/travel/shop/bookings/{$reference}?code=ZZZZ-ZZZZ-ZZZZ", $headers)
            ->assertStatus(404);
    }

    public function test_ticket_pdf_prints_the_real_validation_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $issued = $this->issuedTickets($company, 'issue-code-pdf');
        $ticket = $issued['tickets'][0];

        $stored = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelTicket => TravelTicket::query()->findOrFail($ticket['id']),
        );

        // Contenu imprimé : le VRAI code, plus le numéro de billet à sa place.
        $html = View::make(
            TravelTicketPdfGenerator::TEMPLATE,
            app(TravelTicketPdfGenerator::class)->viewData($stored),
        )->render();

        $this->assertStringContainsString('Code de contrôle : '.$ticket['validation_code'], $html);
        $this->assertStringNotContainsString('Code de contrôle : '.$ticket['ticket_number'], $html);
        $this->assertStringContainsString($ticket['ticket_number'], $html);

        // Le binaire reste un PDF valide.
        $pdf = app(TravelTicketPdfGenerator::class)->generate($stored);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }
}
