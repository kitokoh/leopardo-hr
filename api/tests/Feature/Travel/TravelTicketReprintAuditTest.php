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
use App\Modules\TravelAgency\Domain\Models\TravelTicketPrintEvent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8544 (TRAVEL-OS) — réimpression d'un billet AVEC trace d'audit.
 *
 * Couvre les critères d'acceptation : la première mise à disposition n'est pas
 * une réimpression ; les suivantes sont comptées ; la trace n'expose pas un
 * billet d'un autre tenant ; réimprimer ne change JAMAIS le statut ; un billet
 * révoqué reste refusé.
 */
class TravelTicketReprintAuditTest extends TestCase
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

    /**
     * @return list<TravelTicketPrintEvent>
     */
    private function events(Company $company, TravelTicket $ticket): array
    {
        return app(TenantManager::class)->withinTenant($company, fn (): array => TravelTicketPrintEvent::query()
            ->where('ticket_id', $ticket->id)
            ->orderBy('id')
            ->get()
            ->all());
    }

    public function test_first_availability_is_not_a_reprint(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")
            ->assertOk()
            ->assertJsonPath('data.reprint_number', 0)
            ->assertJsonPath('data.is_reprint', false);

        $events = $this->events($company, $ticket);

        $this->assertCount(1, $events);
        $this->assertSame(0, $events[0]->reprint_number);
        $this->assertFalse($events[0]->is_reprint);
        $this->assertNotNull($events[0]->employee_id);
    }

    public function test_second_and_third_availability_are_counted_as_reprints(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);
        $url = "/api/v1/travel/tickets/{$ticket->id}/pdf";

        $this->getJson($url)->assertOk()->assertJsonPath('data.reprint_number', 0);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.reprint_number', 1)
            ->assertJsonPath('data.is_reprint', true);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.reprint_number', 2)
            ->assertJsonPath('data.is_reprint', true);

        $events = $this->events($company, $ticket);

        $this->assertCount(3, $events);
        $this->assertSame([0, 1, 2], array_map(
            static fn (TravelTicketPrintEvent $event): int => $event->reprint_number,
            $events,
        ));
    }

    public function test_reprint_never_changes_ticket_status(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertOk();
        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertOk();

        $fresh = app(TenantManager::class)->withinTenant(
            $company,
            fn (): ?TravelTicket => TravelTicket::query()->find($ticket->id),
        );

        $this->assertNotNull($fresh);
        $this->assertSame(TicketStatus::ISSUED, $fresh->status);
        $this->assertNull($fresh->checked_in_at);
    }

    public function test_void_ticket_cannot_be_reprinted_and_trace_is_unchanged(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);
        $url = "/api/v1/travel/tickets/{$ticket->id}/pdf";

        $this->getJson($url)->assertOk();
        $this->postJson("/api/v1/travel/tickets/{$ticket->id}/revoke")->assertOk();

        $this->getJson($url)->assertStatus(410);

        $this->assertCount(1, $this->events($company, $ticket));
    }

    public function test_print_history_is_exposed_to_manager_of_the_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertOk();
        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertOk();

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/print-events")
            ->assertOk()
            ->assertJsonPath('data.ticket_number', $ticket->ticket_number)
            ->assertJsonPath('data.reprints_count', 1)
            ->assertJsonPath('data.events.0.reprint_number', 1)
            ->assertJsonPath('data.events.1.reprint_number', 0);
    }

    public function test_print_history_is_forbidden_for_non_manager(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->ticket($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/print-events")->assertStatus(403);
    }

    public function test_print_history_is_cross_tenant_safe(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);
        $ticket = $this->ticket($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyB);
        $this->principal($companyB);

        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/print-events")->assertStatus(404);
        $this->getJson("/api/v1/travel/tickets/{$ticket->id}/pdf")->assertStatus(404);
    }
}
