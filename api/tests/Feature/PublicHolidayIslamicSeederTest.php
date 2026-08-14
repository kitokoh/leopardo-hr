<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Support\Facades\Artisan;
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
        $exitCode = Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->assertSame(0, $exitCode);

        // Après le seed de prod, les fériés et dates islamiques existent.
        $this->assertGreaterThan(0, PublicHoliday::count());
        $this->assertGreaterThan(0, DB::table('islamic_calendar')->count());
    }

    public function test_islamic_calendar_seeder_covers_2028_plus(): void
    {
        // Issue #1931 : les données hardcodées ne couvraient que 2024-2027 —
        // en 2028 la paie perdait silencieusement tous les fériés islamiques.
        // L'algorithme hégirien tabulaire génère désormais 2024-2035.
        (new IslamicCalendarSeeder())->run();

        foreach (range(2028, 2035) as $year) {
            $this->assertGreaterThan(
                0,
                DB::table('islamic_calendar')->where('year', $year)->count(),
                "Aucune date islamique seedée pour {$year} (issue #1931)"
            );
        }
    }

    public function test_islamic_calendar_seeder_seeds_tahmarit_and_all_mapping_keys(): void
    {
        // Issue #1931 : tahmarit (Tamkharit — 10 Muharram) n'était JAMAIS
        // seedé → le Sénégal (seul pays du mapping à le fêter) n'avait pas sa
        // fête. Tous les holiday_key du mapping pays doivent exister en base.
        (new IslamicCalendarSeeder())->run();

        $this->assertGreaterThan(
            0,
            DB::table('islamic_calendar')->where('holiday_key', 'tahmarit')->count(),
            'tahmarit jamais seedé (issue #1931)'
        );

        $mapping = require config_path('islamic_holidays_map.php');

        foreach (array_keys($mapping['countries']['SN']) as $holidayKey) {
            $this->assertGreaterThan(
                0,
                DB::table('islamic_calendar')->where('holiday_key', $holidayKey)->count(),
                "holiday_key « {$holidayKey} » (mapping SN) jamais seedé"
            );
        }
    }
}
