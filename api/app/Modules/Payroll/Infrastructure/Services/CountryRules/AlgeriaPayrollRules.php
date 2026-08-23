<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

class AlgeriaPayrollRules extends AbstractCountryRules
{
    public function countryCode(): string
    {
        return 'DZ';
    }

    public function currency(): string
    {
        return 'DZD';
    }

    public function minimumWage(): float
    {
        return 20000.0;
    }

    public function socialContributions(): array
    {
        // Issue #1819/#1943 — ASSURANCE CHÔMAGE DZ : le régime contributif
        // CNAC (décret législatif n° 94-11, art. 94-188 ; décrets exécutifs
        // n° 22-70 du 10/02/2022 et n° 26-87 du 21/01/2026) couvre les
        // salariés du secteur privé licenciés pour motif économique, financé
        // par 1 % patron + 0,5 % salarié — DÉJÀ inclus dans les agrégats
        // CNAS ci-dessous (9 % / 26 %). L'allocation chômage des
        // primo-demandeurs (ANEM, 13 000 DZD/mois) est financée par le budget
        // de l'État, pas par les entreprises.
        // → PAS de lignes AC_DZ_EMP / AC_DZ_PAT séparées (double cotisation) ;
        //   le cadrage « AC inclus dans CNAS » est documenté dans
        //   docs/payroll/DZ_COMPLIANCE.md §7 et verrouillé par le golden test
        //   GoldenDzEndOfContractFullTest (issue #1943).
        return [
            ['name' => 'CNAS Salariale', 'code' => 'CNAS_EMP', 'type' => 'employee', 'rate' => 9.0, 'cap' => null],
            ['name' => 'CNAS Patronale', 'code' => 'CNAS_PAT', 'type' => 'employer', 'rate' => 26.0, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 20000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 40000, 'rate' => 23, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 80000, 'rate' => 27, 'fixed_deduction' => 0],
            ['min' => 80001, 'max' => 160000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 160001, 'max' => 320000, 'rate' => 33, 'fixed_deduction' => 0],
            ['min' => 320001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $tax = $this->calculateProgressiveTax($grossTaxable, $this->taxSlabs());

        $annualTax = $tax * $annualBasis;
        $abatement = min(max($annualTax * 0.40, 12000), 18000);
        $finalAnnualTax = max(0, $annualTax - $abatement);

        return round($finalAnnualTax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        // ZONE-INFRA (#1820): routed through computeContribution() so the
        // statutory cap (none for DZ today) is applied consistently and a
        // future CNAS cap change only touches the defaults/DB rows.
        return [
            'employee' => $this->computeContribution($grossSalary, 'CNAS_EMP', 9.0, null),
            'employer' => $this->computeContribution($grossSalary, 'CNAS_PAT', 26.0, null),
        ];
    }

    public function timezone(): string
    {
        return 'Africa/Algiers';
    }

    /**
     * PA2-COUNTRY-004: Algerian labor code (loi 90-11 art. 27 modifiee) sets a
     * weekend of Friday + Saturday as the standard legal weekly rest for most
     * sectors (moved from the historical Thursday/Friday weekend by decret
     * 2009 for public administration, since generalized in practice).
     *
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        return [5, 6];
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
        return 'DZ fixed public holidays (seed PublicHolidaySeeder, issue #2255): 1er jan, 1er mai, 5 juil, 1er nov + mobiles islamiques (Aïd el-Fitr, Aïd el-Adha, Aïd el-Mawlid, 1er Moharrem) via calendrier islamique #1812 — PA2-COUNTRY-012.';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006: matches App\Support\CountryDefaults::DEFAULTS['DZ'].
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-004: standard Algerian legal weekly working-hours threshold
     * (loi 90-11 art. 26 : duree legale hebdomadaire de 40 heures pour un
     * horaire normal ; au-dela = heures supplementaires).
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * PA2-COUNTRY-004 (arbitré par l'issue #5266 — écart E2 de la spec
     * `payroll-dz-100`) : loi 90-11 art. 32 (version consolidée JORA — le
     * référentiel du repo citait « art. 33 », référence historique de la
     * version amendée) : les heures supplémentaires donnent lieu à une
     * majoration « qui ne peut en aucun cas être inférieure à 50 % du
     * salaire horaire normal ». Aucun barème 25 %/50 %/100 % dans le texte
     * général (contrairement à la France) — le palier 25 % jusqu'à 10 h/mois
     * de l'ancien §5 de DZ_COMPLIANCE était un usage conventionnel non
     * confirmé. Modélisé comme un palier unique illimité × 1,5, désormais
     * CONSOMMÉ par PayrollCalculator::computeOvertimePay() (#5266) pour tout
     * run de paie DZ (DoD : les HS sont intégrées sans intervention
     * manuelle). Loi 90-11 art. 36 : le travail un jour de repos légal
     * ouvre droit à un repos compensateur d'égale durée en plus de la
     * majoration — règle documentée (DZ_COMPLIANCE §5), le suivi du repos
     * compensateur reste un acte RH hors moteur de paie.
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ];
    }

    /**
     * FOCUS 2 (F-31) — Délai de préavis légal (Loi 90-11).
     *
     * Valeur pilote : 0 jour — le préavis réel est fixé par le contrat /
     * son exécution (comportement historique EndOfContractService, F-08).
     * Les durées légales candidates (Loi 90-11 art. 98 : 1 mois pour une
     * ancienneté < 10 ans, 2 mois au-delà) sont documentées dans
     * docs/payroll/DZ_COMPLIANCE.md §7 mais NON verrouillées tant qu'un
     * expert comptable DZ ne les a pas validées (confidenceLevel='pilot').
     */
    /**
     * Issue #1819/#1943 — Préavis DZ (délai-congé de licenciement).
     *
     * La Loi n° 90-11 du 21/04/1990 ne fixe PAS de durée légale ferme : elle
     * renvoie aux conventions collectives / au règlement intérieur (pendant le
     * délai-congé, le travailleur dispose de 2 h/jour cumulables et rémunérées
     * pour rechercher un emploi, art. 73-4). L'usage dominant en pratique
     * algérienne, retenu ici comme valeur par défaut paramétrable :
     *   1 mois si ancienneté < 10 ans, 2 mois si ≥ 10 ans.
     *
     * #1943 (revue lead) — unité : l'indemnité compensatrice de préavis =
     * rémunération de la période de préavis (Loi 90-11 art. 98). Le moteur
     * paie `base × noticeDays / workingDays` avec workingDays = jours OUVRÉS
     * (22). Renvoyer des jours CALENDAIRES (30/60) surpaierait ~36 %
     * (30/22 = 1,36× le salaire mensuel). On renvoie donc des jours OUVRÉS :
     *   1 mois ≈ 22 j ouvrés, 2 mois ≈ 44 j ouvrés → base × 22/22 = 1 mois
     *   exact. (cf. docs/payroll/DZ_COMPLIANCE.md §7 — confidenceLevel reste
     *   'pilot', validation expert comptable DZ requise avant 'production'.)
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return $yearsOfService >= 10.0 ? 44.0 : 22.0;
    }

    /**
     * FOCUS 2 (F-31) — Indemnité de licenciement / d'ancienneté (Loi 90-11).
     *
     * 1 mois de salaire par année d'ancienneté (valeur historique F-08,
     * dorénavant portée par les règles pays). Le plafond légal n'est pas
     * appliqué ici — à paramétrer par entreprise / à valider comptable.
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 1.0;
    }
}
