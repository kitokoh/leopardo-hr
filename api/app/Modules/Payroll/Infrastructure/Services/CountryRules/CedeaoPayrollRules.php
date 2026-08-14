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
        // CI (#1825) : taux CNSS légaux (CGI CI / Code de la sécurité
        // sociale ivoirien) — retraite 3,2 % salarié + 4,5 % patronal,
        // famille 5,75 % patronal (plafond 1 647 315 XOF/mois), AT 2,0 %
        // patronal (non plafonné, taux pilote variable selon le risque).
        if ($this->memberCountryCode === 'CI') {
            return [
                ['name' => 'CNSS Retraite Salariale (CI)', 'code' => 'CNSS_CI_RET_EMP', 'type' => 'employee', 'rate' => 3.2, 'cap' => 1647315.0],
                ['name' => 'CNSS Retraite Patronale (CI)', 'code' => 'CNSS_CI_RET_PAT', 'type' => 'employer', 'rate' => 4.5, 'cap' => 1647315.0],
                ['name' => 'CNSS Famille Patronale (CI)', 'code' => 'CNSS_CI_FAM_PAT', 'type' => 'employer', 'rate' => 5.75, 'cap' => 1647315.0],
                ['name' => 'CNSS AT Patronale (CI)', 'code' => 'CNSS_CI_AT_PAT', 'type' => 'employer', 'rate' => 2.0, 'cap' => null],
            ];
        }

        return [
            ['name' => 'CNPS/CNSS Salariale', 'code' => 'CNSS_CEDEAO_EMP', 'type' => 'employee', 'rate' => 3.6, 'cap' => null],
            ['name' => 'CNPS/CNSS Patronale (retraite/famille/AT)', 'code' => 'CNSS_CEDEAO_PAT', 'type' => 'employer', 'rate' => 16.4, 'cap' => null],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // CI (#1825) : ITSAS Côte d'Ivoire (CGI CI art. 116-120) — tranches
        // ANNUELLES : 0–600 000 XOF : 0 % · 600 001–2 000 000 : 2 % ·
        // 2 000 001–5 000 000 : 21 % · 5 000 001–10 000 000 : 24,5 % ·
        // > 10 000 000 : 29 % (valeurs à valider par expert-comptable —
        // confidenceLevel 'pilot').
        if ($this->memberCountryCode === 'CI') {
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
        if ($this->memberCountryCode !== 'CI') {
            $annualTaxable = $grossTaxable * $annualBasis;
            $tax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs());

            return round($tax / $annualBasis, 2);
        }

        // CI (#1825, CGI CI art. 116-120) :
        //   1. Assiette = brut − CNSS salariale − abattement frais pro
        //      (20 % du brut, non plafonné). Le moteur passe déjà
        //      brut − CNSS salariale ; l'abattement est appliqué sur cette
        //      base (≈ 19,4 % du brut — approximation pilot documentée
        //      CI_COMPLIANCE.md §1, à valider par expert-comptable).
        //   2. ITSAS progressif annuel / 12 (5 tranches).
        // La Contribution Nationale (CN, 1,5 % sur la part du brut mensuel
        // > 50 000 XOF) est calculée séparément dans calculateBracketTax() ;
        // les deux sont sommées sur le bulletin (impôt total mensuel).
        $abatement = $this->professionalExpensesDeduction();
        $monthlyDeduction = min(
            $grossTaxable * ($abatement['rate'] / 100),
            $abatement['cap'] ?? PHP_FLOAT_MAX
        );

        $annualTaxable = max(0.0, $grossTaxable - $monthlyDeduction) * $annualBasis;
        $monthlyTax = $this->calculateProgressiveTax($annualTaxable, $this->taxSlabs()) / $annualBasis;

        return round($monthlyTax, 2);
    }

    /**
     * CI (#1825) : Contribution Nationale (CN, CGI CI) — 1,5 % sur la part
     * du brut mensuel supérieure à 50 000 XOF (seuil annuel 600 000 XOF).
     * Implémentée via le mécanisme « taxe forfaitaire » du moteur
     * (calculateBracketTax + ligne de déduction dédiée) car elle est assise
     * sur le BRUT, pas sur l'assiette ITSAS ; le libellé de ligne est
     * surchargé via flatPayrollTaxLabel().
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        if ($this->memberCountryCode === 'CI') {
            return round(max(0.0, $grossSalary - 50000.0) * 1.5 / 100, 2);
        }

        return parent::calculateBracketTax($grossSalary);
    }

    /**
     * CI (#1825) : la ligne de déduction forfaitaire du moteur porte le
     * libellé « Contribution Nationale (CN) » pour la Côte d'Ivoire (les
     * autres pays gardent « Taxe de minimum fiscal », ex. TRIMF SN).
     */
    public function flatPayrollTaxLabel(): string
    {
        return $this->memberCountryCode === 'CI'
            ? 'Contribution Nationale (CN)'
            : parent::flatPayrollTaxLabel();
    }

    /**
     * CI (#1825) : abattement frais professionnels 20 % du brut, non
     * plafonné (CGI CI). Les autres membres CEDEAO n'en ont pas (défaut 0 %).
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

    public function calculateSocialCharges(float $grossSalary): array
    {
        // CI (#1825) : CNSS avec plafond statutaire 1 647 315 XOF/mois sur la
        // retraite et la famille ; l'AT (2 %) n'est pas plafonné. Les autres
        // cinq membres CEDEAO restent sur le placeholder (non plafonné)
        // jusqu'à leurs propres issues (BF/ML : #1829).
        if ($this->memberCountryCode === 'CI') {
            $cap = 1647315.0;

            return [
                'employee' => $this->computeContribution($grossSalary, 'CNSS_CI_RET_EMP', 3.2, $cap),
                // Somme arrondie à 2 décimales : chaque cotisation est déjà
                // arrondie individuellement et l'addition de flottants peut
                // dériver (ex. 74 129,18 + 94 720,61 + 60 000 → ...9799).
                'employer' => round(
                    $this->computeContribution($grossSalary, 'CNSS_CI_RET_PAT', 4.5, $cap)
                        + $this->computeContribution($grossSalary, 'CNSS_CI_FAM_PAT', 5.75, $cap)
                        + $this->computeContribution($grossSalary, 'CNSS_CI_AT_PAT', 2.0, null),
                    2
                ),
            ];
        }

        // ZONE-INFRA (#1820): Côte d'Ivoire (CI) statutory CNSS ceiling
        // 1 647 315 XOF/month is applied via computeContribution(); the
        // other five CEDEAO members stay on the placeholder (uncapped)
        // rates until their own member-state issues land (BF/ML: #1829).
        $cap = $this->memberCountryCode === 'CI' ? 1647315.0 : null;

        return [
            'employee' => $this->computeContribution($grossSalary, 'CNSS_CEDEAO_EMP', 3.6, $cap),
            'employer' => $this->computeContribution($grossSalary, 'CNSS_CEDEAO_PAT', 16.4, $cap),
        ];
    }

    /**
     * CI (#1825) : préavis légal (Code du travail CI art. 18) — la matrice
     * complète distingue ouvriers (< 5 ans : 8 j ; ≥ 5 ans : 15 j),
     * employés/techniciens (< 5 ans : 1 mois ; ≥ 5 ans : 2 mois) et cadres
     * (3 mois). Le moteur ne transmet pas la catégorie à
     * noticePeriodDays() : approximation pilote sur l'ancienneté seule
     * (palier employé/technicien), matrice complète documentée
     * CI_COMPLIANCE.md §6 — à valider par expert-comptable OHADA-CI.
     */
    public function noticePeriodDays(float $yearsOfService): float
    {
        if ($this->memberCountryCode !== 'CI') {
            return parent::noticePeriodDays($yearsOfService);
        }

        return match (true) {
            $yearsOfService < 5.0 => 30.0,
            $yearsOfService < 10.0 => 60.0,
            default => 90.0,
        };
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
        if ($this->memberCountryCode === 'CI') {
            return 'CI fixed public holidays: 1er jan, lundi de Pâques, 1er mai, Ascension, lundi de Pentecôte, 7 août, 15 août, 1er nov, 15 nov, 25 déc (CI_COMPLIANCE.md §7) + mobile Islamic holidays (Aïd el-Fitr, Aïd el-Adha, Maouloud) — Islamic calendar wiring pending.';
        }

        return 'placeholder: no official CEDEAO/UEMOA member-state public-holiday calendar wired in yet; '.
            'national/religious holidays must be entered manually per company '.
            'until PA2-COUNTRY-012 delivers a real source.';
    }

    public function confidenceLevel(): string
    {
        // CI passe en 'pilot' (#1825) : barèmes ITSAS CGI 2024 + CNSS + CN +
        // préavis Code du travail implémentés, à valider par un
        // expert-comptable ivoirien avant passage en 'production'.
        return $this->memberCountryCode === 'CI' ? 'pilot' : 'placeholder';
    }

    /**
     * CI (#1825) : le 13ème mois est une pratique généralisée via les
     * conventions de branche (obligatoire dans la plupart des branches,
     * convention OHADA-CI) — versé en décembre, entièrement imposable.
     */
    public function thirteenthMonthMandatory(): bool
    {
        return $this->memberCountryCode === 'CI';
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
     * CI (#1825) : Code du travail CI art. 21 — paliers légaux
     *   40–48 h/sem : +15 % (8 premières heures HS)
     *   48–54 h/sem : +35 % (heures HS 9 à 14)
     *   > 54 h/sem ou nuit/dimanche : +50 % (au-delà de 14 h HS).
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
