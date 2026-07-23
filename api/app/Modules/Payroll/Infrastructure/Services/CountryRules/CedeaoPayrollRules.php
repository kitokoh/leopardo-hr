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
        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNSS_CEDEAO_EMP', 'type' => 'employee', 'rate' => 3.6, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (retraite/famille/AT)', 'code' => 'CNSS_CEDEAO_PAT', 'type' => 'employer', 'rate' => 16.4, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
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
        return [
            'employee' => round($grossSalary * 0.036, 2),
            'employer' => round($grossSalary * 0.164, 2),
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
        return 'placeholder';
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
