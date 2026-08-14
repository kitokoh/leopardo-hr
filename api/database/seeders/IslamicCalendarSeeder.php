<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1812/#1931 — Dates approximatives des fêtes islamiques 2024–2035.
 *
 * 2024–2027 : valeurs indicatives sourcées (islamicfinder.org /
 * timeanddate.com) — inchangées (déjà seedées, ne pas régénérer).
 * 2028+ : générées par ALGORITHME HÉGIRIEN TABULAIRE (civil) — approximation
 * ±1 jour vs les dates astronomiques officielles (les années hégiriennes
 * commencent à l'observation de la lune). Toutes les lignes sont insérées
 * avec `source = 'computed'` et `confirmed = false` : un admin plateforme
 * doit valider chaque date officiellement depuis l'interface admin
 * (issues #1812/#1930).
 *
 * Couvre aussi `tahmarit` (Tamkharit — Achoura, 10 Muharram, mapping SN)
 * qui n'était jamais seedé, et complète `muharram` (1 Muharram) pour les
 * années manquantes.
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

    /**
     * Fêtes islamiques générées par l'algorithme hégirien tabulaire
     * (issue #1931) : [clé, mois hégirien, jour hégirien].
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const HIJRI_HOLIDAYS = [
        'eid_al_fitr' => [10, 1],   // 1 Shawwal
        'eid_al_adha' => [12, 10],  // 10 Dhu al-Hijjah
        'mawlid' => [3, 12],        // 12 Rabi' al-awwal
        'muharram' => [1, 1],       // 1 Muharram
        'tahmarit' => [1, 10],      // 10 Muharram — Tamkharit (Achoura, SN)
    ];

    /** Années grégoriennes couvertes par la génération (2024–2035). */
    private const GENERATED_FROM_YEAR = 2024;
    private const GENERATED_TO_YEAR = 2035;

    /**
     * Conversion hégirienne → grégorienne (calendrier tabulaire civil).
     *
     * Algorithme arithmétique standard (époch hégirienne 1948439.5,
     * années bissextiles 2/5/7/10/13/16/18/21/24/26/29 du cycle de 30 ans).
     * Approximation ±1 jour vs l'observation lunaire — les valeurs restent
     * `source = 'computed'`, `confirmed = false` (validation admin).
     */
    private function hijriToGregorian(int $hijriYear, int $month, int $day): string
    {
        $jdn = $day + (int) ceil(29.5 * ($month - 1)) + ($hijriYear - 1) * 354
            + (int) floor((3 + 11 * $hijriYear) / 30) + 1948439.5 - 1;
        $jd = (int) floor($jdn + 0.5);

        $a = $jd + 32044;
        $b = (int) floor((4 * $a + 3) / 146097);
        $c = $a - (int) floor(146097 * $b / 4);
        $d = (int) floor((4 * $c + 3) / 1461);
        $e = $c - (int) floor(1461 * $d / 4);
        $m = (int) floor((5 * $e + 2) / 153);
        $dayOfMonth = $e - (int) floor((153 * $m + 2) / 5) + 1;
        $monthOfYear = $m + 3 - 12 * (int) floor($m / 10);
        $year = 100 * $b + $d - 4800 + (int) floor($m / 10);

        return sprintf('%04d-%02d-%02d', $year, $monthOfYear, $dayOfMonth);
    }

    /**
     * Génère les lignes hégiriennes pour la plage grégorienne cible
     * (toutes les fêtes du mapping, y compris tahmarit SN).
     *
     * @return array<int, array{0: string, 1: int, 2: string, 3: int}>
     */
    private function generatedIslamicRows(): array
    {
        $rows = [];

        // Années hégiriennes 1445..1457 ≈ grégorien 2023-07 → 2036-06 :
        // couvre intégralement 2024–2035.
        for ($hijriYear = 1445; $hijriYear <= 1457; $hijriYear++) {
            foreach (self::HIJRI_HOLIDAYS as $holidayKey => [$hMonth, $hDay]) {
                $date = $this->hijriToGregorian($hijriYear, $hMonth, $hDay);
                $gregorianYear = (int) substr($date, 0, 4);

                if ($gregorianYear < self::GENERATED_FROM_YEAR || $gregorianYear > self::GENERATED_TO_YEAR) {
                    continue;
                }

                $rows[] = [
                    $holidayKey,
                    $gregorianYear,
                    $date,
                    $holidayKey === 'eid_al_adha' ? 2 : 1,
                ];
            }
        }

        return $rows;
    }

    public function run(): void
    {
        $dates = [...self::ISLAMIC_DATES, ...$this->generatedIslamicRows()];

        $rows = [];
        foreach ($dates as [$holidayKey, $year, $date, $duration]) {
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
