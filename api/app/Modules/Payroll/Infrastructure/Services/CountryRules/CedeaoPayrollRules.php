<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

/**
 * CEDEAO/UEMOA zone (Communaute Economique des Etats de l'Afrique de
 * l'Ouest / Union Economique et Monetaire Ouest-Africaine): this class
 * covers the UEMOA member states that already share the XOF currency —
 * Cote d'Ivoire (CI), Mali (ML), Burkina Faso (BF), Benin (BJ), Togo (TG),
 * Niger (NE). Senegal (SN) is also a UEMOA/XOF member but already has its
 * own dedicated SenegalPayrollRules class delivered earlier, so it is
 * intentionally excluded from MEMBER_COUNTRY_CODES here to avoid two
 * competing rule sets for the same country code (PA2-COUNTRY-008).
 *
 * Per the scope doc (`05_SCOPE_PAYS_PAIE_POINTAGE.md`), CEDEAO support is
 * delivered first for the XOF-denominated members above; the non-XOF
 * ECOWAS members (Nigeria/NGN, Ghana/GHS, Cape Verde/CVE, Gambia/GMD,
 * Guinea/GNF, Liberia/LRD, Sierra Leone/SLL) are an explicit future
 * extension, not covered by this class.
 *
 * `country_code` columns (tax_slabs, social_contributions, payroll_runs...)
 * are `varchar(2)`, so the zone-wide label "CEDEAO" is never persisted as a
 * country code: every usable instance is scoped to one of
 * MEMBER_COUNTRY_CODES via the constructor or forMemberCountry(), and
 * countryCode() returns that ISO 3166-1 alpha-2 code. Cote d'Ivoire (CI) is
 * the representative default when no member state is specified, matching
 * the scope doc's "Africa/Abidjan" zone-wide default timezone.
 */
class CedeaoPayrollRules extends AbstractCountryRules
{
    /**
     * ISO 3166-1 alpha-2 codes of the UEMOA/XOF CEDEAO member states this
     * class supports via forMemberCountry()/the constructor. Senegal (SN)
     * is deliberately excluded — see class docblock.
     */
    public const MEMBER_COUNTRY_CODES = ['CI', 'ML', 'BF', 'BJ', 'TG', 'NE'];

    protected string $memberCountryCode;

    public function __construct(string $memberCountryCode = 'CI')
    {
        $normalized = strtoupper(trim($memberCountryCode));
        $this->memberCountryCode = in_array($normalized, self::MEMBER_COUNTRY_CODES, true) ? $normalized : 'CI';
    }

    /**
     * Returns a clone scoped to a specific CEDEAO/UEMOA member state, so
     * callers that know the precise country (e.g. company provisioning)
     * get the member's minimum wage instead of the Cote d'Ivoire default.
     */
    public function forMemberCountry(string $memberCountryCode): static
    {
        $clone = clone $this;
        $normalized = strtoupper(trim($memberCountryCode));
        $clone->memberCountryCode = in_array($normalized, self::MEMBER_COUNTRY_CODES, true) ? $normalized : $clone->memberCountryCode;

        return $clone;
    }

    public function countryCode(): string
    {
        return $this->memberCountryCode;
    }

    public function currency(): string
    {
        // All supported members share the CFA franc BCEAO (XOF). Non-XOF
        // ECOWAS members are an explicit future extension (see docblock).
        return 'XOF';
    }

    public function minimumWage(): float
    {
        // SMIG (Salaire Minimum Interprofessionnel Garanti) per member
        // state, most recent published figures. Placeholder-grade: not
        // locally legally validated, see confidenceLevel() below.
        return match ($this->memberCountryCode) {
            'ML' => 40000.0,
            'BF' => 34664.0,
            'BJ' => 52000.0,
            'TG' => 35000.0,
            'NE' => 30047.0,
            default => 75000.0, // CI
        };
    }

