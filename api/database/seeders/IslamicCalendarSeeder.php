<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1812/#1931 — Dates approximatives des fêtes islamiques.
 *
 * Source : islamicfinder.org / timeanddate.com pour 2024–2027 (valeurs
 * observées/éditoriales) ; au-delà, générateur ALGORITHMIQUE documenté.
 *
 * Toutes les lignes sont insérées avec `source = 'computed'` et
 * `confirmed = false` : un admin plateforme doit valider chaque date
 * officiellement depuis l'interface admin (issues #1812/#1930). Le calcul
 * de paie n'utilise QUE les dates confirmées (fix #1930).
 *
 * ## Générateur 2028+ (issue #1931)
 * Calendrier islamique TABULAIRE (« algorithme koweïtien », utilisé par la
 * plupart des convertisseurs Hégire ↔ Grégorien) :
 *   - années de 354 j (11 bissextiles par cycle de 30 ans : 2,5,7,10,13,
 *     16,18,21,24,26,29 — le 12e mois passe à 30 j) ;
 *   - conversion en jour julien puis en date grégorienne.
 * Chaque fête est ensuite CALIBRÉE sur les valeurs observées 2024–2027
 * (décalage médian observé − calculé : −1 j pour Aïd el-Fitr, −2 j pour
 * les autres) pour rester cohérent avec le décalage d'observation réel
 * (annonce lunaire locale) sans introduire de rupture entre 2027 et 2028.
 * Ces dates restent des PRÉVISIONS (`confirmed = false`) : la validation
 * admin annuelle reste obligatoire avant usage paie.
 *
 * Idempotent : ne réinsère pas si une entrée (holiday_key, year) existe.
 */
class IslamicCalendarSeeder extends Seeder
{
    /**
     * [holiday_key, year, gregorian_date, duration_days] — valeurs observées
     * 2024–2027 (islamicfinder.org / timeanddate.com), ancres de calibration.
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
        // Tamkharit / Achoura (10 Muharram) — jamais seedé avant #1931
        ['tahmarit', 2025, '2025-07-06', 1],
        ['tahmarit', 2026, '2026-06-26', 1],
        ['tahmarit', 2027, '2027-06-15', 1],
    ];

    /** Génère les dates 2028 à 2035 (10 ans après les ancres observées). */
    private const GENERATE_FROM_YEAR = 2028;

    private const GENERATE_TO_YEAR = 2035;

    /**
     * Fête → (mois hégirien, jour hégirien) dans le calendrier tabulaire.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const HIJRI_DAY = [
        'muharram' => [1, 1],   // 1er Muharram (nouvel an hégirien)
        'tahmarit' => [1, 10],  // 10 Muharram (Achoura / Tamkharit SN)
        'mawlid' => [3, 12],    // 12 Rabi' al-awwal (naissance du Prophète)
        'eid_al_fitr' => [10, 1],  // 1er Shawwal (fin du Ramadan)
        'eid_al_adha' => [12, 10], // 10 Dhou al-hijja (Aïd el-Adha)
    ];

    /** Calibration médiane (observé − calculé) par fête, ancres 2024–2027. */
    private const CALIBRATION_DAYS = [
        'eid_al_fitr' => -1,
        'eid_al_adha' => -2,
        'mawlid' => -2,
        'muharram' => -2,
        'tahmarit' => -2,
    ];

    public function run(): void
    {
        $rows = [];

        foreach (self::OBSERVED_DATES as [$holidayKey, $year, $date, $duration]) {
            if ($this->exists($holidayKey, $year)) {
                continue;
            }

            $rows[] = $this->row($holidayKey, $year, $date, $duration);
        }

        // Générateur 2028+ (issue #1931) : algorithme tabulaire calibré.
        foreach (self::HIJRI_DAY as $holidayKey => [$month, $day]) {
            for ($year = self::GENERATE_FROM_YEAR; $year <= self::GENERATE_TO_YEAR; $year++) {
                if ($this->exists($holidayKey, $year)) {
                    continue;
                }

                $date = $this->generateHijriDate($holidayKey, $month, $day, $year);
                if ($date === null) {
                    continue;
                }

                $rows[] = $this->row($holidayKey, $year, $date, $holidayKey === 'eid_al_adha' ? 2 : 1);
            }
        }

        if ($rows !== []) {
            DB::table('islamic_calendar')->insert($rows);
            // Null-safe : le seeder peut être appelé hors contexte artisan
            // (tests, orchestrateur) — $this->command est alors null.
            $this->command?->info(sprintf(
                'IslamicCalendarSeeder : %d dates islamiques insérées (observées + algorithme tabulaire calibré).',
                count($rows),
            ));
        }
    }

    private function exists(string $holidayKey, int $year): bool
    {
        return IslamicCalendar::query()
            ->where('holiday_key', $holidayKey)
            ->where('year', $year)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $holidayKey, int $year, string $date, int $duration): array
    {
        return [
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

    /**
     * Convertit une fête hégirienne (mois/jour, année grégorienne cible) en
     * date grégorienne via le calendrier tabulaire calibré.
     *
     * Pour l'année grégorienne $gregorianYear, on cherche l'année hégirienne
     * dont la fête tombe dans cette année grégorienne (balayage 1446–1460,
     * large pour couvrir la dérive de −11 j/an).
     */
    private function generateHijriDate(string $holidayKey, int $month, int $day, int $gregorianYear): ?string
    {
        for ($hijriYear = 1446; $hijriYear <= 1462; $hijriYear++) {
            $julian = $this->hijriToJulian($hijriYear, $month, $day);
            $gregorian = $this->julianToGregorian($julian);

            if ($gregorian->format('Y') === (string) $gregorianYear) {
                $calibrated = $gregorian->modify(sprintf('%+d days', self::CALIBRATION_DAYS[$holidayKey] ?? 0));

                return $calibrated->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Date hégirienne → jour julien (calendrier tabulaire « koweïtien »).
     *
     * Formule standard : JDN = d + ⌈29,5 × (m−1)⌉ + (y−1)×354 +
     * ⌊(3+11y)/30⌋ + 1948439,5 — les années bissextiles du cycle de 30 ans
     * sont portées par le terme ⌊(3+11y)/30⌋ (mois 12 à 30 j).
     */
    private function hijriToJulian(int $year, int $month, int $day): float
    {
        // Mois de 30 j (impairs) / 29 j (pairs) = ⌈29,5 × (m−1)⌉.
        $monthDays = 0;
        for ($m = 1; $m < $month; $m++) {
            $monthDays += $m % 2 === 1 ? 30 : 29;
        }

        return ($year - 1) * 354 + intdiv(3 + 11 * $year, 30) + $monthDays + $day + 1948439.5;
    }

    /**
     * Jour julien → date grégorienne (conversion standard Fliegel–Van Flandern).
     */
    private function julianToGregorian(float $julian): \DateTimeImmutable
    {
        $jd = (int) floor($julian + 0.5);

        $a = $jd + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);
        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
