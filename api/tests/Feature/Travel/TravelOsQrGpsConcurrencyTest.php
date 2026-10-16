<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketQrCode;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-OS (#8538, #8539, #8540) — QR du billet, vérification de billet,
 * concurrence du check-in et dernière position connue des véhicules.
 */
class TravelOsQrGpsConcurrencyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function manager(Company $company): Employee
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

    /**
     * @return array{booking: TravelBooking, ticket: TravelTicket}
     */
    private function bookingWithTicket(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published']);

            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create(['trip_id' => $trip->id]);

            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::factory()->create([
                'booking_id' => $booking->id,
                'full_name' => 'Awa Ndiaye',
                'seat_number' => 12,
            ]);

            /** @var TravelTicket $ticket */
            $ticket = TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => TicketStatus::ISSUED->value,
            ]);

            return ['booking' => $booking->refresh(), 'ticket' => $ticket->refresh()];
        });
    }

    // ---------------------------------------------------------------------
    // #8538 — QR code du billet
    // ---------------------------------------------------------------------

    public function test_qr_payload_carries_no_personal_data(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $payload = app(TravelTicketQrCode::class)->payload($ticket);

        $this->assertStringStartsWith('GV1|', $payload);
        $this->assertStringContainsString((string) $booking->reference, $payload);
        // Aucune donnée personnelle dans la charge utile scannable.
        $this->assertStringNotContainsString('Awa', $payload);
        $this->assertStringNotContainsString('Ndiaye', $payload);
        $this->assertStringNotContainsString((string) $ticket->ticket_number, $payload);
    }

    public function test_qr_payload_round_trips_through_parse(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $service = app(TravelTicketQrCode::class);
        $parsed = $service->parse($service->payload($ticket));

        $this->assertNotNull($parsed);
        $this->assertSame((string) $booking->reference, $parsed['reference']);
        $this->assertSame($ticket->plainValidationCode(), $parsed['code']);
    }

    public function test_qr_parse_rejects_unknown_payload(): void
    {
        $service = app(TravelTicketQrCode::class);

        $this->assertNull($service->parse('nimporte-quoi'));
        $this->assertNull($service->parse('GV1|ref-seule'));
        $this->assertNull($service->parse('GV2|ref|code'));
    }

    public function test_qr_png_is_a_valid_png_image(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        ['ticket' => $ticket] = $this->bookingWithTicket($company);

        $png = app(TravelTicketQrCode::class)->png($ticket);

        // Signature PNG : le binaire est bien une image, pas du texte.
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        $this->assertGreaterThan(200, strlen($png));
    }

    public function test_ticket_pdf_view_data_embeds_qr_data_uri(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        ['ticket' => $ticket] = $this->bookingWithTicket($company);

        $data = app(TravelTicketPdfGenerator::class)->viewData($ticket);

        $this->assertArrayHasKey('qrCodeDataUri', $data);
        $this->assertStringStartsWith('data:image/png;base64,', $data['qrCodeDataUri']);
    }

    // ---------------------------------------------------------------------
    // #8538 — vérification d'authenticité (lecture seule)
    // ---------------------------------------------------------------------

    public function test_authorized_employee_can_verify_a_valid_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $response = $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.booking_reference', $booking->reference)
            ->assertJsonPath('data.seat_number', 12)
            ->assertJsonPath('data.passenger.full_name', 'Awa Ndiaye');
    }

    public function test_verification_does_not_consume_the_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertOk();

        // Vérifier n'est PAS consommer : le billet reste utilisable.
        $this->assertSame(TicketStatus::ISSUED, $ticket->refresh()->status);
        $this->assertNull($ticket->checked_in_at);
    }

    public function test_verification_reports_already_used_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $ticket->forceFill(['status' => TicketStatus::CHECKED_IN, 'checked_in_at' => now()])->save();

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertOk()->assertJsonPath('data.status', 'already_used');
    }

    public function test_verification_reports_void_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $ticket->forceFill(['status' => TicketStatus::VOID])->save();

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertOk()->assertJsonPath('data.status', 'void');
    }

    public function test_verification_reports_expired_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $ticket->forceFill(['valid_until' => now()->subDay()])->save();

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertOk()->assertJsonPath('data.status', 'expired');
    }

    public function test_verification_rejects_an_invalid_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['booking' => $booking] = $this->bookingWithTicket($company);

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => 'ZZZZ-ZZZZ-ZZZZ',
        ])->assertNotFound();
    }

    public function test_verification_is_cross_tenant_safe(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyA);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyB);
        $this->manager($companyB);

        // 404 (jamais 403) : ne pas révéler l'existence de la réservation.
        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertNotFound();
    }

    public function test_verification_requires_the_checkin_permission(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->simpleEmployee($company);

        ['booking' => $booking, 'ticket' => $ticket] = $this->bookingWithTicket($company);

        $this->postJson('/api/v1/travel/tickets/verify', [
            'reference' => $booking->reference,
            'code' => $ticket->plainValidationCode(),
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // #8539 — concurrence du check-in
    // ---------------------------------------------------------------------

    public function test_checkin_is_idempotent_and_keeps_the_first_operator(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        ['ticket' => $ticket] = $this->bookingWithTicket($company);

        $first = $this->manager($company);
        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")->assertOk();

        $ticket->refresh();
        $this->assertSame(TicketStatus::CHECKED_IN, $ticket->status);
        $this->assertSame($first->id, $ticket->checked_in_by_user_id);
        $firstCheckedInAt = $ticket->checked_in_at;
        $this->assertNotNull($firstCheckedInAt);

        // Second contrôle (autre opérateur) : idempotent, sans écrasement.
        $second = $this->manager($company);
        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")->assertOk();

        $ticket->refresh();
        $this->assertSame($first->id, $ticket->checked_in_by_user_id);
        $this->assertNotSame($second->id, $ticket->checked_in_by_user_id);
        $this->assertTrue($firstCheckedInAt->equalTo($ticket->checked_in_at));

        // Un seul événement outbox pour un seul billet.
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.ticket.checked_in.v1')
            ->count());
    }

    public function test_checkin_rejects_a_void_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        ['ticket' => $ticket] = $this->bookingWithTicket($company);
        $ticket->forceFill(['status' => TicketStatus::VOID])->save();

        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/check-in")->assertStatus(422);
        $this->assertSame(TicketStatus::VOID, $ticket->refresh()->status);
    }

    // ---------------------------------------------------------------------
    // #8540 — dernière position connue des véhicules
    // ---------------------------------------------------------------------

    public function test_vehicle_without_report_has_no_position(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        $vehicle = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelVehicle => TravelVehicle::factory()->create()
        );

        // Aucune position inventée : la clé existe et vaut null.
        $this->getJson("/api/v1/travel/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.position', null);
    }

    public function test_vehicle_position_can_be_recorded_with_gps_source(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        $vehicle = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelVehicle => TravelVehicle::factory()->create()
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicle->id}/position", [
            'latitude' => 14.6928,
            'longitude' => -17.4467,
            'source' => 'gps',
        ])
            ->assertOk()
            ->assertJsonPath('data.position.source', 'gps')
            ->assertJsonPath('data.position.is_measured', true)
            ->assertJsonPath('data.position.latitude', 14.6928);

        $this->assertNotNull($vehicle->refresh()->position_recorded_at);
    }

    public function test_estimated_position_is_flagged_as_not_measured(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        $vehicle = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelVehicle => TravelVehicle::factory()->create()
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicle->id}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => TravelVehiclePositionSource::ESTIMATED->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.position.source', 'estimated')
            // Une estimation n'est JAMAIS présentée comme une position GPS.
            ->assertJsonPath('data.position.is_measured', false);
    }

    public function test_vehicle_position_rejects_an_unknown_source(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->manager($company);

        $vehicle = app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelVehicle => TravelVehicle::factory()->create()
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicle->id}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'satellite-imaginaire',
        ])->assertStatus(422);
    }

    public function test_vehicle_position_is_cross_tenant_safe(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyA);

        $vehicle = app(TenantManager::class)->withinTenant(
            $companyA,
            fn (): TravelVehicle => TravelVehicle::factory()->create()
        );

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyB);
        $this->manager($companyB);

        $this->postJson("/api/v1/travel/vehicles/{$vehicle->id}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'gps',
        ])->assertNotFound();
    }
}
