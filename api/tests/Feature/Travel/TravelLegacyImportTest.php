<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Infrastructure\Services\TravelLegacyImportService;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1003 (#6116) — Import des données legacy gv-back.
 *
 * Dry-run sans écriture, mapping documenté, idempotence (rejouable sans
 * doublon), rapport complet, consentement jamais accordé par l'import.
 */
class TravelLegacyImportTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    private TravelLegacyImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
        $this->service = app(TravelLegacyImportService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedReferential(): array
    {
        return $this->tenants->withinTenant($this->company, function (): array {
            $douala = TravelCity::factory()->create(['name' => 'Douala']);
            $yaounde = TravelCity::factory()->create(['name' => 'Yaoundé']);
            $class = TravelClass::factory()->create(['code' => 'standard']);

            return ['douala' => $douala, 'yaounde' => $yaounde, 'class' => $class];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function makeDump(): array
    {
        return [
            'source' => 'gv-back',
            'routes' => [
                ['code' => 'DLA-YDE', 'origin_city' => 'Douala', 'destination_city' => 'Yaoundé', 'distance_km' => 250, 'duration_min' => 240],
                ['code' => 'INCONNUE', 'origin_city' => 'VilleAbsente', 'destination_city' => 'Yaoundé'],
            ],
            'trips' => [
                ['code' => 'T-001', 'route_code' => 'DLA-YDE', 'departure_date' => '2026-09-10', 'departure_time' => '08:00', 'total_seats' => 40, 'status' => 'published',
                    'prices' => [['class_code' => 'standard', 'adult_price_minor' => 15000, 'child_price_minor' => 7500]]],
            ],
            'bookings' => [
                ['reference' => 'GV-LEGACY001', 'trip_code' => 'T-001', 'status' => 'confirmed', 'total_amount_minor' => 30000, 'currency' => 'XAF',
                    'passengers' => [
                        ['full_name' => 'Voyageur Legacy 1', 'age_category' => 'adult', 'unit_price_minor' => 15000],
                        ['full_name' => 'Voyageur Legacy 2', 'age_category' => 'adult', 'unit_price_minor' => 15000],
                    ]],
            ],
            'contacts' => [
                ['email' => 'legacy@example.com', 'first_name' => 'Ancien', 'last_name' => 'Client'],
            ],
        ];
    }

    public function test_import_maps_and_creates_entities(): void
    {
        $this->seedReferential();

        $report = $this->tenants->withinTenant($this->company, fn (): array => $this->service->import($this->company, $this->makeDump()));

        self::assertSame(1, $report['routes']['created']);
        self::assertSame(1, $report['routes']['skipped'], 'route avec ville inconnue skippée');
        self::assertSame(1, $report['trips']['created']);
        self::assertSame(1, $report['prices']['created']);
        self::assertSame(1, $report['bookings']['created']);
        self::assertSame(2, $report['passengers']['created']);
        self::assertSame(1, $report['contacts']['created']);

        $this->tenants->withinTenant($this->company, function (): void {
            self::assertSame(1, TravelRoute::query()->where('code', 'DLA-YDE')->count());
            self::assertSame(1, TravelTrip::query()->where('code', 'T-001')->count());
            self::assertSame(1, TravelTripPrice::query()->count());
            self::assertSame('confirmed', TravelBooking::query()->where('reference', 'GV-LEGACY001')->firstOrFail()->status);
            self::assertSame(2, TravelPassenger::query()->count());
        });
    }

    public function test_import_is_idempotent(): void
    {
        $this->seedReferential();

        $this->tenants->withinTenant($this->company, function (): void {
            $this->service->import($this->company, $this->makeDump());
            $report = $this->service->import($this->company, $this->makeDump());

            // Rejeu : zéro création, uniquement des mises à jour.
            self::assertSame(0, $report['routes']['created']);
            self::assertSame(0, $report['trips']['created']);
            self::assertSame(0, $report['bookings']['created']);
            self::assertSame(0, $report['passengers']['created']);

            self::assertSame(1, TravelRoute::query()->count());
            self::assertSame(1, TravelTrip::query()->count());
            self::assertSame(1, TravelBooking::query()->count());
            self::assertSame(2, TravelPassenger::query()->count(), 'passagers non dupliqués au rejeu');
        });
    }

    public function test_import_never_grants_consent(): void
    {
        $this->seedReferential();

        $this->tenants->withinTenant($this->company, function (): void {
            $this->service->import($this->company, $this->makeDump());
        });

        $contact = TravelCustomerContact::query()->where('email', 'legacy@example.com')->firstOrFail();
        self::assertFalse($contact->email_consent_given, 'RGPD : l\'import n\'accorde jamais de consentement');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seedReferential();
        $dump = $this->makeDump();
        $dumpPath = tempnam(sys_get_temp_dir(), 'legacy_dump_');
        file_put_contents($dumpPath, json_encode($dump, JSON_THROW_ON_ERROR));

        Artisan::call('leopardo:travel:import-legacy', [
            'dump' => $dumpPath,
            '--company' => (string) $this->company->id,
            '--dry-run' => true,
        ]);

        $this->tenants->withinTenant($this->company, function (): void {
            self::assertSame(0, TravelRoute::query()->count(), 'dry-run : aucune écriture');
            self::assertSame(0, TravelBooking::query()->count());
        });

        unlink($dumpPath);
    }

    public function test_booking_statuses_are_frozen_on_reimport(): void
    {
        $this->seedReferential();

        $this->tenants->withinTenant($this->company, function (): void {
            $this->service->import($this->company, $this->makeDump());

            // Le dump est modifié (statut différent) — le rejeu ne doit PAS
            // faire évoluer une réservation importée (statuts figés).
            $dump = $this->makeDump();
            $dump['bookings'][0]['status'] = 'cancelled';
            $this->service->import($this->company, $dump);
        });

        $booking = TravelBooking::query()->where('reference', 'GV-LEGACY001')->firstOrFail();
        self::assertSame('confirmed', $booking->status, 'statut importé figé');
    }
}
