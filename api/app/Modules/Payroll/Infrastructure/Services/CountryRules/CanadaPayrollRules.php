<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

use App\Core\Tenant\Domain\Models\Company;

/**
 * Canada (CA): unlike CemacPayrollRules/CedeaoPayrollRules, Canada is a
 * single ISO 3166-1 alpha-2 country code, so countryCode() always returns
 * 'CA' regardless of province — the province is an *optional* refinement
 * (PA2-COUNTRY-009 acceptance criteria: "CAD province optionnelle timezone
 * placeholders overtime provinciaux"), not a separate persisted country
 * code. Provincial/territorial employment-standards legislation in Canada
 * differs mainly on: timezone (provinces span 6 IANA zones) and the
 * statutory weekly overtime threshold (federal Canada Labour Code default
 * is 44h/week; several provinces set a lower 40h/week threshold instead).
 *
 * Audit légal 2026-08-24 (pack EN #5255, audit complémentaire — sources
 * CRA/Canada.ca) :
 *  - Barème fédéral IR 2026 : 14 % (taux le plus bas réduit de 15 % →
 *    14 % au 1er juillet 2025, plein effet 2026) jusqu'à $58 523, puis
 *    20,5 % ≤ $117 045, 26 % ≤ $181 440, 29 % ≤ $258 482, 33 % au-delà.
 *  - Basic Personal Amount 2026 : $16 452 (revenu ≤ $181 440), élimination
 *    progressive entre $181 440 et $258 482 jusqu'à $14 829 — appliqué en
 *    crédit non remboursable (BPA × 14 %).
 *  - CPP 2026 : 5,95 % (sal. et pat.) sur le YMPE $74 600 (exemption de
 *    base $3 500) ; CPP2 : 4 % entre $74 600 et le YAMPE $85 000.
 *  - EI 2026 : 1,63 % salarial (1,4× = 2,282 % patronal) sur la MIE
 *    $68 900.
 *  - Salaire minimum FÉDÉRAL : $18,15/h (1er avril 2026) — les provinces
 *    ont leurs propres minimums (souvent plus élevés), non modélisés.
 *  - Canada Labour Code : 44 h/semaine (fédéral) ; provinces 40-48 h.
 *    Préavis fédéral : 1-8 semaines selon l'ancienneté (CLC art. 230).
 *
 * Lot BC-07 #7933 (2026-09-21) — impôt PROVINCIAL QC et ON + régimes
 * québécois (sources : Revenu Québec « Income Tax Rates » 2026, ministère des
 * Finances QC « Parameters of the Personal Income Tax System for 2026 »,
 * Retraite Québec (RRQ 2026), Revenu Québec (RQAP 2026), CEIC/EDSC (taux AE
 * 2026 et réduction RQAP), T4032-QC 2026 pour l'abattement fédéral) :
 *  - QC 2026 : 14 % ≤ $54 345, 19 % ≤ $108 680, 24 % ≤ $132 245, 25,75 %
 *    au-delà ; crédit personnel de base $18 952 × 14 %. Abattement du Québec :
 *    −16,5 % de l'impôt fédéral de base (Loi sur les arrangements fiscaux).
 *  - ON 2026 : 5,05 % ≤ $53 891, 9,15 % ≤ $107 785, 11,16 % ≤ $150 000,
 *    12,16 % ≤ $220 000, 13,16 % au-delà ; crédit personnel de base $12 989 ×
 *    5,05 % ; SURTAXE : 20 % de l'impôt ontarien de base > $5 818 + 36 %
 *    > $7 446. (La contribution-santé de l'Ontario n'est PAS modélisée.)
 *  - Employé QC : RRQ/QPP 6,30 % (base 5,3 % + supplémentaire 1 %) sur
 *    (min(brut, YMPE) − exemption $3 500), RRQ2 4 % sur [YMPE, YAMPE] — à la
 *    place du RPC/CPP ; AE au taux RÉDUIT Québec 1,30 % (patronal 1,82 %) ;
 *    RQAP 0,430 % salarial / 0,602 % patronal sur le brut plafonné à
 *    $103 000/an.
 * Résolution de province EXPLICITE : forProvince() (code ISO 3166-2:CA) ou
 * override entreprise `company.metadata.payroll_ca_province` (lu par
 * forCompany()). Code de province inconnu → InvalidArgumentException — AUCUN
 * fallback silencieux. Sans province : comportement fédéral-seul documenté.
 */
