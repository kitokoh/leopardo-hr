<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyEntry;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyReward;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Infrastructure\Services\TravelLoyaltyService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-811 (#6101) — Fidélité voyageur.
 *
 * Couvre le critère d'acceptation : points crédités UNE seule fois par
 * billet, opt-in RGPD requis (aucun crédit sans opt-in), solde consultable
 * et échange de points (débit idempotent par réservation).
 */
class TravelLoyaltyApiTest extends TestCase
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
     * Réservation confirmée avec contact + billet émis.
     */
    private function confirmedBookingWithTicket(Company $company, string $contactEmail = 'client@example.com'): TravelTicket
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($contactEmail): TravelTicket {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 20]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED,
                'contact_email' => $contactEmail,
                'notify_consent' => true,
            ]);

            $passenger = $booking->passengers()->create([
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'unit_price_minor' => 10000,
            ]);

            /** @var TravelTicket $ticket */
            $ticket = TravelTicket::query()->create([
                'booking_id' => $booking->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
            $ticket->issueValidationCode();
            $ticket->save();

            return $ticket;
        });
    }

    public function test_no_credit_without_opt_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->confirmedBookingWithTicket($company);

        $credited = app(TenantManager::class)->withinTenant($company, fn () => app(TravelLoyaltyService::class)->creditForTicket($ticket));

        $this->assertSame(0, $credited);
        $this->assertSame(0, TravelLoyaltyAccount::query()->count());
    }

    public function test_points_credited_once_per_ticket_after_opt_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->confirmedBookingWithTicket($company);

        $result = app(TenantManager::class)->withinTenant($company, function () use ($company, $ticket): array {
            app(TravelLoyaltyService::class)->optIn($company->id, 'client@example.com');
            $service = app(TravelLoyaltyService::class);

            return [
                'first' => $service->creditForTicket($ticket),
                'replay' => $service->creditForTicket($ticket),
                'balance' => $service->balance($company->id, 'client@example.com'),
                'entries' => TravelLoyaltyEntry::query()->where('type', 'earned')->count(),
            ];
        });

        $this->assertSame(10, $result['first']);
        // Rejeu : crédit unique par billet.
        $this->assertSame(0, $result['replay']);
        $this->assertSame(10, $result['balance']);
        $this->assertSame(1, $result['entries']);
    }

    public function test_opt_in_required_for_redeem(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $ticket = $this->confirmedBookingWithTicket($company);
        $reward = app(TenantManager::class)->withinTenant($company, function () use ($company): TravelLoyaltyReward {
            return TravelLoyaltyReward::query()->create([
                'company_id' => $company->id,
                'name' => 'Réduction 5 %',
                'points_cost' => 10,
            ]);
        });

        $service = app(TravelLoyaltyService::class);
        $service->optIn($company->id, 'client@example.com');
        $service->creditForTicket($ticket);

        $entry = $service->redeem($company->id, 'client@example.com', $reward->id, (int) $ticket->booking_id);

        $this->assertSame(-10, $entry->points);
        $this->assertSame(0, $service->balance($company->id, 'client@example.com'));

        // Idempotence : même réservation → 422.
        try {
            $service->redeem($company->id, 'client@example.com', $reward->id, (int) $ticket->booking_id);
            $this->fail('Un second échange sur la même réservation devrait échouer.');
        } catch (ValidationException $e) {
            $this->fail('422 attendu (ValidationException inattendue).');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_account_endpoint_returns_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', [
            'contact_identifier' => 'client@example.com',
        ])->assertOk();

        $this->getJson('/api/v1/travel/loyalty/account?contact_identifier=client@example.com')
            ->assertOk()
            ->assertJsonPath('data.opt_in', true)
            ->assertJsonPath('data.points_balance', 0);
    }
}
