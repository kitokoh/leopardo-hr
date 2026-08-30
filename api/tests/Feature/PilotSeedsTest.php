<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\FuelStationPilotSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-012 (#5870) — Seeds pilotes et données synthétiques (BC-01 PLATFORM).
 *
 * Critères d'acceptation :
 *  - seeds reproductibles par BC/verticale, sans secret ni donnée réelle ;
 *  - idempotents (réexécution sûre) ;
 *  - nettoyables (`pilot:seed --clean`) ;
 *  - ne peuvent cibler un tenant de production par erreur (garde env).
 */
class PilotSeedsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_fuel_pilot_seeder_creates_deterministic_synthetic_tenant(): void
    {
        self::assertTrue(Schema::hasTable('fuel_stations'), 'la migration fuel_stations doit être exécutée');

        $seeder = new FuelStationPilotSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        /** @var Company $company */
        $company = Company::query()->where('slug', 'fuel-pilot-001')->firstOrFail();

        self::assertSame('DZ', $company->country);
        self::assertSame('DZD', $company->currency);
        self::assertTrue($company->hasFeature('fuel_station'));
        self::assertSame(1, DB::table('employees')->where('company_id', $company->id)->count());
        self::assertSame(1, DB::table('fuel_stations')->where('company_id', $company->id)->count());
        self::assertSame(3, DB::table('fuel_products')->where('company_id', $company->id)->count());
        self::assertSame(2, DB::table('fuel_pumps')->where('company_id', $company->id)->count());
        self::assertSame(1, DB::table('fuel_tanks')->where('company_id', $company->id)->count());
        self::assertSame(1, DB::table('fuel_shifts')->where('company_id', $company->id)->count());

        // Ventes synthétiques déterministes — montants calculés à la main :
        // ESS95 40 l × 145 = 5 800 · Diesel 50 l × 135 = 6 750 · GPLc 20 l × 90 = 1 800.
        $sales = DB::table('fuel_sales')
            ->where('company_id', $company->id)
            ->orderBy('external_id')
            ->get();

        self::assertCount(3, $sales);
        self::assertSame(['5800.00', '6750.00', '1800.00'], $sales->pluck('amount')->map(fn ($a) => (string) $a)->all());

        // Zéro donnée réelle : domaine de test uniquement.
        $employeeEmail = DB::table('employees')->where('company_id', $company->id)->value('email');
        self::assertStringEndsWith('.leopardo.test', (string) $employeeEmail);
    }

    public function test_fuel_pilot_seeder_is_reentrant(): void
    {
        $seeder = new FuelStationPilotSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();
        $seeder->run();

        self::assertSame(1, Company::query()->where('slug', 'fuel-pilot-001')->count());
        $company = Company::query()->where('slug', 'fuel-pilot-001')->firstOrFail();
        self::assertSame(3, DB::table('fuel_sales')->where('company_id', $company->id)->count());
    }

    public function test_pilot_seed_command_cleans_pilot_tenant(): void
    {
        $seeder = new FuelStationPilotSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        /** @var Company $company */
        $company = Company::query()->where('slug', 'fuel-pilot-001')->firstOrFail();
        $companyId = (string) $company->id;

        $this->artisan('pilot:seed', ['--solution' => 'fuel', '--clean' => true])
            ->assertSuccessful();

        self::assertSame(0, Company::query()->where('slug', 'fuel-pilot-001')->count());
        self::assertSame(0, DB::table('fuel_sales')->where('company_id', $companyId)->count());
        self::assertSame(0, DB::table('fuel_stations')->where('company_id', $companyId)->count());
        self::assertSame(0, DB::table('employees')->where('company_id', $companyId)->count());
    }

    public function test_pilot_seeder_refuses_production_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/interdit|production/');

        $seeder = new FuelStationPilotSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();
    }

    public function test_edu_pilot_seeder_skips_gracefully_when_module_absent(): void
    {
        // Sur main (sans le socle EduManager #5974), le seeder ne plante pas :
        // il skip gracieusement et ne crée aucun tenant.
        if (Schema::hasTable('edu_campuses')) {
            self::markTestSkipped('Module EduManager déjà livré — le seeder est alors actif.');
        }

        $seeder = new \Database\Seeders\EduManagerPilotSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        self::assertSame(0, Company::query()->where('slug', 'edu-pilot-001')->count());
    }
}
