<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Application\Actions\RebuildReportReadModelsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelDailySale;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelReportExport;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripOccupancy;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-501..507 (#6071..#6077) — Rapports & exports.
 *
 * Sales (agrégats exacts, isolation tenant), occupancy (taux exact), revenue
 * (confirmés − remboursés), cancellations (motifs), dashboard (KPIs
 * cohérents), export CSV (idempotence, contenu), read models (recalcul
 * idempotent) et RBAC (travel.reports, employé simple → 403).
 */
class TravelReportApiTest extends TestCase
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

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /**
     * @return array{trip: TravelTrip, route: TravelRoute}
     */
    private function publishedTrip(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            $dest = TravelCity::factory()->create(['name' => 'Yaoundé']);
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $dest->id,
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 40,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            return ['trip' => $trip->refresh(), 'route' => $route];
        });
    }

    /**
     * @return array{trip: TravelTrip, booking: TravelBooking}
     */
    private function confirmedBooking(Company $company, int $amountMinor = 15000): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($amountMinor): array {
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => PaymentStatus::CONFIRMED,
                'passenger_count' => 2,
                'total_amount_minor' => $amountMinor,
                'booking_source' => 'office',
            ]);

            TravelPayment::factory()->create([
                'booking_id' => $booking->id,
                'status' => PaymentStatus::CONFIRMED,
                'amount_minor' => $amountMinor,
                'provider_code' => 'cash',
            ]);

            return ['trip' => $trip, 'booking' => $booking];
        });
    }

    public function test_sales_report_returns_exact_aggregates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->confirmedBooking($company, 10000);
        $this->confirmedBooking($company, 20000);
        $this->confirmedBooking($company, 30000);

        $this->getJson('/api/v1/travel/reports/sales')
            ->assertOk()
            ->assertJsonPath('summary.booking_count', 3)
            ->assertJsonPath('summary.passenger_count', 6)
            ->assertJsonPath('summary.total_amount_minor', 60000);
    }

    public function test_sales_report_is_tenant_isolated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->activateTravel($other);
        $this->principal($company);

        $this->confirmedBooking($company, 10000);
        $this->confirmedBooking($other, 99999);

        $this->getJson('/api/v1/travel/reports/sales')
            ->assertOk()
            ->assertJsonPath('summary.total_amount_minor', 10000);
    }

    public function test_occupancy_report_computes_exact_rate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $trip = TravelTrip::factory()->create([
                'status' => 'published',
                'total_seats' => 40,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', SeatStatus::FREE)
                ->take(10)
                ->update(['status' => SeatStatus::SOLD]);
        });

        $this->getJson('/api/v1/travel/reports/occupancy')
            ->assertOk()
            ->assertJsonPath('data.data.0.total_seats', 40)
            ->assertJsonPath('data.data.0.sold_seats', 10)
            ->assertJsonPath('data.data.0.occupancy_rate', 0.25);
    }

    public function test_revenue_report_subtracts_refunds(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->confirmedBooking($company, 15000);
        $this->confirmedBooking($company, 15000);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $booking = TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED,
                'total_amount_minor' => 5000,
            ]);
            TravelPayment::factory()->create([
                'booking_id' => $booking->id,
                'status' => PaymentStatus::REFUNDED,
                'amount_minor' => 5000,
            ]);
        });

        $this->getJson('/api/v1/travel/reports/revenue')
            ->assertOk()
            ->assertJsonPath('data.confirmed_minor', 30000)
            ->assertJsonPath('data.refunded_minor', 5000)
            ->assertJsonPath('data.net_minor', 25000);
    }

    public function test_cancellations_report_groups_by_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelBooking::factory()->create([
                'status' => BookingStatus::CANCELLED,
                'cancel_reason' => 'Client indisponible',
                'cancelled_at' => now(),
            ]);
            TravelBooking::factory()->create([
                'status' => BookingStatus::CANCELLED,
                'cancel_reason' => 'Client indisponible',
                'cancelled_at' => now(),
            ]);
            TravelBooking::factory()->create([
                'status' => BookingStatus::CANCELLED,
                'cancel_reason' => 'Voyage annulé par l\'agence',
                'cancelled_at' => now(),
            ]);
            TravelBooking::factory()->create([
                'status' => BookingStatus::CONFIRMED,
            ]);
        });

        $this->getJson('/api/v1/travel/reports/cancellations')
            ->assertOk()
            ->assertJsonPath('data.cancelled_count', 3)
            ->assertJsonPath('data.total_final_count', 4)
            ->assertJsonPath('data.by_reason.0.reason', 'Client indisponible')
            ->assertJsonPath('data.by_reason.0.count', 2)
            ->assertJsonPath('data.by_reason.1.reason', 'Voyage annulé par l\'agence')
            ->assertJsonPath('data.by_reason.1.count', 1);
    }

    public function test_dashboard_kpis_match_detailed_reports(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->confirmedBooking($company, 15000);

        $this->getJson('/api/v1/travel/reports/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bookings_count', 1)
            ->assertJsonPath('data.sales_minor', 15000)
            ->assertJsonPath('data.passengers_count', 2)
            ->assertJsonPath('data.revenue_minor', 15000);
    }

    public function test_reports_require_manager_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->simpleEmployee($company);

        $this->getJson('/api/v1/travel/reports/sales')->assertForbidden();
        $this->getJson('/api/v1/travel/reports/dashboard')->assertForbidden();
        $this->getJson('/api/v1/travel/reports/export?type=sales')->assertForbidden();
    }

    public function test_export_is_idempotent_and_returns_signed_url(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->confirmedBooking($company, 15000);

        $first = $this->getJson('/api/v1/travel/reports/export?type=sales')->assertOk();
        $hash1 = $first->json('data.request_hash');

        $this->assertNotEmpty($hash1);
        $this->assertStringContainsString('travel/exports', $first->json('data.signed_url'));

        $second = $this->getJson('/api/v1/travel/reports/export?type=sales')->assertOk();

        $this->assertSame($hash1, $second->json('data.request_hash'));
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelReportExport::query()->count();
        }));
    }

    public function test_export_rejects_unknown_type(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/reports/export?type=bogus')->assertStatus(422);
    }

    public function test_read_models_rebuild_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $this->confirmedBooking($company, 15000);

        $action = app(RebuildReportReadModelsAction::class);

        $first = app(TenantManager::class)->withinTenant($company, fn (): int => $action->execute());
        $second = app(TenantManager::class)->withinTenant($company, fn (): int => $action->execute());

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, $second);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelDailySale::query()->count();
        }));
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTripOccupancy::query()->count();
        }));
    }
}
