<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8546 (TRAVEL-OS) — recherche MANUELLE par référence, en secours de la
 * caméra (appareil sans capteur, QR illisible, caméra en panne).
 *
 * Ce que ces tests prouvent :
 *  - la recherche est en LECTURE SEULE : elle ne consomme jamais le billet
 *    (le statut reste `issued` après appel) ;
 *  - elle ne divulgue AUCUN code de contrôle (il n'est délivré qu'à
 *    l'émission) ;
 *  - l'identité du passager n'est renvoyée qu'à un opérateur habilité au
 *    contrôle (`travel.checkin`) ;
 *  - l'isolation par entreprise est respectée (404 cross-tenant) ;
 *  - un opérateur non habilité reçoit 403.
 */
class TravelTicketLookupTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

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

    private function simpleEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        return $company;
    }

    /**
     * @return array{booking: TravelBooking, ticket: TravelTicket}
     */
    private function bookingWithTicket(Company $company, string $reference): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($reference): array {
            $booking = TravelBooking::factory()->create(['reference' => $reference]);
            $class = TravelClass::factory()->create();
            $passenger = $booking->passengers()->create([
                'full_name' => 'Awa Ndiaye',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            $ticket = TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ]);

            return ['booking' => $booking, 'ticket' => $ticket];
        });
    }

    public function test_lookup_returns_booking_and_tickets(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->bookingWithTicket($company, 'GV-LOOKUP-1');

        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-1'])
            ->assertOk()
            ->assertJsonPath('data.booking_reference', 'GV-LOOKUP-1')
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.status', 'valid');
    }

    public function test_lookup_never_consumes_the_ticket(): void
    {
        $company = $this->company();
        $this->principal($company);
        $result = $this->bookingWithTicket($company, 'GV-LOOKUP-2');

        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-2'])
            ->assertOk();

        // LECTURE SEULE : le billet reste utilisable après une recherche.
        $this->assertSame(
            TicketStatus::ISSUED,
            $result['ticket']->fresh()->status
        );
        $this->assertNull($result['ticket']->fresh()->checked_in_at);
    }

    public function test_lookup_never_exposes_the_validation_code(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->bookingWithTicket($company, 'GV-LOOKUP-3');

        $response = $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-3'])
            ->assertOk();

        // Le code de contrôle n'est délivré qu'à l'émission : il ne doit
        // apparaître NULLE PART dans la réponse de recherche.
        $payload = json_encode($response->json());

        $this->assertStringNotContainsString('validation_code', (string) $payload);
        $this->assertStringNotContainsString('plainValidationCode', (string) $payload);
    }

    public function test_lookup_hides_passenger_identity_from_unprivileged_operator(): void
    {
        $company = $this->company();
        $this->bookingWithTicket($company, 'GV-LOOKUP-4');

        // Un employé simple n'a pas `travel.checkin` : il peut chercher, mais
        // l'identité du passager ne lui est pas divulguée.
        $this->simpleEmployee($company);

        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-4'])
            ->assertStatus(403);
    }

    public function test_lookup_shows_passenger_identity_to_privileged_operator(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->bookingWithTicket($company, 'GV-LOOKUP-5');

        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-5'])
            ->assertOk()
            ->assertJsonPath('data.tickets.0.passenger.full_name', 'Awa Ndiaye');
    }

    public function test_unknown_reference_returns_404(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-DOES-NOT-EXIST'])
            ->assertStatus(404);
    }

    public function test_reference_of_another_tenant_returns_404(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyB);
        $this->bookingWithTicket($companyB, 'GV-FOREIGN-REF');

        $this->principal($companyA);

        // 404 (jamais 403) : ne rien divulguer sur l'existence de la
        // réservation dans un autre tenant.
        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-FOREIGN-REF'])
            ->assertStatus(404);
    }

    public function test_reference_is_required(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/travel/tickets/lookup', [])
            ->assertStatus(422);
    }

    public function test_lookup_reports_void_ticket_status(): void
    {
        $company = $this->company();
        $this->principal($company);
        $result = $this->bookingWithTicket($company, 'GV-LOOKUP-6');

        app(TenantManager::class)->withinTenant($company, function () use ($result): void {
            $result['ticket']->forceFill(['status' => TicketStatus::VOID])->save();
        });

        // Le statut opérationnel est bien remonté : l'agent sait que le billet
        // est annulé avant même de saisir un code.
        $this->postJson('/api/v1/travel/tickets/lookup', ['reference' => 'GV-LOOKUP-6'])
            ->assertOk()
            ->assertJsonPath('data.tickets.0.status', 'void');
    }
}
