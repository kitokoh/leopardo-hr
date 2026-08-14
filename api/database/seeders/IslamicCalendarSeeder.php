<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use App\Modules\Payroll\Infrastructure\Services\IslamicHijriCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1812/#1931 — Dates approximatives des fêtes islamiques.
 *
 * Deux sources :
 *  1. 2024–2027 : dates OBSERVÉES codées en dur (islamicfinder.org /
 *     timeanddate.com — valeurs indicatives, référence historique).
 *  2. 2028+ : générées par ALGORITHME hégirien tabulaire
 *     (`IslamicHijriCalendar`, issue #1931) — précision ±1-2 jours vs
 *     l'observation lunaire, marquées `source = 'computed'` et
 *     `confirmed = false` : un admin plateforme valide chaque date
 *     officiellement depuis l'interface admin (issue #1812/#1930).
 *
 * `tahmarit` (Tamkharit/Achoura, 10 Muharram) est généré pour toutes les
 * années — clé attendue par le mapping SN (`config/islamic_holidays_map.php`).
 *
 * Idempotent : ne réinsère pas si une entrée (holiday_key, year) existe.
 */
class IslamicCalendarSeeder extends Seeder
{
    /**
     * Dates OBSERVÉES 2024-2027 (référence) — [holiday_key, year, gregorian_date, duration_days].
     *
     * @var array<int, array{0: string, 1: int, 2: string, 3: int}>
     */
    private const OBSERVED_DATES = [
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

    /**
     * Jours hégiriens des fêtes générées — [holiday_key, mois hégirien, jour, durée défaut].
     *  - eid_al_fitr : 1 Shawwal (mois 10)
     *  - eid_al_adha : 10 Dhu al-Hijjah (mois 12)
     *  - mawlid : 12 Rabi' al-awwal (mois 3)
     *  - muharram : 1 Muharram (mois 1)
     *  - tahmarit : 10 Muharram (mois 1) — Achoura/Tamkharit (SN)
     *
     * @var array<int, array{0: string, 1: int, 2: int, 3: int}>
     */
    private const HIJRI_FEASTS = [
        ['eid_al_fitr', 10, 1, 1],
        ['eid_al_adha', 12, 10, 2],
        ['mawlid', 3, 12, 1],
        ['muharram', 1, 1, 1],
        ['tahmarit', 1, 10, 1],
    ];

    /** Première année grégorienne générée par algorithme (les précédentes sont observées). */
    private const FIRST_COMPUTED_YEAR = 2028;

    /** Dernière année grégorienne générée. */
    private const LAST_COMPUTED_YEAR = 2040;

    public function run(): void
    {
        $rows = $this->observedRows();
        $rows = array_merge($rows, $this->computedRows());

        if ($rows !== []) {
            DB::table('islamic_calendar')->insert($rows);
            // Null-safe : le seeder peut être appelé hors contexte artisan
            // (tests, orchestrateur) — $this->command est alors null.
            $this->command?->info(sprintf(
                'IslamicCalendarSeeder : %d dates islamiques insérées (2024-2027 observées, 2028+ approximatives).',
                count($rows)
            ));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function observedRows(): array
    {
        $rows = [];
        foreach (self::OBSERVED_DATES as [$holidayKey, $year, $date, $duration]) {
            $row = $this->row($holidayKey, $year, $date, $duration, 'computed');
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Génère les fêtes 2028+ par conversion hégirienne tabulaire.
     * Chaque fête est attribuée à l'année grégorienne de sa date.
     *
     * @return list<array<string, mixed>>
     */
    private function computedRows(): array
    {
        $rows = [];
        // Une année grégorienne peut voir 2 occurrences d'une même fête
        // (ex. Aïd début et fin d'année) : on garde la première par
        // (holiday_key, year) pour respecter l'unicité de la table.
        $seen = [];

        for ($hijriYear = IslamicHijriCalendar::FIRST_HIJRI_YEAR; $hijriYear <= IslamicHijriCalendar::LAST_HIJRI_YEAR; $hijriYear++) {
            foreach (self::HIJRI_FEASTS as [$holidayKey, $hijriMonth, $hijriDay, $duration]) {
                $gregorian = IslamicHijriCalendar::jdnToGregorian(
                    IslamicHijriCalendar::hijriToJdn($hijriYear, $hijriMonth, $hijriDay)
                );
                $year = $gregorian['year'];
                if ($year < self::FIRST_COMPUTED_YEAR || $year > self::LAST_COMPUTED_YEAR) {
                    continue;
                }
                $date = sprintf('%04d-%02d-%02d', $year, $gregorian['month'], $gregorian['day']);

                $key = $holidayKey.'|'.$year;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $row = $this->row($holidayKey, $year, $date, $duration, 'computed');
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $holidayKey, int $year, string $date, int $duration, string $source): ?array
    {
        if (IslamicCalendar::query()->where('holiday_key', $holidayKey)->where('year', $year)->exists()) {
            return null; // déjà seedé (idempotent)
        }

        return [
            'holiday_key' => $holidayKey,
            'year' => $year,
            'gregorian_date' => $date,
            'duration_days' => $duration,
            'source' => $source,
            'confirmed' => false,
            'confirmed_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
