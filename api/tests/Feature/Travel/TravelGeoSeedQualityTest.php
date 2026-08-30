<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1004 (#6117) — Qualité du seed géographique legacy.
 *
 * Le dump pays/villes de gv-back est converti en seed versionné
 * (TRAVEL-202/#6015) : rejouable sans doublon, codes ISO 3166-1 valides,
 * aucune ville sans pays.
 */
class TravelGeoSeedQualityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    public function test_seed_is_idempotent(): void
    {
        $action = app(ActivateTravelAgencyAction::class);

        $this->tenants->withinTenant($this->company, function () use ($action): void {
            $action->execute($this->company);
            $countriesAfterFirst = TravelCountry::query()->count();

            // Rejeu (re-activation + seed) : aucun doublon.
            $action->execute($this->company);

            self::assertGreaterThan(0, $countriesAfterFirst);
            self::assertSame($countriesAfterFirst, TravelCountry::query()->count(), 'rejouable sans doublon');
        });
    }

    public function test_country_iso_codes_are_valid_iso_3166_1(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            app(ActivateTravelAgencyAction::class)->execute($this->company);

            $badIso = TravelCountry::query()
                ->get(['iso2'])
                ->filter(fn (TravelCountry $c): bool => ! preg_match('/^[A-Z]{2}$/', (string) $c->iso2))
                ->count();

            self::assertSame(0, $badIso, 'tous les codes ISO2 sont au format ISO 3166-1 (2 lettres majuscules)');
            self::assertGreaterThanOrEqual(100, TravelCountry::query()->count(), 'dump legacy complet');
        });
    }

    public function test_cities_always_reference_an_existing_country(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            app(ActivateTravelAgencyAction::class)->execute($this->company);

            $orphan = TravelCity::query()
                ->whereDoesntHave('country')
                ->count();

            self::assertSame(0, $orphan, 'aucune ville orpheline');
        });
    }

    public function test_seed_is_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->tenants->withinTenant($this->company, fn () => app(ActivateTravelAgencyAction::class)->execute($this->company));

        $this->tenants->withinTenant($companyB, function (): void {
            self::assertSame(0, TravelCountry::query()->count(), 'le tenant B ne voit pas le seed du tenant A');
        });
    }
}
