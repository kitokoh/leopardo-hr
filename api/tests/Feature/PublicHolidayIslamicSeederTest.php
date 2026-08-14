<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IslamicCalendarSeeder;
use Database\Seeders\PublicHolidaySeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1895 — les seeders `PublicHolidaySeeder` et `IslamicCalendarSeeder`
 * étaient orphelins (jamais appelés par DatabaseSeeder) : en production
 * (`php artisan db:seed --class=DatabaseSeeder --force` via
 * docker-entrypoint.sh), aucun pays n'avait de jours fériés → fallback
 * working_days actif partout.
 *
 * Couvre : le câblage dans l'orchestrateur ET l'idempotence des seeders.
 */
class PublicHolidayIslamicSeederTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_public_holiday_seeder_populates_fixed_holidays_and_is_idempotent(): void
    {
        (new PublicHolidaySeeder())->run();

        foreach (['DZ', 'CM', 'CI', 'SN'] as $countryCode) {
            $this->assertGreaterThan(
                0,
                PublicHoliday::where('country_code', $countryCode)->count(),
                "Aucun férié fixe seedé pour {$countryCode}"
            );
        }

        $dzCount = PublicHoliday::where('country_code', 'DZ')->count();

        // Idempotence : un second run ne duplique rien.
        (new PublicHolidaySeeder())->run();
        $this->assertSame($dzCount, PublicHoliday::where('country_code', 'DZ')->count());
    }

    public function test_islamic_calendar_seeder_populates_dates_and_is_idempotent(): void
    {
        (new IslamicCalendarSeeder())->run();

        $this->assertGreaterThan(
            0,
            DB::table('islamic_calendar')->count(),
            'Aucune date islamique seedée'
        );
        $this->assertGreaterThan(
            0,
            DB::table('islamic_calendar')->where('holiday_key', 'eid_al_fitr')->count()
        );

        $total = DB::table('islamic_calendar')->count();

        (new IslamicCalendarSeeder())->run();
        $this->assertSame($total, DB::table('islamic_calendar')->count());
    }

    public function test_database_seeder_wires_holiday_seeders(): void
    {
        // Reproduit la commande de production :
        //   php artisan db:seed --class=DatabaseSeeder --force
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
            ->assertExitCode(0);

        // Après le seed de prod, les fériés et dates islamiques existent.
        $this->assertGreaterThan(0, PublicHoliday::count());
        $this->assertGreaterThan(0, DB::table('islamic_calendar')->count());
    }
}
