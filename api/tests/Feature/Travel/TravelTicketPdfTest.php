<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-412/413 (#6064/#6065) — Génération PDF locale + URL signée.
 */
class TravelTicketPdfTest extends TestCase
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

    private function ticket(Company $company): TravelTicket
    {
        return app(TenantManager::class)->withinTenant($company, function (): TravelTicket {
            $booking = TravelBooking::factory()->create();
            $class = TravelClass::factory()->create();
            $passenger = $booking->passengers()->create([
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ]);
        });
    }

    public function test_pdf_generator_produces_pdf_binary(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $ticket = $this->ticket($company);

        $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);

        // Signature PDF %PDF + fin de fichier %%EOF (contenu compressé par
        // dompdf : le texte n'est pas lisible dans le binaire brut).
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    public function test_pdf_endpoint_returns_signed_url(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', $ticket->ticket_number)
            ->assertJsonPath('data.expires_in_minutes', 30)
            ->assertJsonPath('data.pdf_url', fn ($value) => is_string($value) && str_contains($value, 'travel/tickets'));
    }

    public function test_revoke_voids_ticket(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'void');
    }

    public function test_pdf_after_revoke_returns_410(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);
        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/revoke")->assertOk();

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertStatus(410);
    }

    public function test_ticket_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $ticketId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            $booking = TravelBooking::factory()->create();
            $class = TravelClass::factory()->create();
            $passenger = $booking->passengers()->create([
                'full_name' => 'Autre Tenant',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 15000,
            ]);

            return TravelTicket::factory()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
            ])->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/tickets/{$ticketId}/pdf")->assertStatus(404);
    }
}
