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
use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
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
    /**
     * Fenêtre `from`/`to` exigée par TravelReportRequest (#8128) : couvre les
     * réservations créées à l'instant ET les départs de trips des factories
     * (+1..+30 j).
     */
    private function reportWindow(): string
    {
        return http_build_query([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDays(31)->toDateString(),
        ]);
    }

    /**
     * Corps exigé par StoreTravelExportRequest (#6075) : `report_type`
     * allowlisté, fenêtre `from`/`to`, `idempotency_key` obligatoire.
     *
     * @return array{report_type: string, from: string, to: string, idempotency_key: string}
     */
    private function exportPayload(string $idempotencyKey, string $reportType = 'sales'): array
    {
        return [
            'report_type' => $reportType,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDays(31)->toDateString(),
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /** @return array{trip: TravelTrip, booking: TravelBooking} */
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

        // Contrat actuel : `from`/`to` obligatoires (TravelReportRequest) et
        // agrégats sous `data.*` (booking_count → bookings_count, #8128).
        $this->getJson('/api/v1/travel/reports/sales?'.$this->reportWindow())
            ->assertOk()
            ->assertJsonPath('data.bookings_count', 3)
            ->assertJsonPath('data.passengers_count', 6)
            ->assertJsonPath('data.revenue_minor', 60000);
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

        $this->getJson('/api/v1/travel/reports/sales?'.$this->reportWindow())
            ->assertOk()
            ->assertJsonPath('data.revenue_minor', 10000);
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

        $this->getJson('/api/v1/travel/reports/occupancy?'.$this->reportWindow())
            ->assertOk()
            ->assertJsonPath('data.by_trip.0.total_seats', 40)
            ->assertJsonPath('data.by_trip.0.seats_sold', 10)
            ->assertJsonPath('data.by_trip.0.occupancy_rate', 0.25);
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

        $this->getJson('/api/v1/travel/reports/revenue?'.$this->reportWindow())
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

        // TRAVEL-504 : `cancellations_count` (clé courante) + agrégat
        // `by_reason` restauré dans le service (#8128 — motifs décroissants).
        $this->getJson('/api/v1/travel/reports/cancellations?'.$this->reportWindow())
            ->assertOk()
            ->assertJsonPath('data.cancellations_count', 3)
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

        // Clés du contrat dashboard courant (TravelReportService::dashboard).
        $this->getJson('/api/v1/travel/reports/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales_today', 1)
            ->assertJsonPath('data.confirmed_minor', 15000)
            ->assertJsonPath('data.passengers', 2)
            ->assertJsonPath('data.revenue_minor', 15000);
    }

    public function test_reports_require_manager_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->simpleEmployee($company);

        // Fenêtre valide exigée sur /sales : la validation FormRequest
        // s'exécute avant le Gate — sans from/to, un 422 masquerait le 403.
        $this->getJson('/api/v1/travel/reports/sales?'.$this->reportWindow())->assertForbidden();
        $this->getJson('/api/v1/travel/reports/dashboard')->assertForbidden();
        // L'export est ASYNCHRONE (#6075) : POST + corps validé. En GET, la
        // route n'existe pas pour cette URI -> 405, qui masquait le 403.
        $this->postJson('/api/v1/travel/reports/export', $this->exportPayload('perm-export'))
            ->assertForbidden();
    }

    public function test_export_is_idempotent_and_returns_signed_url(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->confirmedBooking($company, 15000);

        // POST = demande d'export : 202 + asset, job dispatché (queue sync en
        // test, donc le fichier peut être généré dans la même requête).
        $first = $this->postJson('/api/v1/travel/reports/export', $this->exportPayload('export-1'))
            ->assertStatus(202)
            ->assertJsonPath('data.report_type', 'sales');

        $assetId = $first->json('data.id');
        $this->assertNotNull($assetId, 'un asset export est cree');

        // GET de l'asset : même ressource, URL signée régénérée si généré.
        $detail = $this->getJson("/api/v1/travel/reports/export/{$assetId}")->assertOk();
        $this->assertSame($assetId, $detail->json('data.id'));
        $this->assertContains($detail->json('data.status'), [
            TravelExportAsset::STATUS_PENDING,
            TravelExportAsset::STATUS_GENERATED,
        ]);
        if ($detail->json('data.status') === TravelExportAsset::STATUS_GENERATED) {
            $this->assertNotEmpty($detail->json('data.signed_url'));
        }

        // Rejeu de la MÊME clé d'idempotence : même asset, aucun doublon.
        $second = $this->postJson('/api/v1/travel/reports/export', $this->exportPayload('export-1'))
            ->assertStatus(202);

        $this->assertSame($assetId, $second->json('data.id'));
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelExportAsset::query()->count();
        }));
    }

    public function test_export_rejects_unknown_type(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/reports/export', $this->exportPayload('bogus-1', 'bogus'))
            ->assertStatus(422);
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
