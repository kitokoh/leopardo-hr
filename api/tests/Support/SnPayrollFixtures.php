<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Issue #2541 — valeurs attendues paie Sénégal centralisées.
 *
 * Source unique des attendus goldens/unit SN (charges sociales + TRIMF) pour
 * les bruts les plus cités (SMIG → haut salaire). Toute modification de taux
 * dans le moteur met à jour UN SEUL fichier (plus 6 suites à la main — la
 * divergence #2473 a nécessité 6 fichiers).
 *
 * Valeurs calculées À LA MAIN (SN_COMPLIANCE.md §1-7, jamais reprises du
 * code) et auto-vérifiées par GoldenSnFixturesConsistencyTest contre le
 * moteur : si une valeur ci-dessous dérive du moteur, le test échoue.
 *
 * @see tests/Feature/Payroll/Golden/GoldenSnPayrollTest
 */
final class SnPayrollFixtures
{
    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float}>
     *         clé = libellé → [brut, charges salariales, charges patronales, TRIMF]
     */
    public static function charges(): array
    {
        return [
            'SMIG 58 900' => [58900.0, 3298.40, 9070.60, 2700.0],
            'Ouvrier 100 000' => [100000.0, 5600.0, 13920.0, 5400.0],
            'Employé 250 000' => [250000.0, 14000.0, 31020.0, 9000.0],
            'Plafond IPRES T1 432 000' => [432000.0, 24192.0, 51768.0, 18000.0],
            'Cadre T1+T2 600 000' => [600000.0, 28224.0, 62856.0, 18000.0],
            'Cadre moyen T2 1 000 000' => [1000000.0, 37824.0, 89256.0, 36000.0],
            'Cadre haut T2 max 2 160 000' => [2160000.0, 65664.0, 165816.0, 36000.0],
            'Haut salaire T2 plafonné 3 000 000' => [3000000.0, 65664.0, 191016.0, 36000.0],
        ];
    }

    /**
     * @return array<string, array{0: float, 1: float}>  gross → TRIMF attendu
     */
    public static function trimfBrackets(): array
    {
        // Tranches officielles (SN_COMPLIANCE.md §3, source GoldenSnPayrollTest).
        return [
            'tranche 1 (≤ 25k)' => [25000.0, 900.0],
            'tranche 2 (25k-75k)' => [75000.0, 2700.0],
            'tranche 3 (75k-150k)' => [150000.0, 5400.0],
            'tranche 4 (150k-350k)' => [350000.0, 9000.0],
            'tranche 5 (350k-700k)' => [700000.0, 18000.0],
            'tranche 6 (> 700k)' => [800000.0, 36000.0],
        ];
    }
}
