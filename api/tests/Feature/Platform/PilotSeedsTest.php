<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\PilotTenantSeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-012 (#5870) — Seeds pilotes et données synthétiques (BC-01 PLATFORM).
 *
 * Preuves : idempotence (double exécution → un seul tenant, mêmes données),
 * propreté (suppression complète), refus de cibler un tenant non pilote
 * (garde anti-production) et refus en environnement production sans force.
 */
class PilotSeedsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_seed_creates_deterministic_pilot_tenant(): void
    {
        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station'])->assertSuccessful();

        /** @var Company $company */
        $company = Company::query()->where('slug', 'fuel-pilot-001')->firstOrFail();

        self::assertTrue($company->metadata['pilot'] ?? false);
        self::assertTrue($company->metadata['synthetic'] ?? false);
        self::assertTrue($company->hasFeature('fuel_station'));
        self::assertTrue($company->hasFeature('rh'));
        self::assertStringContainsString('synthétiques', $company->name);

        // Verrou d'idempotence posé.
        self::assertTrue(DB::table('seed_locks')->where('lock_key', 'pilot_tenant:fuel-pilot-001')->exists());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station'])->assertSuccessful();
        $firstId = Company::query()->where('slug', 'fuel-pilot-001')->value('id');

        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station'])->assertSuccessful();

        self::assertSame(1, Company::query()->where('slug', 'fuel-pilot-001')->count());
        self::assertSame($firstId, Company::query()->where('slug', 'fuel-pilot-001')->value('id'));
    }

    public function test_seed_refuses_production_without_force(): void
    {
        $seeder = new PilotTenantSeeder(force: false, environment: 'production');

        try {
            $seeder->create('fuel_station');
            self::fail('Le seed pilote doit être refusé en production sans --force.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('production', $exception->getMessage());
        }

        self::assertSame(0, Company::query()->count());
    }

    public function test_seed_never_touches_a_non_pilot_tenant_with_same_slug(): void
    {
        // Un tenant RÉEL porte déjà le slug pilote (donnée réelle, pas de
        // marque pilot) — le seed doit refuser, jamais l'écraser.
        Company::factory()->create(['slug' => 'fuel-pilot-001', 'metadata' => ['pilot' => false]]);

        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station'])->assertFailed();

        $company = Company::query()->where('slug', 'fuel-pilot-001')->firstOrFail();
        self::assertNotTrue($company->metadata['pilot'] ?? false);
    }

    public function test_seed_delete_cleans_tenant_and_lock(): void
    {
        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station'])->assertSuccessful();
        self::assertSame(1, Company::query()->where('slug', 'fuel-pilot-001')->count());

        $this->artisan('leopardo:seed-pilot', ['--solution' => 'fuel_station', '--delete' => true])->assertSuccessful();

        self::assertSame(0, Company::query()->where('slug', 'fuel-pilot-001')->count());
        self::assertFalse(DB::table('seed_locks')->where('lock_key', 'pilot_tenant:fuel-pilot-001')->exists());
    }

    public function test_unknown_solution_is_rejected(): void
    {
        $this->artisan('leopardo:seed-pilot', ['--solution' => 'spaceship'])->assertFailed();
        self::assertSame(0, Company::query()->count());
    }
}
