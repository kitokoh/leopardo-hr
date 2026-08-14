<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Infrastructure\Services\IslamicHijriCalendar;
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

    public function test_islamic_calendar_seeder_generates_2028_and_beyond(): void
    {
        // Issue #1931 — le seeder doit couvrir 2028+ (algorithme hégirien
        // tabulaire) : en 2028 la paie ne doit PAS perdre silencieusement
        // les fériés islamiques (fallback working_days).
        (new IslamicCalendarSeeder())->run();

        // 2028 : toutes les fêtes du mapping DZ présentes.
        foreach (['eid_al_fitr', 'eid_al_adha', 'mawlid', 'muharram'] as $key) {
            $this->assertGreaterThan(
                0,
                DB::table('islamic_calendar')->where('holiday_key', $key)->where('year', 2028)->count(),
                "{$key} manquant pour 2028 (génération #1931)"
            );
        }

        // 2035 : toujours couvert (persistance de la génération).
        $this->assertGreaterThan(
            0,
            DB::table('islamic_calendar')->where('holiday_key', 'eid_al_fitr')->where('year', 2035)->count()
        );

        // Tahmarit (Tamkharit SN) seedé — clé attendue par le mapping SN
        // (config/islamic_holidays_map.php), jamais présente avant #1931.
        $this->assertGreaterThan(
            0,
            DB::table('islamic_calendar')->where('holiday_key', 'tahmarit')->count(),
            'tahmarit (Tamkharit) jamais seedé avant #1931'
        );
    }

    public function test_islamic_hijri_calendar_matches_observed_2024_2027(): void
    {
        // Validation de l'algorithme tabulaire sur les dates OBSERVÉES du
        // seeder (islamicfinder.org) : ±1-2 jours attendu (observation
        // lunaire), tolérance large de 3 jours.
        $cases = [
            [1445, 10, 1, '2024-04-10'],   // Aïd el-Fitr 2024
            [1446, 10, 1, '2025-03-30'],   // Aïd el-Fitr 2025
            [1446, 12, 10, '2025-06-06'],  // Aïd el-Adha 2025
            [1448, 3, 12, '2026-08-24'],   // Maouloud 2026
        ];

        foreach ($cases as [$hijriYear, $month, $day, $observed]) {
            $computed = IslamicHijriCalendar::hijriToGregorianDate($hijriYear, $month, $day);
            $diff = abs(strtotime($computed) - strtotime($observed)) / 86400;
            $this->assertLessThanOrEqual(
                3,
                $diff,
                "Écart inattendu entre tabulaire ({$computed}) et observé ({$observed}) pour H{$hijriYear}/{$month}/{$day}"
            );
        }
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
}
