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
 *   IPRES T2 cadres 2,4 % / 3,6 % sur (min(brut, 2 160 000) − 432 000) ·
 *   CSS famille 3 % + AT 1 % plafonnées 63 000 · CFCE 3 % non plafonné ·
 *   TRIMF forfaitaire par tranche (900/2 700/5 400/9 000/18 000/36 000) ·
 *   IR progressif annuel / 12, assiette = brut − IPRES sal., abattement 30 %
 *   du BRUT (non plafonné).
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
            // SMIG : 58 900 × 5,6 % = 3 298,40 ; patronal
            // 4 947,60 (T1) + 1 767 + 589 + 1 767 (CSS/AT/CFCE) = 9 070,60
            58900.0 => ['employee' => 3298.40, 'employer' => 9070.60],
            100000.0 => ['employee' => 5600.0, 'employer' => 13920.0],
            200000.0 => ['employee' => 11200.0, 'employer' => 25320.0],
            250000.0 => ['employee' => 14000.0, 'employer' => 31020.0],
            // Plafond T1 : 24 192 ; patronal 36 288 + 1 890 + 630 + 12 960 = 51 768
            432000.0 => ['employee' => 24192.0, 'employer' => 51768.0],
            // T1 24 192 + T2 (600 000−432 000)×2,4 % = 4 032 → 28 224 ; patronal
            // 36 288 + 6 048 + 1 890 + 630 + 18 000 = 62 856
            600000.0 => ['employee' => 28224.0, 'employer' => 62856.0],
            // T2 = (1 000 000−432 000)×2,4 % = 13 632 → 37 824 ; patronal
            // 36 288 + 20 448 + 1 890 + 630 + 30 000 = 89 256
            1000000.0 => ['employee' => 37824.0, 'employer' => 89256.0],
            // T2 max = (2 160 000−432 000)×2,4 % = 41 472 → 65 664 ; patronal
            // 36 288 + 62 208 + 1 890 + 630 + 64 800 = 165 816
            2160000.0 => ['employee' => 65664.0, 'employer' => 165816.0],
            // T2 plafonné à 2 160 000 → salariale 65 664 ; patronal
            // 36 288 + 62 208 + 1 890 + 630 + 90 000 = 191 016
            3000000.0 => ['employee' => 65664.0, 'employer' => 191016.0],
            default => throw new \InvalidArgumentException("Brut SN non couvert par SnPayrollFixtures : {$gross}"),
        };
    }

    /** TRIMF forfaitaire SN par tranche de brut mensuel (SN_COMPLIANCE.md §3). */
    public static function bracketTax(float $gross): float
    {
        return match (true) {
            $gross <= 25000.0 => 900.0,
            $gross <= 75000.0 => 2700.0,
            $gross <= 150000.0 => 5400.0,
            $gross <= 350000.0 => 9000.0,
            $gross <= 700000.0 => 18000.0,
            default => 36000.0,
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
            600000.0 => 97454.93,
            1000000.0 => 192094.93,
            2160000.0 => 491784.40,
            3000000.0 => 726984.40,
            default => throw new \InvalidArgumentException("IR SN non couvert par SnPayrollFixtures : {$gross}"),
        };
    }
}
