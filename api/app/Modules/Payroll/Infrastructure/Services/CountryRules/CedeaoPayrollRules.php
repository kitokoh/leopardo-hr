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
        if ($this->memberCountryCode === 'BF') {
            // CNSS Burkina Faso (issue #1829) : retraite salarié 5,5 % +
            // retraite patronal 6,5 % + famille patronal 7,0 % plafonnés à
            // 900 000 XOF/mois, AT patronal 3,5 % non plafonné (taux pilote).
            return [
                ['name' => 'CNSS Retraite Salariale', 'code' => 'CNSS_BF_RET_EMP', 'type' => 'employee', 'rate' => 5.5, 'cap' => 900000.0],
                ['name' => 'CNSS Retraite Patronale', 'code' => 'CNSS_BF_RET_PAT', 'type' => 'employer', 'rate' => 6.5, 'cap' => 900000.0],
                ['name' => 'CNSS Prestations Familiales Patronale', 'code' => 'CNSS_BF_FAM_PAT', 'type' => 'employer', 'rate' => 7.0, 'cap' => 900000.0],
                ['name' => 'CNSS Risques Professionnels Patronale', 'code' => 'CNSS_BF_AT_PAT', 'type' => 'employer', 'rate' => 3.5, 'cap' => null],
            ];
        }

        if ($this->memberCountryCode === 'ML') {
            // INPS Mali (issue #1829) : retraite salarié 3,6 % + retraite
            // patronal 7,4 % plafonnés à 3 000 000 XOF/mois, famille patronal
            // 4,0 % et AT patronal 2,0 % non plafonnés (taux pilote).
            return [
                ['name' => 'INPS Retraite Salariale', 'code' => 'INPS_ML_RET_EMP', 'type' => 'employee', 'rate' => 3.6, 'cap' => 3000000.0],
                ['name' => 'INPS Retraite Patronale', 'code' => 'INPS_ML_RET_PAT', 'type' => 'employer', 'rate' => 7.4, 'cap' => 3000000.0],
                ['name' => 'INPS Prestations Familiales Patronale', 'code' => 'INPS_ML_FAM_PAT', 'type' => 'employer', 'rate' => 4.0, 'cap' => null],
                ['name' => 'INPS Risques Professionnels Patronale', 'code' => 'INPS_ML_AT_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
            ];
        }

        // Placeholder générique pour les autres membres UEMOA (CI, BJ, TG,
        // NE) tant que leurs issues pays n'ont pas livré de taux légaux
        // validés (CI : #1825).
        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNSS_CEDEAO_EMP', 'type' => 'employee', 'rate' => 3.6, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (retraite/famille/AT)', 'code' => 'CNSS_CEDEAO_PAT', 'type' => 'employer', 'rate' => 16.4, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        if ($this->memberCountryCode === 'BF') {
            // IUTS Burkina Faso (CGI 2024) — tranches ANNUELLES, taux « tout
            // compris » (contribution communale incluse) — issue #1829,
            // docs/payroll/BF_COMPLIANCE.md §1.
            return [
                ['min' => 0, 'max' => 600000, 'rate' => 0, 'fixed_deduction' => 0],
                ['min' => 600001, 'max' => 1500000, 'rate' => 12.1, 'fixed_deduction' => 0],
                ['min' => 1500001, 'max' => 3000000, 'rate' => 13.9, 'fixed_deduction' => 0],
                ['min' => 3000001, 'max' => 4500000, 'rate' => 18.7, 'fixed_deduction' => 0],
                ['min' => 4500001, 'max' => null, 'rate' => 23.6, 'fixed_deduction' => 0],
            ];
        }

        if ($this->memberCountryCode === 'ML') {
            // ITS Mali (CGI 2024) — tranches ANNUELLES — issue #1829,
            // docs/payroll/ML_COMPLIANCE.md §1.
            return [
                ['min' => 0, 'max' => 540000, 'rate' => 0, 'fixed_deduction' => 0],
                ['min' => 540001, 'max' => 1320000, 'rate' => 5, 'fixed_deduction' => 0],
                ['min' => 1320001, 'max' => 2040000, 'rate' => 10, 'fixed_deduction' => 0],
                ['min' => 2040001, 'max' => 3480000, 'rate' => 15, 'fixed_deduction' => 0],
                ['min' => 3480001, 'max' => 6360000, 'rate' => 20, 'fixed_deduction' => 0],
                ['min' => 6360001, 'max' => null, 'rate' => 30, 'fixed_deduction' => 0],
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

        return round($tax / $annualBasis, 2);
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        if ($this->memberCountryCode === 'BF') {
            // CNSS Burkina Faso (issue #1829) : retraite salariale 5,5 % +
            // patronale 6,5 % + famille 7,0 % plafonnées à 900 000 XOF/mois,
            // AT 3,5 % non plafonné (docs/payroll/BF_COMPLIANCE.md §3).
            return [
                'employee' => $this->computeContribution($grossSalary, 'CNSS_BF_RET_EMP', 5.5, 900000.0),
                'employer' => round(
                    $this->computeContribution($grossSalary, 'CNSS_BF_RET_PAT', 6.5, 900000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_BF_FAM_PAT', 7.0, 900000.0)
                    + $this->computeContribution($grossSalary, 'CNSS_BF_AT_PAT', 3.5, null),
                    2
                ),
            ];
        }

        if ($this->memberCountryCode === 'ML') {
            // INPS Mali (issue #1829) : retraite salariale 3,6 % + patronale
            // 7,4 % plafonnées à 3 000 000 XOF/mois, famille 4,0 % + AT 2,0 %
            // non plafonnés (docs/payroll/ML_COMPLIANCE.md §3).
            return [
                'employee' => $this->computeContribution($grossSalary, 'INPS_ML_RET_EMP', 3.6, 3000000.0),
                'employer' => round(
                    $this->computeContribution($grossSalary, 'INPS_ML_RET_PAT', 7.4, 3000000.0)
                    + $this->computeContribution($grossSalary, 'INPS_ML_FAM_PAT', 4.0, null)
                    + $this->computeContribution($grossSalary, 'INPS_ML_AT_PAT', 2.0, null),
                    2
                ),
            ];
        }

        // ZONE-INFRA (#1820): Côte d'Ivoire (CI) statutory CNSS ceiling
        // 1 647 315 XOF/month is applied via computeContribution(); the
        // other CEDEAO members stay on the placeholder (uncapped) rates
        // until their own member-state issues land.
        $cap = $this->memberCountryCode === 'CI' ? 1647315.0 : null;

        return [
            'employee' => $this->computeContribution($grossSalary, 'CNSS_CEDEAO_EMP', 3.6, $cap),
            'employer' => $this->computeContribution($grossSalary, 'CNSS_CEDEAO_PAT', 16.4, $cap),
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
        // Burkina Faso (BF) et Mali (ML) : IUTS/ITS + CNSS/INPS implémentés
        // depuis les sources légales publiques (CGI 2024) — niveau 'pilot'
        // (issue #1829) tant qu'un expert-comptable local n'a pas validé les
        // chiffres. Les autres membres UEMOA restent 'placeholder' jusqu'à
        // leurs issues pays (CI : #1825).
        return in_array($this->memberCountryCode, ['BF', 'ML'], true) ? 'pilot' : 'placeholder';
    }

    /**
     * Préavis OHADA (issue #1829) — niveau employé/technicien (1 mois) :
     * ouvriers (8 jours) et cadres (3 mois) documentés dans
     * BF_COMPLIANCE.md §7 / ML_COMPLIANCE.md §7 — la catégorie du contrat
     * sera prise en compte dans un suivi.
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        if (in_array($this->memberCountryCode, ['BF', 'ML'], true)) {
            return 30.0;
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
    public function overtimeRateTiers(): array
    {
        return [
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.35],
        ];
    }
}
