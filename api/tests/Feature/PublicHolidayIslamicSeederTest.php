<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IslamicCalendarSeeder;
use Database\Seeders\PublicHolidaySeeder;
use Illuminate\Support\Facades\Artisan;
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
        (new PublicHolidaySeeder)->run();

        foreach (['DZ', 'CM', 'CI', 'SN'] as $countryCode) {
            $this->assertGreaterThan(
                0,
                PublicHoliday::where('country_code', $countryCode)->count(),
                "Aucun férié fixe seedé pour {$countryCode}"
            );
        }

        $dzCount = PublicHoliday::where('country_code', 'DZ')->count();

        // Idempotence : un second run ne duplique rien.
        (new PublicHolidaySeeder)->run();
        $this->assertSame($dzCount, PublicHoliday::where('country_code', 'DZ')->count());
    }

    public function test_islamic_calendar_seeder_populates_dates_and_is_idempotent(): void
    {
        (new IslamicCalendarSeeder)->run();

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

        (new IslamicCalendarSeeder)->run();
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

    // ── Issue #1931 : pérennité des données islamiques 2028+ ──────────────

    public function test_islamic_calendar_seeder_covers_2028_plus_with_all_keys(): void
    {
        (new IslamicCalendarSeeder)->run();

        // 2028+ : aucune perte silencieuse des fériés islamiques (fallback
        // working_days redevient actif sans ces lignes).
        foreach ([2028, 2029, 2030, 2031, 2032, 2033] as $year) {
            $keys = DB::table('islamic_calendar')
                ->where('year', $year)
                ->pluck('holiday_key')
                ->all();

            $this->assertContains('eid_al_fitr', $keys, "eid_al_fitr manquant en {$year}");
            $this->assertContains('eid_al_adha', $keys, "eid_al_adha manquant en {$year}");
            $this->assertContains('mawlid', $keys, "mawlid manquant en {$year}");
        }
    }

    public function test_tahmarit_is_seeded_for_senegal_mapping(): void
    {
        (new IslamicCalendarSeeder)->run();

        // Tahmarit (Tamkharit — 10 Muharram) est requis par le mapping SN
        // (`config/islamic_holidays_map.php`) mais n'était jamais seedé.
        $sn = DB::table('islamic_calendar')
            ->where('holiday_key', 'tahmarit')
            ->orderBy('year')
            ->get();

        $this->assertGreaterThan(0, $sn->count(), 'Aucune date tahmarit seedée');
        $this->assertSame('2025-07-06', $sn->first()?->gregorian_date);
    }

    public function test_all_country_mapping_keys_have_seeded_dates(): void
    {
        (new IslamicCalendarSeeder)->run();

        /** @var array<string, array<string, mixed>> $countriesConfig */
        $countriesConfig = config('islamic_holidays_map.countries');

        $mappingKeys = collect($countriesConfig)
            ->flatMap(fn (array $holidays): array => array_keys($holidays))
            ->unique()
            ->values();

        $seededKeys = DB::table('islamic_calendar')
            ->distinct()
            ->pluck('holiday_key');

        foreach ($mappingKeys as $key) {
            $this->assertTrue(
                $seededKeys->contains($key),
                "Aucune date seedée pour la fête du mapping pays : {$key}"
            );
        }
    }
}