    public function socialContributions(): array
    {
        if ($this->memberCountryCode === 'CI') {
            // CNSS Côte d'Ivoire (issue #1825) : retraite salarié 3,2 % +
            // retraite patronal 4,5 % + famille patronal 5,75 % plafonnés à
            // 1 647 315 XOF/mois, AT patronal 2,0 % non plafonné (taux pilote).
            return [
                ['name' => 'CNSS Retraite Salariale', 'code' => 'CNSS_CI_RET_EMP', 'type' => 'employee', 'rate' => 3.2, 'cap' => 1647315.0],
                ['name' => 'CNSS Retraite Patronale', 'code' => 'CNSS_CI_RET_PAT', 'type' => 'employer', 'rate' => 4.5, 'cap' => 1647315.0],
                ['name' => 'CNSS Prestations Familiales Patronale', 'code' => 'CNSS_CI_FAM_PAT', 'type' => 'employer', 'rate' => 5.75, 'cap' => 1647315.0],
                ['name' => 'CNSS Risques Professionnels Patronale', 'code' => 'CNSS_CI_AT_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
            ];
        }

        // Placeholder générique pour les autres membres UEMOA (ML, BF, BJ,
        // TG, NE) tant que leurs issues pays n'ont pas livré de taux légaux
        // validés (BF/ML : #1829).
        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNSS_CEDEAO_EMP', 'type' => 'employee', 'rate' => 3.6, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (retraite/famille/AT)', 'code' => 'CNSS_CEDEAO_PAT', 'type' => 'employer', 'rate' => 16.4, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        if ($this->memberCountryCode === 'CI') {
            // ITSAS Côte d'Ivoire (CGI 2024, art. 116-120) — tranches ANNUELLES
            // (cf. docs/payroll/CI_COMPLIANCE.md §1) : calculateIncomeTax()
            // annualise l'assiette mensuelle avant d'appliquer ce barème.
            return [
                ['min' => 0, 'max' => 600000, 'rate' => 0, 'fixed_deduction' => 0],
                ['min' => 600001, 'max' => 2000000, 'rate' => 2, 'fixed_deduction' => 0],
                ['min' => 2000001, 'max' => 5000000, 'rate' => 21, 'fixed_deduction' => 0],
                ['min' => 5000001, 'max' => 10000000, 'rate' => 24.5, 'fixed_deduction' => 0],
                ['min' => 10000001, 'max' => null, 'rate' => 29, 'fixed_deduction' => 0],
            ];
        }

        // Conservative placeholder progressive IGR-style schedule, common
        // shape across UEMOA members. confidenceLevel() below explicitly
        // marks this as 'placeholder', not a legally validated figure per
        // member state.
        return [
            ['min' => 0, 'max' => 600000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 600001, 'max' => 1200000, 'rate' => 12, 'fixed_deduction' => 0],
            ['min' => 1200001, 'max' => 3000000, 'rate' => 22, 'fixed_deduction' => 0],
            ['min' => 3000001, 'max' => 6000000, 'rate' => 32, 'fixed_deduction' => 0],
            ['min' => 6000001, 'max' => null, 'rate' => 35, 'fixed_deduction' => 0],
        ];
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12): float
    {
        $annualTaxable = $grossTaxable * $annualBasis;
        $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

        if ($this->memberCountryCode === 'CI') {
            // CI (issue #1825) : calculateIncomeTax() ne retourne que l'ITSAS
            // (CGI 2024 art. 116-120) ; la Contribution Nationale (1,5 % sur
            // le brut > 50 000) est calculée séparément par
            // calculateBracketTax() sur le BRUT réel, puis additionnée dans
            // le bulletin via la ligne « Taxe de minimum fiscal »
            // (docs/payroll/CI_COMPLIANCE.md §2-§3). Impôt total mensuel =
            // ITSAS + CN.
            return round($tax / $annualBasis, 2);
        }

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        if ($this->memberCountryCode === 'CI') {
            // CNSS Côte d'Ivoire (issue #1825) : retraite 3,2 % salarié et
            // 4,5 % patronal + famille 5,75 % patronal plafonnés à
            // 1 647 315 XOF/mois, AT 2,0 % patronal non plafonné
            // (docs/payroll/CI_COMPLIANCE.md §4).
            return [
                'employee' => $this->computeContribution($grossSalary, 'CNSS_CI_RET_EMP', 3.2, 1647315.0),
                'employer' => round(
                    $this->computeContribution($grossSalary, 'CNSS_CI_RET_PAT', 4.5, 1647315.0)
                    + $this->computeContribution($grossSalary, 'CNSS_CI_FAM_PAT', 5.75, 1647315.0)
                    + $this->computeContribution($grossSalary, 'CNSS_CI_AT_PAT', 2.0, null),
                    2
                ),
            ];
        }

        $employeeRate = $this->resolveContributionRate('CNSS_CEDEAO_EMP', 3.6);
        $employerRate = $this->resolveContributionRate('CNSS_CEDEAO_PAT', 16.4);

        return [
            'employee' => round($grossSalary * $employeeRate / 100, 2),
            'employer' => round($grossSalary * $employerRate / 100, 2),
        ];
    }

    public function timezone(): string
    {
        // All UEMOA members observe UTC (GMT) year-round.
        return match ($this->memberCountryCode) {
            'ML' => 'Africa/Bamako',
            'BF' => 'Africa/Ouagadougou',
            'BJ' => 'Africa/Porto-Novo',
            'TG' => 'Africa/Lome',
            'NE' => 'Africa/Niamey',
            default => 'Africa/Abidjan', // CI
        };
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day across all UEMOA members.
        return [7];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPayCycles(): array
    {
        return ['monthly'];
    }

    public function publicHolidaysSource(): string
    {
        return 'placeholder: no official CEDEAO/UEMOA member-state public-holiday calendar wired in yet; '.
            'national/religious holidays must be entered manually per company '.
            'until PA2-COUNTRY-012 delivers a real source.';
    }

    public function confidenceLevel(): string
    {
        // Côte d'Ivoire (CI) : ITSAS/CN/CNSS implémentés depuis les sources
        // légales publiques (CGI 2024 art. 116-120, CNSS) — niveau 'pilot'
        // (issue #1825) tant qu'un expert-comptable OHADA-CI n'a pas validé
        // les chiffres. Les autres membres UEMOA restent 'placeholder'
        // jusqu'à leurs issues pays (BF/ML : #1829).
        return $this->memberCountryCode === 'CI' ? 'pilot' : 'placeholder';
    }

    /**
     * ZONE-INFRA (#1820/#1825) : abattement frais professionnels ivoirien
     * (CGI 2024, art. 116) — 20 % du brut, NON plafonné. Appliqué par
     * PayrollCalculator::calculateSlip() sur l'assiette imposable.
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        if ($this->memberCountryCode === 'CI') {
            return ['rate' => 20.0, 'cap' => null];
        }

        return parent::professionalExpensesDeduction();
    }

    /**
     * ZONE-INFRA (#1820/#1825) : Contribution Nationale ivoirienne (CGI 2024
     * art. 116-120) — 1,5 % sur la part du BRUT mensuel excédant 50 000 XOF
     * (seuil annuel 600 000 XOF) :
     *   CN mensuelle = max(0, brut − 50 000) × 0,015.
     * Calculée séparément de l'ITSAS (docs/payroll/CI_COMPLIANCE.md §3) et
     * portée dans le bulletin par PayrollCalculator (ligne « Taxe de minimum
     * fiscal ») — impôt total mensuel = ITSAS + CN.
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        if ($this->memberCountryCode === 'CI') {
            return round(max(0.0, $grossSalary - 50000.0) * 0.015, 2);
        }

        return parent::calculateBracketTax($grossSalary);
    }

    /**
     * Issue #1825 : le 13ème mois est une pratique généralisée en Côte
     * d'Ivoire via les conventions de branche (obligatoire dans la plupart)
     * — thirteenthMonthMandatory() = true pour CI. PayrollCalculator
     * l'injecte en ligne earning du mois de décembre (mécanisme ZONE-INFRA
     * #1820, traitement 'fully_taxable').
     */
    public function thirteenthMonthMandatory(): bool
    {
        return $this->memberCountryCode === 'CI';
    }

    /**
     * Préavis ivoirien (Code du travail, art. 18) — issue #1825,
     * docs/payroll/CI_COMPLIANCE.md §8. L'interface n'expose que
     * l'ancienneté (pas la catégorie) : paliers implémentés au niveau
     * employé/technicien (le plus courant) — < 5 ans : 1 mois ; ≥ 5 ans :
     * 2 mois. Ouvriers (8/15 j) et cadres (3 mois) documentés dans le
     * référentiel ; la prise en compte de la catégorie du contrat est un
     * suivi.
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        if ($this->memberCountryCode === 'CI') {
            return $yearsOfService < 5.0 ? 30.0 : 60.0;
        }

        return parent::noticePeriodDays($yearsOfService);
    }

    /**
     * PA2-COUNTRY-006 follow-up: matches App\Support\CountryDefaults for
     * all six CEDEAO/UEMOA member codes (CI/ML/BF/BJ/TG/NE), all
     * French-speaking.
     */
    public function language(): string
    {
        return 'fr';
    }

    /**
     * PA2-COUNTRY-008 baseline: UEMOA labor codes generally set the legal
     * weekly working-hours threshold at 40 hours/week for non-agricultural
     * sectors, consistent across the supported member states. Placeholder-
     * grade, see confidenceLevel().
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return 40.0;
    }

    /**
     * PA2-COUNTRY-008 baseline: UEMOA labor codes commonly majorent les
     * heures supplementaires par paliers (+15% pour les 8 premieres heures
     * hebdomadaires, +35% au-dela), modelise ici comme un palier a 2
     * niveaux commun a tous les membres supportes, a titre placeholder
     * (confidenceLevel() = 'placeholder').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    /**
     * Issue #1825 : heures supplémentaires ivoiriennes (Code du travail,
     * art. 21) — 40-48 h : +15 % ; 48-54 h : +35 % ; nuit/dimanche > 54 h :
     * +50 % (paliers hebdomadaires modélisés en largeurs 8 h / 14 h). Les
     * autres membres UEMOA conservent le palier placeholder 1.15/1.35
     * commun (confidenceLevel() = 'placeholder').
     *
     * @return array<int, array{up_to_hours: float|null, multiplier: float}>
     */
    public function overtimeRateTiers(): array
    {
        if ($this->memberCountryCode === 'CI') {
            return [
                ['up_to_hours' => 8.0, 'multiplier' => 1.15],
                ['up_to_hours' => 14.0, 'multiplier' => 1.35],
                ['up_to_hours' => null, 'multiplier' => 1.50],
            ];
        }

        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.35],
        ];
    }
}
