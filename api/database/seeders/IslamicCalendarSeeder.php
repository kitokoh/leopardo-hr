<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1812 — Dates approximatives des fêtes islamiques 2024–2028.
 *
 * Source : islamicfinder.org / timeanddate.com (valeurs indicatives).
 * Toutes les lignes sont insérées avec `source = 'computed'` et
 * `confirmed = false` : un admin plateforme doit valider chaque date
 * officiellement depuis l'interface admin (issue #1812).
 *
 * Idempotent : ne réinsère pas si une entrée (holiday_key, year) existe.
 */
class IslamicCalendarSeeder extends Seeder
{
    /**
     * [holiday_key, year, gregorian_date, duration_days]
     *
     * @var array<int, array{0: string, 1: int, 2: string, 3: int}>
     */
    private const ISLAMIC_DATES = [
        // Aïd el-Fitr
        ['eid_al_fitr', 2024, '2024-04-10', 1],
        ['eid_al_fitr', 2025, '2025-03-30', 1],
        ['eid_al_fitr', 2026, '2026-03-20', 1],
        ['eid_al_fitr', 2027, '2027-03-09', 1],
        // Aïd el-Adha (durée par défaut 2 jours ; le mapping pays ajuste)
        ['eid_al_adha', 2024, '2024-06-16', 2],
        ['eid_al_adha', 2025, '2025-06-06', 2],
        ['eid_al_adha', 2026, '2026-05-27', 2],
        ['eid_al_adha', 2027, '2027-05-17', 2],
        // Maouloud
        ['mawlid', 2024, '2024-09-15', 1],
        ['mawlid', 2025, '2025-09-04', 1],
        ['mawlid', 2026, '2026-08-24', 1],
        ['mawlid', 2027, '2027-08-14', 1],
        // Nouvel an hégirien (Muharram)
        ['muharram', 2025, '2025-06-26', 1],
        ['muharram', 2026, '2026-06-16', 1],
    ];

    public function run(): void
    {
        $rows = [];
        foreach (self::ISLAMIC_DATES as [$holidayKey, $year, $date, $duration]) {
            if (IslamicCalendar::query()->where('holiday_key', $holidayKey)->where('year', $year)->exists()) {
                continue; // déjà seedé (idempotent)
            }

            $rows[] = [
                'holiday_key' => $holidayKey,
                'year' => $year,
                'gregorian_date' => $date,
                'duration_days' => $duration,
                'source' => 'computed',
                'confirmed' => false,
                'confirmed_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('islamic_calendar')->insert($rows);
            // Null-safe : le seeder peut être appelé hors contexte artisan
            // (tests, orchestrateur) — $this->command est alors null.
            $this->command?->info(sprintf('IslamicCalendarSeeder : %d dates islamiques insérées (approximatives).', count($rows)));
        }
    }
}
