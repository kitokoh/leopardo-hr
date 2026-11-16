<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-501..504/507 (#6071..#6074/#6077) — Rapports & dashboard travel.
 * Agrégats serveur exacts (minor units), isolation tenant, permission
 * `travel.reports` (403 employé lambda).
 */
class TravelReportTest extends TestCase
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

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
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
     * Fixture : trajet 40 places, 2 réservations confirmées (3+2 passagers,
     * 30000+20000) + 1 annulée (10000) + 1 paiement confirmé 30000 + 1 refunded 5000.
     */
    private function makeFixture(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
                'departure_date' => now()->toDateString(),
                'arrival_date' => now()->toDateString(),
            ]);

            $b1 = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'passenger_count' => 3,
                'total_amount_minor' => 30000,
                'booking_source' => 'office',
                'payment_status' => PaymentStatus::CONFIRMED->value,
            ]);
            $b2 = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED->value,
                'passenger_count' => 2,
                'total_amount_minor' => 20000,
                'booking_source' => 'online',
                'payment_status' => PaymentStatus::PENDING->value,
            ]);
            $b3 = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CANCELLED->value,
                'passenger_count' => 1,
                'total_amount_minor' => 10000,
                'booking_source' => 'office',
                'payment_status' => PaymentStatus::REFUNDED->value,
            ]);

            TravelPayment::factory()->create([
                'booking_id' => $b1->id,
                'amount_minor' => 30000,
                'status' => PaymentStatus::CONFIRMED->value,
            ]);
            TravelPayment::factory()->create([
                'booking_id' => $b3->id,
                'amount_minor' => 5000,
                'status' => PaymentStatus::REFUNDED->value,
            ]);

            return ['trip' => $trip];
        });
    }

    public function test_sales_revenue_occupancy_cancellations_dashboard(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);
        ['trip' => $trip] = $this->makeFixture($company);

        // #9098 — `toIso8601String()` produit `+00:00` ; non encode, le `+`
        // d'une query string se decode en ESPACE, donc `to` devenait
        // `2026-11-15T04:00:00 00:00` : la regle `date` echouait et l'API
        // repondait 422. On passe par `http_build_query`, qui encode.
        $from = now()->subDays(1)->toIso8601String();
        $to = now()->toIso8601String();
        $period = http_build_query(['from' => $from, 'to' => $to]);

        // Ventes : 2 réservations nettes (annulée exclue), 5 passagers, 50000.
        $this->getJson('/api/v1/travel/reports/sales?'.$period)
            ->assertOk()
            ->assertJsonPath('data.bookings_count', 2)
            ->assertJsonPath('data.passengers_count', 5)
            ->assertJsonPath('data.revenue_minor', 50000);

        // Recettes : confirmé 30000 − refunded 5000 = 25000.
        $this->getJson('/api/v1/travel/reports/revenue?'.$period)
            ->assertOk()
            ->assertJsonPath('data.confirmed_minor', 30000)
            ->assertJsonPath('data.refunded_minor', 5000)
            ->assertJsonPath('data.net_minor', 25000);

        // Occupation : 5 sièges vendus / 40 = 0.125.
        $this->getJson('/api/v1/travel/reports/occupancy?'.$period)
            ->assertOk()
            ->assertJsonPath('data.by_trip.0.trip_id', (int) $trip->id)
            ->assertJsonPath('data.by_trip.0.seats_sold', 5)
            ->assertJsonPath('data.by_trip.0.occupancy_rate', 0.125);

        // Annulations : 1 (office), 10000.
        $this->getJson('/api/v1/travel/reports/cancellations?'.$period)
            ->assertOk()
            ->assertJsonPath('data.cancellations_count', 1)
            ->assertJsonPath('data.by_source.office', 1);

        // Dashboard : ventes du jour 2, passagers 5, recettes nettes 25000.
        $this->getJson('/api/v1/travel/reports/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales_today', 2)
            ->assertJsonPath('data.passengers', 5)
            ->assertJsonPath('data.revenue_minor', 25000);
    }

    public function test_reports_require_travel_reports_permission(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->ordinaryEmployee($company);

        // Meme piege `+` : sans encodage, la validation repondait 422 avant que
        // la permission ne soit evaluee, donc le test ne prouvait rien.
        $period = http_build_query([
            'from' => now()->subDays(1)->toIso8601String(),
            'to' => now()->toIso8601String(),
        ]);
        $this->getJson('/api/v1/travel/reports/sales?'.$period)->assertStatus(403);

        $this->getJson('/api/v1/travel/reports/dashboard')->assertStatus(403);
    }
}
