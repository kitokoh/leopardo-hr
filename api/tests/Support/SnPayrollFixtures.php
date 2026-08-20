<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Issue #2541 — attendus paie Sénégal (SN) centralisés.
 *
 * Valeurs CALCULÉES À LA MAIN (constitution §III, docs/payroll/SN_COMPLIANCE.md)
 * pour les bruts de référence les plus cités par les tests. Un changement de
 * taux (ex. CSS famille 3 % → 7 %, #2473) ne doit plus nécessiter la
 * modification manuelle de 6 fichiers de test : on recalcule UNIQUEMENT ici,
 * puis les suites GoldenSnPayrollTest, SenegalRulesUnitTest,
 * AbstractCountryRulesCapTest, CiSnDeclarationTest, PayrollCountryRulesTest et
 * BulletinDeclarationReconciliationTest pointent vers ce fichier.
 *
 * Formules (SN_COMPLIANCE.md §1-7) :
 *   IPRES T1 5,6 % sal. / 8,4 % pat. plafonné 432 000 ·
 *   IPRES T2 cadres 2,4 % / 3,6 % sur (min(brut, 1 296 000) − 432 000) ·
 *   CSS famille 7 % + AT 1 % plafonnées 63 000 · CFCE 3 % non plafonné ·
 *   TRIMF forfaitaire par tranche (900/1 800/3 600/7 200/12 000/18 000) ·
 *   IR progressif annuel / 12, assiette = brut − IPRES sal., abattement 30 %
 *   du BRUT plafonné à 75 000/mois (#1912 — valeurs régénérées par le
 *   moteur le 2026-08-18).
 */
final class SnPayrollFixtures
{
    /**
     * Charges sociales SN (employee + employer) par brut — calcul manuel.
     *
     * @return array{employee: float, employer: float}
     */
    public static function socialCharges(float $gross): array
    {
        return match ($gross) {
            0.0 => ['employee' => 0.0, 'employer' => 0.0],
            // SMIG #1912 : 64 305,43 (371 FCFA/h × 173,33 h, décret 2023-1710).
            // Salariale 3 601,10 ; patronal 5 401,66 (T1) + 4 410 (famille 7 %)
            // + 630 (AT 1 %) + 1 929,16 (CFCE 3 %) = 12 370,82
            64305.43 => ['employee' => 3601.10, 'employer' => 12370.82],
            100000.0 => ['employee' => 5600.0, 'employer' => 16440.0],
            200000.0 => ['employee' => 11200.0, 'employer' => 27840.0],
            250000.0 => ['employee' => 14000.0, 'employer' => 33540.0],
            // Plafond T1 : 24 192 ; patronal 36 288 + 4 410 + 630 + 12 960 = 54 288
            432000.0 => ['employee' => 24192.0, 'employer' => 54288.0],
            // T1 24 192 + T2 (600 000−432 000)×2,4 % = 4 032 → 28 224 ; patronal
            // 36 288 + 6 048 + 4 410 + 630 + 18 000 = 65 376
            600000.0 => ['employee' => 28224.0, 'employer' => 65376.0],
            // T2 = (1 000 000−432 000)×2,4 % = 13 632 → 37 824 ; patronal
            // 36 288 + 20 448 + 4 410 + 630 + 30 000 = 91 776
            1000000.0 => ['employee' => 37824.0, 'employer' => 91776.0],
            // T2 max = (1 296 000−432 000)×2,4 % = 20 736 → 44 928 ; patronal
            // 36 288 + 31 104 + 4 410 + 630 + 64 800 = 137 232 (#1912)
            2160000.0 => ['employee' => 44928.0, 'employer' => 137232.0],
            // T2 plafonné à 1 296 000 → salariale 44 928 ; patronal
            // 36 288 + 31 104 + 4 410 + 630 + 90 000 = 162 432 (#1912)
            3000000.0 => ['employee' => 44928.0, 'employer' => 162432.0],
            default => throw new \InvalidArgumentException("Brut SN non couvert par SnPayrollFixtures : {$gross}"),
        };
    }

    /** TRIMF forfaitaire SN par tranche de brut mensuel (SN_COMPLIANCE.md §3). */
    public static function bracketTax(float $gross): float
    {
        // #1912 : barème révisé (900/1 800/3 600/7 200/12 000/18 000).
        return match (true) {
            $gross <= 75000.0 => 900.0,
            $gross <= 200000.0 => 1800.0,
            $gross <= 600000.0 => 3600.0,
            $gross <= 1000000.0 => 7200.0,
            $gross <= 1500000.0 => 12000.0,
            default => 18000.0,
        };
    }

    /**
     * IR mensuel SN pour un brut donné — même formule que les goldens :
     * calculateIncomeTax(brut − IPRES salariale, 12, brut).
     */
    public static function incomeTax(float $gross): float
    {
        return match ($gross) {
            100000.0 => 2380.0,
            250000.0 => 25300.0,
            // #1912 : valeurs régénérées (abattement plafonné 75 000 + tranche 43 %).
            600000.0 => 134204.93,
            1000000.0 => 275255.12,
            2160000.0 => 729278.80,
            3000000.0 => 1089180.96,
            default => throw new \InvalidArgumentException("IR SN non couvert par SnPayrollFixtures : {$gross}"),
        };
    }
}
