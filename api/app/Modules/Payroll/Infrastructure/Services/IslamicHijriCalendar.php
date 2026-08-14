<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

/**
 * Issue #1931 — Conversion grégorienne ↔ hégirienne (calendrier tabulaire).
 *
 * Algorithme arithmétique standard (« tabular Islamic calendar », formule de
 * Calendrical Calculations / Wikipedia) : cycle de 30 ans avec 11 années
 * bissextiles (354/355 jours), époque 1 Muharram 1 AH = 19 juillet 622
 * grégorien (JDN 1948440).
 *
 * Précision : les dates tabulaires peuvent différer de ±1-2 jours des dates
 * OBSERVÉES (elles dépendent de l'observation du croissant lunaire, variable
 * par pays). C'est suffisant pour le seeder `islamic_calendar` dont toutes
 * les lignes sont marquées `source = 'computed'` et `confirmed = false` :
 * un admin plateforme valide chaque date officiellement avant usage
 * (issue #1812/#1930) — le calcul n'est jamais présenté comme certifié.
 *
 * Validé sur les dates de référence du seeder (islamicfinder.org) :
 *   - 1 Shawwal 1445 → 2024-04-10 tabulaire vs 2024-04-10 observé (Δ 0 j)
 *   - 10 Dhu al-Hijjah 1445 → 2024-06-17 tabulaire vs 2024-06-16 observé (Δ 1 j)
 */
final class IslamicHijriCalendar
{
    /** JDN de l'époque hégirienne : 1 Muharram 1 AH = 19/07/622 grégorien. */
    private const ISLAMIC_EPOCH_JDN = 1948440;

    /** Année hégirienne de départ du générateur (1445 AH ≈ 2023/2024). */
    public const FIRST_HIJRI_YEAR = 1445;

    /** Dernière année hégirienne générée (1470 AH ≈ 2048/2049). */
    public const LAST_HIJRI_YEAR = 1470;

    /**
     * Convertit une date hégirienne (année, mois, jour) en JDN tabulaire.
     */
    public static function hijriToJdn(int $year, int $month, int $day): int
    {
        return $day
            + (int) ceil(29.5 * ($month - 1))
            + ($year - 1) * 354
            + (int) floor((3 + 11 * $year) / 30)
            + self::ISLAMIC_EPOCH_JDN
            - 1;
    }

    /**
     * Convertit un JDN en date grégorienne (formule de Meeus).
     *
     * @return array{year: int, month: int, day: int}
     */
    public static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = (int) floor((4 * $a + 3) / 146097);
        $c = $a - (int) floor(146097 * $b / 4);
        $d = (int) floor((4 * $c + 3) / 1461);
        $e = $c - (int) floor(1461 * $d / 4);
        $m = (int) floor((5 * $e + 2) / 153);
        $day = $e - (int) floor((153 * $m + 2) / 5) + 1;
        $month = $m + 3 - 12 * (int) floor($m / 10);
        $year = 100 * $b + $d - 4800 + (int) floor($m / 10);

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * Date grégorienne (Y-m-d) d'un jour hégirien donné.
     */
    public static function hijriToGregorianDate(int $year, int $month, int $day): string
    {
        $g = self::jdnToGregorian(self::hijriToJdn($year, $month, $day));

        return sprintf('%04d-%02d-%02d', $g['year'], $g['month'], $g['day']);
    }
}