class CanadaPayrollRules extends AbstractCountryRules
{
    /**
     * ISO 3166-2:CA province/territory subdivision codes this class
     * recognizes for forProvince()/the constructor. Passing null (or an
     * unrecognized code) keeps federal Canada Labour Code defaults, per
     * the "province optionnelle" acceptance criterion.
     */
    public const PROVINCE_CODES = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];

    /** YMPE 2026 (CPP — premier plafond annuel). */
    private const CPP_YMPE = 74600.0;

    /** YAMPE 2026 (CPP2 — second plafond annuel). */
    private const CPP_YAMPE = 85000.0;

    /** Exemption de base CPP 2026 (annuelle). */
    private const CPP_BASIC_EXEMPTION = 3500.0;

    /** Maximum insurable earnings EI 2026. */
    private const EI_MIE = 68900.0;

    /** BPA maximum 2026 (revenu ≤ $181 440). */
    private const BPA_MAX = 16452.0;

    /** BPA minimum 2026 (revenu ≥ $258 482). */
    private const BPA_MIN = 14829.0;

    /** Début du phase-out du BPA (2026). */
    private const BPA_PHASE_START = 181440.0;

    /** Fin du phase-out du BPA (2026). */
    private const BPA_PHASE_END = 258482.0;

    /** Clé metadata entreprise portant la province canadienne (ISO 3166-2:CA). */
    public const COMPANY_PROVINCE_METADATA_KEY = 'payroll_ca_province';

    /**
     * Abattement du Québec : −16,5 % de l'impôt fédéral de base pour un
     * résident du Québec (Loi sur les arrangements fiscaux entre le
     * gouvernement fédéral et les provinces ; confirmé « 16,5 % pour 2026 »
     * par T4032-QC 2026).
     */
    private const QC_FEDERAL_ABATEMENT_RATE = 0.165;

    /** Crédit personnel de base QC 2026 (ministère des Finances QC, nov. 2025). */
    private const QC_BPA = 18952.0;

    /** Taux du crédit QC = taux le plus bas du barème QC (14 % en 2026). */
    private const QC_CREDIT_RATE = 0.14;

    /** RRQ/QPP 2026 : 6,30 % salarial ET patronal (base 5,3 % + supplémentaire 1 %). */
    private const QPP_RATE = 6.30;

    /** RRQ2/QPP2 2026 : 4 % sur la tranche [YMPE $74 600, YAMPE $85 000]. */
    private const QPP2_RATE = 4.0;

    /** AE 2026 — taux RÉDUIT Québec (réduction RQAP 0,33 pt) : 1,30 % salarial. */
    private const EI_QC_EMPLOYEE_RATE = 1.30;

    /** AE 2026 — patronal Québec : 1,4 × 1,30 % = 1,82 %. */
    private const EI_QC_EMPLOYER_RATE = 1.82;

    /** RQAP 2026 : 0,430 % salarial. */
    private const QPIP_EMPLOYEE_RATE = 0.430;

    /** RQAP 2026 : 0,602 % patronal (1,4 × salarial). */
    private const QPIP_EMPLOYER_RATE = 0.602;

    /** Revenu maximal assurable RQAP 2026 (annuel). */
    private const QPIP_MIE = 103000.0;

    /** Crédit personnel de base Ontario 2026. */
    private const ON_BPA = 12989.0;

    /** Taux du crédit ON = taux le plus bas du barème ON (5,05 %). */
    private const ON_CREDIT_RATE = 0.0505;

    /** Surtaxe ON 2026 : 20 % de l'impôt ontarien de base au-delà de $5 818. */
    private const ON_SURTAX_THRESHOLD_1 = 5818.0;

    /** Surtaxe ON 2026 : +36 % de l'impôt ontarien de base au-delà de $7 446. */
    private const ON_SURTAX_THRESHOLD_2 = 7446.0;

    protected ?string $province = null;

    public function __construct(?string $province = null)
    {
        $this->province = $this->normalizeProvince($province);
    }

    /**
     * Returns a clone scoped to a specific province/territory, so callers
     * that know the employee/company's province get its timezone, statutory
     * overtime threshold AND (for QC/ON, #7933) provincial income tax and
     * Québec social schemes (QPP/QPP2, reduced EI, QPIP) instead of the
     * federal default. Pass null to reset to the federal (no-province)
     * default.
     *
     * #7933 — no silent fallback: an unrecognized province code throws
     * InvalidArgumentException instead of silently degrading to federal
     * rules (which would under-withhold provincial tax).
     */
    public function forProvince(?string $province): static
    {
        $clone = clone $this;
        $clone->province = $this->normalizeProvince($province);

        return $clone;
    }

    /**
     * #7933 — company-level province override: resolves the ISO 3166-2:CA
     * subdivision code from `company.metadata.payroll_ca_province` when the
     * rules are scoped to a company and no province was set explicitly.
     * An invalid metadata value throws (no silent fallback); a missing
     * value keeps the documented federal-only behaviour. Wrapped
     * defensively for non-booted-app contexts (pure unit tests), matching
     * resolveContributionRate().
     */
    public function forCompany(?string $companyId): static
    {
        $clone = parent::forCompany($companyId);

        if ($clone->province !== null || $companyId === null) {
            return $clone;
        }

        try {
            $metadata = Company::query()->find($companyId)?->metadata;
        } catch (\Throwable) {
            return $clone; // no booted app/DB (pure unit tests)
        }

        $province = is_array($metadata) ? ($metadata[self::COMPANY_PROVINCE_METADATA_KEY] ?? null) : null;

        if (is_string($province) && trim($province) !== '') {
            return $clone->forProvince($province); // throws on unknown code
        }

        return $clone;
    }

    private function normalizeProvince(?string $province): ?string
    {
        if ($province === null || trim($province) === '') {
            return null;
        }

        $normalized = strtoupper(trim($province));

        if (! in_array($normalized, self::PROVINCE_CODES, true)) {
            throw new \InvalidArgumentException(
                "Unknown Canadian province/territory code [{$province}] — expected one of: ".implode(', ', self::PROVINCE_CODES)
                .'. Refusing to silently fall back to federal-only payroll rules (#7933).'
            );
        }

        return $normalized;
    }

    public function countryCode(): string
    {
        return 'CA';
    }

    public function currency(): string
    {
        return 'CAD';
    }

    public function minimumWage(): float
    {
        // Salaire minimum FÉDÉRAL 2026 : $18,15/h × 173,33 h mensuelles
        // ≈ $3 145,94 → 3 146,00. Les minimums provinciaux (souvent plus
        // élevés, ex. BC/ON) ne sont pas modélisés (pilot).
        return 3146.0;
    }

    public function socialContributions(): array
    {
        // #7933 — employé QC : RRQ/QPP + RRQ2 (à la place du RPC/CPP), AE au
        // taux réduit Québec et RQAP (sources : Retraite Québec / Revenu
        // Québec / CEIC 2026 — docs/payroll/CA_COMPLIANCE.md §2bis).
        if ($this->province === 'QC') {
            return [
                // RRQ/QPP 2026 : 6,30 % chacun (base 5,3 % + supplémentaire 1 %)
                // sur l'assiette YMPE ($74 600/an → $6 216,67/mois), exemption
                // $3 500/an appliquée dans calculateSocialCharges.
                ['name' => 'RRQ/QPP salariale', 'code' => 'QPP_CA_EMP', 'type' => 'employee', 'rate' => self::QPP_RATE, 'cap' => 6216.67],
                ['name' => 'RRQ/QPP patronale', 'code' => 'QPP_CA_PAT', 'type' => 'employer', 'rate' => self::QPP_RATE, 'cap' => 6216.67],
                // RRQ2/QPP2 2026 : 4 % sur la tranche [$74 600, $85 000].
                ['name' => 'RRQ2/QPP2 salariale', 'code' => 'QPP2_CA_EMP', 'type' => 'employee', 'rate' => self::QPP2_RATE, 'cap' => 7083.33, 'floor' => 6216.67],
                ['name' => 'RRQ2/QPP2 patronale', 'code' => 'QPP2_CA_PAT', 'type' => 'employer', 'rate' => self::QPP2_RATE, 'cap' => 7083.33, 'floor' => 6216.67],
                // AE 2026 taux réduit QC (réduction RQAP 0,33 pt) : 1,30 % /
                // 1,82 % sur la MIE $68 900 ($5 741,67/mois).
                ['name' => 'Assurance-emploi salariale (QC réduit)', 'code' => 'EI_QC_EMP', 'type' => 'employee', 'rate' => self::EI_QC_EMPLOYEE_RATE, 'cap' => 5741.67],
                ['name' => 'Assurance-emploi patronale (QC réduit)', 'code' => 'EI_QC_PAT', 'type' => 'employer', 'rate' => self::EI_QC_EMPLOYER_RATE, 'cap' => 5741.67],
                // RQAP 2026 : 0,430 % / 0,602 % sur $103 000/an ($8 583,33/mois).
                ['name' => 'RQAP salariale', 'code' => 'QPIP_QC_EMP', 'type' => 'employee', 'rate' => self::QPIP_EMPLOYEE_RATE, 'cap' => 8583.33],
                ['name' => 'RQAP patronale', 'code' => 'QPIP_QC_PAT', 'type' => 'employer', 'rate' => self::QPIP_EMPLOYER_RATE, 'cap' => 8583.33],
            ];
        }

        return [
            // CPP 2026 : 5,95 % chacun sur l'assiette YMPE ($74 600/an →
            // $6 216,67/mois), exemption de base $3 500/an appliquée dans
            // calculateSocialCharges.
            ['name' => 'RPC/CPP salariale', 'code' => 'CPP_CA_EMP', 'type' => 'employee', 'rate' => 5.95, 'cap' => 6216.67],
            ['name' => 'RPC/CPP patronale', 'code' => 'CPP_CA_PAT', 'type' => 'employer', 'rate' => 5.95, 'cap' => 6216.67],
            // CPP2 2026 : 4 % sur l'assiette $74 600 → $85 000 (YAMPE —
            // $7 083,33/mois).
            ['name' => 'RPC2/CPP2 salariale', 'code' => 'CPP2_CA_EMP', 'type' => 'employee', 'rate' => 4.0, 'cap' => 7083.33, 'floor' => 6216.67],
            ['name' => 'RPC2/CPP2 patronale', 'code' => 'CPP2_CA_PAT', 'type' => 'employer', 'rate' => 4.0, 'cap' => 7083.33, 'floor' => 6216.67],
            // AE/EI 2026 : 1,63 % salarial (MIE $68 900 → $5 741,67/mois),
            // 2,282 % patronal (1,4 × 1,63 %).
            ['name' => 'Assurance-emploi salariale', 'code' => 'EI_CA_EMP', 'type' => 'employee', 'rate' => 1.63, 'cap' => 5741.67],
            ['name' => 'Assurance-emploi patronale', 'code' => 'EI_CA_PAT', 'type' => 'employer', 'rate' => 2.282, 'cap' => 5741.67],
        ];
    }

    protected function defaultTaxSlabs(): array
    {
        // Barème fédéral 2026 (2 % d'indexation + réduction 15 % → 14 % du
        // taux le plus bas). Bornes inclusives du helper progressif.
        return [
            ['min' => 0, 'max' => 58523, 'rate' => 14, 'fixed_deduction' => 0],
            ['min' => 58524, 'max' => 117045, 'rate' => 20.5, 'fixed_deduction' => 0],
            ['min' => 117046, 'max' => 181440, 'rate' => 26, 'fixed_deduction' => 0],
            ['min' => 181441, 'max' => 258482, 'rate' => 29, 'fixed_deduction' => 0],
            ['min' => 258483, 'max' => null, 'rate' => 33, 'fixed_deduction' => 0],
        ];
    }

    /**
     * Basic Personal Amount 2026 : $16 452 plein (revenu ≤ $181 440),
     * élimination progressive linéaire jusqu'à $14 829 (revenu ≥ $258 482).
     */
    private function basicPersonalAmount(float $annualIncome): float
    {
        if ($annualIncome <= self::BPA_PHASE_START) {
            return self::BPA_MAX;
        }

        if ($annualIncome >= self::BPA_PHASE_END) {
            return self::BPA_MIN;
        }

        $fraction = ($annualIncome - self::BPA_PHASE_START) / (self::BPA_PHASE_END - self::BPA_PHASE_START);

        return self::BPA_MAX - (self::BPA_MAX - self::BPA_MIN) * $fraction;
    }

    public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
    {
        $annualIncome = $grossTaxable * $annualBasis;
        $federalTax = $this->calculateProgressiveTax($annualIncome, $this->taxSlabs());

        // Le BPA est un crédit d'impôt NON remboursable : BPA × taux le
        // plus bas (14 % en 2026).
        $credit = $this->basicPersonalAmount($annualIncome) * 0.14;
        $federalNet = max(0.0, $federalTax - $credit);

        // #7933 — abattement du Québec : −16,5 % de l'impôt fédéral de base
        // pour un employé QC (T4032-QC 2026).
        if ($this->province === 'QC') {
            $federalNet *= 1.0 - self::QC_FEDERAL_ABATEMENT_RATE;
        }

        return round(($federalNet + $this->provincialIncomeTax($annualIncome)) / $annualBasis, 2);
    }

    /**
     * #7933 — impôt provincial ANNUEL (QC/ON, barèmes 2026). Simplification
     * pilote documentée (CA_COMPLIANCE.md §6) : la même assiette que le
     * fédéral est utilisée (brut − cotisations salariales) ; les déductions
     * spécifiques (déduction pour emploi QC, contribution-santé ON…) ne sont
     * pas modélisées. Provinces reconnues sans barème modélisé (AB, BC…) =
     * comportement fédéral-seul DOCUMENTÉ (pas de fallback silencieux : le
     * cas est acté dans complianceWarning() et CA_COMPLIANCE.md §6).
     */
    private function provincialIncomeTax(float $annualIncome): float
    {
        return match ($this->province) {
            // Barème QC 2026 (Revenu Québec) − crédit personnel de base
            // $18 952 × 14 %.
            'QC' => max(0.0, $this->calculateProgressiveTax($annualIncome, [
                ['min' => 0, 'max' => 54345, 'rate' => 14, 'fixed_deduction' => 0],
                ['min' => 54346, 'max' => 108680, 'rate' => 19, 'fixed_deduction' => 0],
                ['min' => 108681, 'max' => 132245, 'rate' => 24, 'fixed_deduction' => 0],
                ['min' => 132246, 'max' => null, 'rate' => 25.75, 'fixed_deduction' => 0],
            ]) - self::QC_BPA * self::QC_CREDIT_RATE),
            'ON' => $this->ontarioIncomeTax($annualIncome),
            default => 0.0,
        };
    }

    /**
     * Impôt ontarien 2026 : barème progressif − crédit personnel de base
     * ($12 989 × 5,05 %), puis SURTAXE (Taxation Act, 2007 (ON)) : 20 % de
     * l'impôt ontarien de base au-delà de $5 818 + 36 % au-delà de $7 446.
     */
    private function ontarioIncomeTax(float $annualIncome): float
    {
        $basicTax = max(0.0, $this->calculateProgressiveTax($annualIncome, [
            ['min' => 0, 'max' => 53891, 'rate' => 5.05, 'fixed_deduction' => 0],
            ['min' => 53892, 'max' => 107785, 'rate' => 9.15, 'fixed_deduction' => 0],
            ['min' => 107786, 'max' => 150000, 'rate' => 11.16, 'fixed_deduction' => 0],
            ['min' => 150001, 'max' => 220000, 'rate' => 12.16, 'fixed_deduction' => 0],
            ['min' => 220001, 'max' => null, 'rate' => 13.16, 'fixed_deduction' => 0],
        ]) - self::ON_BPA * self::ON_CREDIT_RATE);

        $surtax = 0.20 * max(0.0, $basicTax - self::ON_SURTAX_THRESHOLD_1)
            + 0.36 * max(0.0, $basicTax - self::ON_SURTAX_THRESHOLD_2);

        return $basicTax + $surtax;
    }

    public function calculateSocialCharges(float $grossSalary): array
    {
        if ($this->province === 'QC') {
            return $this->calculateQuebecSocialCharges($grossSalary);
        }

        $cppRate = $this->resolveContributionRate('CPP_CA_EMP', 5.95);
        $cpp2Rate = $this->resolveContributionRate('CPP2_CA_EMP', 4.0);
        $eiRate = $this->resolveContributionRate('EI_CA_EMP', 1.63);
        $cppEmployerRate = $this->resolveContributionRate('CPP_CA_PAT', 5.95);
        $cpp2EmployerRate = $this->resolveContributionRate('CPP2_CA_PAT', 4.0);
        $eiEmployerRate = $this->resolveContributionRate('EI_CA_PAT', 2.282);

        $ympe = self::CPP_YMPE / 12; // $6 216,67
        $yampe = self::CPP_YAMPE / 12; // $7 083,33
        $mie = self::EI_MIE / 12; // $5 741,67
        $basicExemption = self::CPP_BASIC_EXEMPTION / 12; // $291,67

        // CPP : (min(brut, YMPE) − exemption de base) × 5,95 %.
        $cppBase = ($this->capsEnabled() ? min($grossSalary, $ympe) : $grossSalary) - $basicExemption;
        $cpp = max(0.0, $cppBase) * $cppRate / 100;

        // CPP2 : 4 % sur la tranche [YMPE, YAMPE].
        $cpp2Base = $this->capsEnabled() ? min($grossSalary, $yampe) - $ympe : 0.0;
        $cpp2 = max(0.0, $cpp2Base) * $cpp2Rate / 100;

        // EI : 1,63 % sur le brut plafonné à la MIE.
        $eiBase = $this->capsEnabled() ? min($grossSalary, $mie) : $grossSalary;
        $ei = $eiBase * $eiRate / 100;
        $eiEmployer = $eiBase * $eiEmployerRate / 100;

        return [
            'employee' => round($cpp + $cpp2 + $ei, 2),
            'employer' => round($cpp + $cpp2 + $eiEmployer, 2),
        ];
    }

    /**
     * #7933 — cotisations d'un employé QUÉBÉCOIS (2026) : RRQ/QPP 6,30 %
     * (base 5,3 % + supplémentaire 1 %) et RRQ2 4 % À LA PLACE du RPC/CPP,
     * AE au taux réduit Québec (1,30 % / 1,82 %) et RQAP (0,430 % / 0,602 %
     * sur $103 000/an). Sources : Retraite Québec, Revenu Québec, CEIC —
     * docs/payroll/CA_COMPLIANCE.md §2bis.
     *
     * @return array{employee: float, employer: float}
     */
    private function calculateQuebecSocialCharges(float $grossSalary): array
    {
        $qppRate = $this->resolveContributionRate('QPP_CA_EMP', self::QPP_RATE);
        $qpp2Rate = $this->resolveContributionRate('QPP2_CA_EMP', self::QPP2_RATE);
        $eiRate = $this->resolveContributionRate('EI_QC_EMP', self::EI_QC_EMPLOYEE_RATE);
        $qpipRate = $this->resolveContributionRate('QPIP_QC_EMP', self::QPIP_EMPLOYEE_RATE);
        $qppEmployerRate = $this->resolveContributionRate('QPP_CA_PAT', self::QPP_RATE);
        $qpp2EmployerRate = $this->resolveContributionRate('QPP2_CA_PAT', self::QPP2_RATE);
        $eiEmployerRate = $this->resolveContributionRate('EI_QC_PAT', self::EI_QC_EMPLOYER_RATE);
        $qpipEmployerRate = $this->resolveContributionRate('QPIP_QC_PAT', self::QPIP_EMPLOYER_RATE);

        $ympe = self::CPP_YMPE / 12; // $6 216,67
        $yampe = self::CPP_YAMPE / 12; // $7 083,33
        $mie = self::EI_MIE / 12; // $5 741,67
        $qpipMie = self::QPIP_MIE / 12; // $8 583,33
        $basicExemption = self::CPP_BASIC_EXEMPTION / 12; // $291,67

        // RRQ : (min(brut, YMPE) − exemption de base) × 6,30 %.
        $qppBase = ($this->capsEnabled() ? min($grossSalary, $ympe) : $grossSalary) - $basicExemption;
        $qpp = max(0.0, $qppBase) * $qppRate / 100;
        $qppEmployer = max(0.0, $qppBase) * $qppEmployerRate / 100;

        // RRQ2 : 4 % sur la tranche [YMPE, YAMPE].
        $qpp2Base = $this->capsEnabled() ? min($grossSalary, $yampe) - $ympe : 0.0;
        $qpp2 = max(0.0, $qpp2Base) * $qpp2Rate / 100;
        $qpp2Employer = max(0.0, $qpp2Base) * $qpp2EmployerRate / 100;

        // AE (taux réduit QC) : 1,30 % / 1,82 % sur le brut plafonné à la MIE.
        $eiBase = $this->capsEnabled() ? min($grossSalary, $mie) : $grossSalary;
        $ei = $eiBase * $eiRate / 100;
        $eiEmployer = $eiBase * $eiEmployerRate / 100;

        // RQAP : 0,430 % / 0,602 % sur le brut plafonné à $103 000/an.
        $qpipBase = $this->capsEnabled() ? min($grossSalary, $qpipMie) : $grossSalary;
        $qpip = $qpipBase * $qpipRate / 100;
        $qpipEmployer = $qpipBase * $qpipEmployerRate / 100;

        return [
            'employee' => round($qpp + $qpp2 + $ei + $qpip, 2),
            'employer' => round($qppEmployer + $qpp2Employer + $eiEmployer + $qpipEmployer, 2),
        ];
    }

    public function timezone(): string
    {
        // Provincial/territorial IANA timezone; falls back to
        // America/Toronto (Ontario, the most populous province) when no
        // province is set, matching the scope doc's "America/Toronto"
        // zone-wide default.
        return match ($this->province) {
            'BC' => 'America/Vancouver',
            'AB' => 'America/Edmonton',
            'SK' => 'America/Regina',
            'MB' => 'America/Winnipeg',
            'QC' => 'America/Toronto', // Eastern, same offset as Ontario
            'NB' => 'America/Moncton',
            'NS' => 'America/Halifax',
            'PE' => 'America/Halifax',
            'NL' => 'America/St_Johns',
            'YT' => 'America/Whitehorse',
            'NT' => 'America/Yellowknife',
            'NU' => 'America/Iqaluit',
            default => 'America/Toronto', // ON or no province set (federal default)
        };
    }

    /**
     * @return array<int, int>
     */
    public function weeklyRestDays(): array
    {
        // Sunday is the standard weekly rest day used as the Canada-wide
        // default; provinces do not mandate a specific weekday.
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
        return 'CA fixed federal public holidays (seed PublicHolidaySeeder, issue #2255): 1er jan, 1er juil, 11 nov, '.
            '25 déc + mobiles fédéraux (Good Friday, Victoria Day, Labour Day, Thanksgiving) — les fériés '.
            'provinciaux restent à saisir manuellement (PA2-COUNTRY-012).';
    }

    public function confidenceLevel(): string
    {
        return 'pilot';
    }

    /**
     * PA2-COUNTRY-006 follow-up: matches App\Support\CountryDefaults,
     * where CA defaults to English.
     */
    public function language(): string
    {
        return 'en';
    }

    public function complianceWarning(): string
    {
        return 'Pilot ruleset for Canada: 2026 federal tax brackets (14 % lowest rate), CPP/CPP2 (YMPE $74,600, YAMPE $85,000) and EI (1.63 % on $68,900) are sourced from CRA/Canada.ca public guidance. Provincial income tax is modelled for QC and ON ONLY (2026 brackets + provincial basic personal amount; ON surtax; QC federal abatement 16.5 %); a QC-scoped employee gets QPP/QPP2, reduced-rate EI and QPIP instead of CPP/standard EI. Other recognized provinces/territories remain FEDERAL-ONLY (documented, CA_COMPLIANCE.md §6); unknown province codes throw. Ontario Health Premium, provincial minimum wages and QC-specific deductions are NOT modelled. NOT a substitute for a certified Canadian payroll provider or local counsel — do not rely on this for statutory payroll compliance without validation.';
    }

    /**
     * #7933 — la province change les montants (impôt provincial, RRQ/RQAP) :
     * elle doit donc participer à l'empreinte de version des règles, en plus
     * des probes de la classe parente.
     */
    public function rulesVersion(): string
    {
        return parent::rulesVersion().'-'.strtolower($this->province ?? 'federal');
    }

    /**
     * #7933 — référentiel de conformité CA (fédéral + provincial QC/ON,
     * sources CRA / Revenu Québec / ministère des Finances ON 2026).
     */
    public function complianceSource(): string
    {
        return 'docs/payroll/CA_COMPLIANCE.md';
    }

    /**
     * PA2-COUNTRY-009: statutory weekly overtime threshold differs by
     * province — this is the provincial variation the acceptance criteria
     * ("overtime provinciaux") calls for. Falls back to the federal Canada
     * Labour Code threshold (44h/week) when no province is set. Pilot-
     * grade sourcing (general employment-standards baselines, not locally
     * legally validated), see confidenceLevel().
     */
    public function overtimeThresholdWeeklyHours(): float
    {
        return match ($this->province) {
            'BC', 'MB', 'NL', 'QC', 'NT', 'NU', 'SK', 'YT' => 40.0,
            'NS', 'PE' => 48.0,
            'AB', 'NB', 'ON' => 44.0,
            default => 44.0, // federal Canada Labour Code default
        };
    }

    /**
     * PA2-COUNTRY-009: every supported province/territory (and the federal
     * default) uses a single +50% overtime premium tier beyond the
     * provincial threshold above; the provincial variation lives in the
     * threshold, not the multiplier. Pilot-grade, see confidenceLevel().
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
     * Canada Labour Code art. 230 : préavis fédéral — 1 semaine après
     * 3 mois, 2 après 1 an, puis +1 semaine par année jusqu'à 8 semaines
     * (≥ 8 ans). Les provinces ont leurs propres régimes (non modélisés).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return match (true) {
            $yearsOfService < 0.25 => 0.0,
            $yearsOfService < 1.0 => 7.0,
            $yearsOfService < 3.0 => 14.0,
            $yearsOfService < 4.0 => 21.0,
            $yearsOfService < 5.0 => 28.0,
            $yearsOfService < 6.0 => 35.0,
            $yearsOfService < 7.0 => 42.0,
            $yearsOfService < 8.0 => 49.0,
            default => 56.0,
        };
    }

    /**
     * Indemnité de départ statutaire : provinciale (ex. Ontario ESA — 1
     * semaine par année plafonnée 8). Approximation pilote fédérale :
     * 1 semaine par année ≈ 0,2309 mois.
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 0.2309;
    }

    /**
     * Pas de régime fédéral d'indemnisation maladie courte durée (les
     * provinces ont leurs régimes, ex. QPIP au Québec) → politique inerte
     * documentée.
     *
     * @return array{
     *     waiting_days: int,
     *     daily_allowance_rates: array<int, array{from_day: int, to_day: int|null, rate: float}>,
     *     max_paid_days: int,
     *     employer_maintenance_days: int,
     * }
     */
    public function sickLeavePolicy(): array
    {
        return [
            'waiting_days' => 0,
            'daily_allowance_rates' => [],
            'max_paid_days' => 0,
            'employer_maintenance_days' => 0,
        ];
    }
}
