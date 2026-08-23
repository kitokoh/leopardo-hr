<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class MoroccoPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'MA';
    }

    public function currency(): string
    {
        return 'MAD';
    }

    /**
     * SMIG secteur non agricole 2026 : 17,92 MAD/h × 191 h/mois (loi 65-99,
     * art. 184 — base mensuelle de 191 heures) = 3 422,72 MAD/mois
     * (revalorisation de l'accord social 2024-2026, en vigueur 2026).
     * Référence : docs/payroll/MA_COMPLIANCE.md §3.
     */
    public function minimumWage(): float
    {
        return 3422.72;
    }

    /**
     * Audit légal 2026 (issue #5248) — sources : CNSS.ma (taux AMO),
     * CLEISS (fiche Maroc), Upsilon Consulting 2026 (expert-comptable,
     * tableau 2026). Total salarié 6,74 % → 6,93 % avec IPE ; total
     * employeur 13,09 % → 21,47 % avec allocations familiales + TFP.
     *
     * Détail par branche (taux / plafond mensuel MAD) :
     *   CNSS prestations sociales     4,48 % sal. / 8,98 % pat. — plafond 6 000
     *   CNSS long terme (retraite…)   inclus dans 4,48/8,98 %   — plafond 6 000
     *   AMO                           2,26 % sal. / 4,11 % pat. — sans plafond
     *   IPE (perte d'emploi)          0,19 % sal. / 0,38 % pat. — plafond 6 000
     *   Allocations familiales        —         / 6,40 % pat.  — sans plafond
     *   Taxe formation professionnelle —        / 1,60 % pat.  — sans plafond
     */
    public function socialContributions(): array
    {
        return [
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_EMP', 'type' => 'employee', 'rate' => 4.48, 'cap' => 6000],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_PAT', 'type' => 'employer', 'rate' => 8.98, 'cap' => 6000],
            ['name' => 'AMO Salariale', 'code' => 'AMO_EMP', 'type' => 'employee', 'rate' => 2.26, 'cap' => null],
            ['name' => 'AMO Patronale', 'code' => 'AMO_PAT', 'type' => 'employer', 'rate' => 4.11, 'cap' => null],
            ['name' => 'IPE Salariale (perte d\'emploi)', 'code' => 'IPE_EMP', 'type' => 'employee', 'rate' => 0.19, 'cap' => 6000],
            ['name' => 'IPE Patronale (perte d\'emploi)', 'code' => 'IPE_PAT', 'type' => 'employer', 'rate' => 0.38, 'cap' => 6000],
            ['name' => 'Allocations familiales (patronale)', 'code' => 'AF_PAT', 'type' => 'employer', 'rate' => 6.40, 'cap' => null],
            ['name' => 'Taxe de formation professionnelle (patronale)', 'code' => 'TFP_PAT', 'type' => 'employer', 'rate' => 1.60, 'cap' => null],
        ];
    }

    /**
     * Barème IR 2026 (CGI Maroc art. 73-I, réforme Loi de Finances 2025,
     * en vigueur depuis 01/2025 — inchangée en 2026) : tranches ANNUELES
     * sur le revenu net imposable (brut − cotisations salariales − frais
     * professionnels), méthode « taux × revenu − somme à déduire » :
     *   0–40 000      0 %
     *   40 001–60 000 10 %  (déduction 4 000)
     *   60 001–80 000 20 %  (déduction 10 000)
     *   80 001–100 000 30 % (déduction 18 000)
     *   100 001–180 000 34 % (déduction 22 000)
     *   > 180 000      37 % (déduction 27 400)
     * L'impôt est calculé sur l'année puis rapporté au mois (÷ 12).
     */
    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 40000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 60000, 'rate' => 10, 'fixed_deduction' => 4000],
            ['min' => 60001, 'max' => 80000, 'rate' => 20, 'fixed_deduction' => 10000],
            ['min' => 80001, 'max' => 100000, 'rate' => 30, 'fixed_deduction' => 18000],
            ['min' => 100001, 'max' => 180000, 'rate' => 34, 'fixed_deduction' => 22000],
            ['min' => 180001, 'max' => null, 'rate' => 37, 'fixed_deduction' => 27400],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        // CGI Maroc art. 73-I (LF 2025) + art. 59-I (LF 2023) : abattement
        // pour frais professionnels appliqué AVANT le barème IR (méthode
        // dédiée moroccoProfessionalExpensesAbatement() — constitution
        // §III, jamais de calcul inline). Le moteur passe $grossTaxable =
        // brut − cotisations salariales (CNSS + AMO + IPE) et
        // $grossForAbatement = brut réel (défaut : $grossTaxable).
        $abatementBase = $grossForAbatement ?? $grossTaxable;
        $abatement = $this->moroccoProfessionalExpensesAbatement($abatementBase * $annualBasis);

        $annualTaxable = max(0.0, $grossTaxable * $annualBasis - $abatement);
        $tax = 0.0;
        $fixedDeduction = 0.0;

        foreach ($this->taxSlabs() as $slab) {
            $max = $slab['max'] ?? PHP_FLOAT_MAX;
            if ($annualTaxable >= $slab['min'] && $annualTaxable <= $max) {
                $tax = $annualTaxable * ($slab['rate'] / 100);
                $fixedDeduction = $slab['fixed_deduction'];
                break;
            }
        }

        return round(max(0, ($tax - $fixedDeduction)) / $annualBasis, 2);
    }

    /**
     * CGI Maroc art. 59-I (LF 2023) — abattement pour frais professionnels :
     *   35 % du revenu brut ANNUEL imposable si < 78 000 MAD,
     *   25 % si ≥ 78 000 MAD,
     *   plancher 2 500 MAD/an, plafond 35 000 MAD/an (ex-30 000 avant LF
     *   2023). Sources : BO LF 2023 (art. 59-I), cielmaroc.ma, Upsilon
     * Consulting 2026, docs/payroll/MA_COMPLIANCE.md §2.
     * Méthode dédiée (constitution §III).
     */
    public function moroccoProfessionalExpensesAbatement(float $annualGross): float
    {
        $rate = $annualGross < 78000.0 ? 0.35 : 0.25;

        return min(max($annualGross * $rate, 2500.0), 35000.0);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // ZONE-INFRA (#1820) : chaque cotisation passe par
        // computeContribution() (cap + taux résolus depuis la table
        // social_contributions si présente) — constitution §III, jamais de
        // calcul inline. Total salarié = CNSS + AMO + IPE ; total employeur
        // = CNSS + AMO + IPE + allocations familiales + TFP.
        $employee = round(
            $this->computeContribution($grossSalary, 'CNSS_EMP', 4.48, 6000)
            + $this->computeContribution($grossSalary, 'AMO_EMP', 2.26, null)
            + $this->computeContribution($grossSalary, 'IPE_EMP', 0.19, 6000),
            2,
        );

        $employer = round(
            $this->computeContribution($grossSalary, 'CNSS_PAT', 8.98, 6000)
            + $this->computeContribution($grossSalary, 'AMO_PAT', 4.11, null)
            + $this->computeContribution($grossSalary, 'IPE_PAT', 0.38, 6000)
            + $this->computeContribution($grossSalary, 'AF_PAT', 6.40, null)
            + $this->computeContribution($grossSalary, 'TFP_PAT', 1.60, null),
            2,
        );

        return [
            'employee' => $employee,
            'employer' => $employer,
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Casablanca';
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day in Morocco.
        return [7];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'MA fixed public holidays (décrets royaux, seed PublicHolidaySeeder, issue #2255): 1er jan, 11 jan, 1er mai, 30 juil, 14 août, 20 août, 21 août, 6 nov, 18 nov + mobiles islamiques (Aïd el-Fitr, Aïd el-Adha, 1er Moharrem, Aïd el-Mawlid) — PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['MA'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-005: Moroccan labor code (loi 65-99) sets the legal weekly
     * working-hours threshold at 44 hours/week for most non-agricultural
     * sectors.
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 44.0;
    }

    /**
     * PA2-COUNTRY-005: loi 65-99 art. 201 majore les heures supplementaires
     * de 25% (heures de jour) a 50% (heures de nuit/jour de repos), avec des
     * taux plus eleves les jours feries. Modelise ici uniquement le palier
     * par defaut "heures de jour", a titre pilote (confidenceLevel='pilot') ;
     * la distinction jour/nuit/ferie necessite un horodatage non disponible
     * dans cette interface generique.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.25],
        ];
    }
}
