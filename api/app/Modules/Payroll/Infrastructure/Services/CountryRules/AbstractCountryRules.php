<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services\CountryRules;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

abstract class AbstractCountryRules implements CountryRulesInterface
{
    /**
     * Optional tenant scope used to look up company-specific tax slab overrides.
     * Set via forCompany(); left null means "use global/default slabs only".
     */
    protected ?string $companyId = null;

    /** @var array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>|null */
    private ?array $taxSlabsOverride = null;

    private bool $capsEnabled = true;

    /**
     * Point in time used to resolve which TaxSlab/SocialContribution rows are
     * "effective" (PA2-ARCH-004: country rates/tables are associated with an
     * effective date so a past payroll run can be recalculated for audit
     * purposes using the rates that applied *during its own period*, not
     * today's rates). Set via asOf(); null means "use now()", matching the
     * pre-existing behaviour.
     */
    protected ?Carbon $asOfDate = null;

    /**
     * Cache of resolved SocialContribution rows for the current asOfDate/
     * companyId scope, keyed by contribution code, so a single calculateRun()
     * only queries once per code even though calculateSocialCharges() may
     * read several rates from it.
     *
     * @var array<string, SocialContribution|null>
     */
    private array $resolvedContributions = [];

    /**
     * Returns a clone of this rules object scoped to a given company, so that
     * company-specific TaxSlab overrides (configured via TaxSlabController) are
     * taken into account by taxSlabs()/calculateIncomeTax().
     */
    public function forCompany(?string $companyId): static
    {
        $clone = clone $this;
        $clone->companyId = $companyId;

        return $clone;
    }

    /**
     * Returns a clone of this rules object scoped to a specific point in
     * time, so taxSlabs()/calculateIncomeTax()/calculateSocialCharges()
     * resolve the TaxSlab/SocialContribution rows that were effective on
     * that date instead of today's (PA2-ARCH-004). Typically called with a
     * payroll run's period date so recalculating an old run for audit stays
     * consistent with the rates that applied back then. Pass null to reset
     * to "use now()".
     */
    public function asOf(\DateTimeInterface|string|null $date): static
    {
        $clone = clone $this;
        $clone->asOfDate = $date === null ? null : Carbon::parse($date);
        $clone->resolvedContributions = [];

        return $clone;
    }

    /**
     * Stable fingerprint of the exact effective ruleset used by a calculation.
     * The payload includes the country, tenant/date scope, tax slabs and social
     * contribution schedule so a changed override cannot reuse an old version.
     */
    public function rulesVersion(): string
    {
        $payload = [
            'country_code' => $this->countryCode(),
            'company_id' => $this->companyId,
            'as_of' => $this->asOfDate?->toDateString(),
            'tax_slabs' => $this->taxSlabs(),
            'social_contributions' => $this->socialContributions(),
            'resolved_social_probe' => $this->calculateSocialCharges(10000.0),
            'resolved_income_tax_probe' => $this->calculateIncomeTax(10000.0),
            'resolved_bracket_tax_probe' => $this->calculateBracketTax(10000.0),
        ];

        return 'v1-'.substr(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * Effective tax slabs: company-specific override from the `tax_slabs` table
     * if present, else a global (company_id IS NULL) override from the same
     * table, else the country's hardcoded default slabs. This makes the
     * TaxSlabController CRUD API (and the underlying `tax_slabs` table) actually
     * affect payroll calculations, instead of being a disconnected admin screen.
     *
     * S-3 (#1663, D-01) : les barèmes « par défaut » sont NATIONAUX (périmètre
     * pays résolu par la règle, ex. DZ) — tout barème spécifique entreprise
     * doit être déclaré en base (`tax_slabs`) pour être pris en compte.
     *
     * Wrapped defensively: if called outside a booted Laravel app (e.g. pure
     * PHPUnit\Framework\TestCase unit tests with no facade root/DB), silently
     * falls back to defaultTaxSlabs() so existing unit tests keep passing
     * unchanged.
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    public function taxSlabs(): array
    {
        // Issue #1814 : override de simulation (dry-run) — prioritaire sur la
        // base, ne persiste rien.
        if ($this->taxSlabsOverride !== null) {
            return $this->taxSlabsOverride;
        }

        return $this->resolveTaxSlabsFromDatabase() ?? $this->defaultTaxSlabs();
    }

    /**
     * Issue #1814 — injecte un barème temporaire pour la simulation d'impact
     * (endpoint /payroll/simulate). Ne touche pas à la base de données.
     *
     * @param  array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>  $slabs
     */
    /**
     * Issue #1815 — active/désactive l'application des plafonds de cotisation
     * (mode simulation « avec/sans plafond »). N'affecte que cette instance.
     */
    public function withCapsEnabled(bool $enabled): static
    {
        $this->capsEnabled = $enabled;

        return $this;
    }

    public function withTaxSlabs(array $slabs): static
    {
        $this->taxSlabsOverride = $slabs;

        return $this;
    }

    /**
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>|null
     */
    protected function resolveTaxSlabsFromDatabase(): ?array
    {
        try {
            if (! Schema::hasTable('tax_slabs')) {
                return null;
            }

            // Issue #1813 : seules les lignes ACTIVES participent aux calculs
            // (draft/pending_validation/superseded sont ignorées).
            $base = TaxSlab::query()
                ->forCountry($this->countryCode())
                ->active()
                ->effective($this->asOfDate);

            if ($this->companyId !== null) {
                $companySlabs = (clone $base)->where('company_id', $this->companyId)->orderBy('min_amount')->get();
                if ($companySlabs->isNotEmpty()) {
                    return $this->mapSlabs($companySlabs);
                }
            }

            $globalSlabs = (clone $base)->whereNull('company_id')->orderBy('min_amount')->get();
            if ($globalSlabs->isNotEmpty()) {
                return $this->mapSlabs($globalSlabs);
            }

            return null;
        } catch (\Throwable) {
            // No booted app/DB (e.g. pure unit tests) or transient DB error:
            // fall back to the hardcoded default slabs rather than fatal.
            return null;
        }
    }

    /**
     * @param  Collection<int, TaxSlab>  $slabs
     * @return array<int, array{min: float, max: float|null, rate: float, fixed_deduction: float}>
     */
    private function mapSlabs(Collection $slabs): array
    {
        return $slabs->map(static fn (TaxSlab $slab): array => [
            'min' => (float) $slab->min_amount,
            'max' => $slab->max_amount === null ? null : (float) $slab->max_amount,
            'rate' => (float) $slab->rate,
            'fixed_deduction' => (float) $slab->fixed_deduction,
        ])->all();
    }

    /**
     * Country-specific hardcoded fallback slabs, used only when no matching
     * rows exist in the `tax_slabs` table (fresh install before seeding, or
     * country not yet seeded).
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    abstract protected function defaultTaxSlabs(): array;

    /**
     * Barème légal de référence (source légale), indépendant de la base :
     * retourne `defaultTaxSlabs()` — les valeurs ancrées dans le code par
     * pays (CGI, codes du travail). Issue #2003 : le seeder doit seeder
     * depuis cette source, pas depuis `taxSlabs()` qui résout la base AVANT
     * le code (re-seeder = no-op silencieux quand la base diverge du code).
     *
     * @return array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>
     */
    public function legalReferenceTaxSlabs(): array
    {
        return $this->defaultTaxSlabs();
    }

    /**
     * Resolves the effective rate (percentage points, e.g. 9.0 for 9%) for a
     * given SocialContribution code as of asOfDate() (or now() when unset),
     * scoped to companyId() with a fallback to the global (company_id IS
     * NULL) row. Falls back to $defaultRate when the `social_contributions`
     * table doesn't exist yet, no matching row is effective, or the app/DB
     * isn't booted (pure unit tests) — so existing hardcoded percentages
     * keep working unchanged until a country is actually seeded.
     *
     * This is what makes socialContributions()/the `social_contributions`
     * table (and its effective_from/effective_to columns) actually drive
     * calculateSocialCharges(), instead of being a disconnected admin CRUD
     * screen — the same disconnect PA2-ARCH-001 fixed for tax_slabs. It's
     * also what makes retroactive recalculation possible for audit purposes
     * (PA2-ARCH-004): recalculating an old payroll run with asOf() set to its
     * own period resolves the rate that was effective back then, not today's.
     */
    protected function resolveContributionRate(string $code, float $defaultRate): float
    {
        $contribution = $this->resolveContribution($code);

        return $contribution === null ? $defaultRate : $contribution->rate;
    }

    /**
     * Resolves the effective cap (same scoping rules as
     * resolveContributionRate() above) for a given SocialContribution code.
     * Returns $defaultCap when no matching DB row is found/effective.
     */
    protected function resolveContributionCap(string $code, ?float $defaultCap): ?float
    {
        $contribution = $this->resolveContribution($code);

        return $contribution === null ? $defaultCap : $contribution->cap;
    }

    private function resolveContribution(string $code): ?SocialContribution
    {
        if (array_key_exists($code, $this->resolvedContributions)) {
            return $this->resolvedContributions[$code];
        }

        try {
            if (! Schema::hasTable('social_contributions')) {
                return $this->resolvedContributions[$code] = null;
            }

            $base = SocialContribution::query()
                ->forCountry($this->countryCode())
                ->active()
                ->where('code', $code)
                ->effective($this->asOfDate);

            if ($this->companyId !== null) {
                $companyRow = (clone $base)->where('company_id', $this->companyId)->first();
                if ($companyRow !== null) {
                    return $this->resolvedContributions[$code] = $companyRow;
                }
            }

            $globalRow = (clone $base)->whereNull('company_id')->first();

            return $this->resolvedContributions[$code] = $globalRow;
        } catch (\Throwable) {
            // No booted app/DB (e.g. pure unit tests) or transient DB error:
            // fall back to the hardcoded default rate/cap rather than fatal.
            return $this->resolvedContributions[$code] = null;
        }
    }

    /**
     * Issue #1872 — source légale de référence du pays (référentiel de
     * conformité). Surchargée par les pays qui citent une source plus précise
     * (loi/décret) — utilisée par le contrat de calcul pour afficher
     * « source + date de vérification » côté clients web/mobile.
     */
    public function complianceSource(): string
    {
        return 'docs/payroll/'.strtoupper($this->countryCode()).'_COMPLIANCE.md';
    }

    /**
     * Issue #1872 — date de dernière VÉRIFICATION EXPERTE des taux légaux
     * (YYYY-MM-DD), ou null tant qu'aucune validation experte n'a eu lieu
     * (registre : docs/payroll/VALIDATION_EXPERTE.md, issues #1904/#1912).
     * Un pays en `production` doit renseigner cette date.
     */
    public function verificationDate(): ?string
    {
        return null;
    }

    /**
     * Issue #2117 — défaut générique de la RICF : aucune réduction (le
     * mécanisme est inerte hors pays l'ayant implémenté, ex. CI art. 120
     * CGI via CedeaoPayrollRules). Constitution §III : les réductions sont
     * déclarées dans une méthode DÉDIÉE, jamais en inline dans
     * calculateIncomeTax().
     */
    public function familyTaxReduction(float $familyParts = 1.0): float
    {
        return 0.0;
    }

    /**
     * PA2-COUNTRY-006: default compliance disclaimer shared by every
     * country implementation, derived from confidenceLevel() so it stays
     * accurate automatically as a country's rules mature from
     * 'placeholder'/'pilot' to 'production' without touching every class.
     * Subclasses may override with country-specific wording (e.g. citing a
     * specific labor code article) if that becomes useful later.
     */
    public function complianceWarning(): string
    {
        return match ($this->confidenceLevel()) {
            'production' => sprintf(
                'Legally validated for %s payroll use, but always confirm current rates with local counsel before relying on this for statutory filings.',
                $this->countryCode()
            ),
            'placeholder' => sprintf(
                'Structure-only placeholder for %s: tax/social-contribution figures are not yet researched and must not be used for real payroll runs without replacing them first.',
                $this->countryCode()
            ),
            default => sprintf(
                'Pilot ruleset for %s, sourced from general public labor-code references but not yet legally validated locally. Confirm with local legal/tax counsel before relying on these figures (tax slabs, social contributions, overtime thresholds) for statutory compliance.',
                $this->countryCode()
            ),
        };
    }

    /**
     * Calculates progressive tax for slabs declared with inclusive human-readable
     * bounds such as 0-5000, 5001-20000.
     *
     * @param  array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>  $slabs
     */
    protected function calculateProgressiveTax(float $taxableAmount, array $slabs): float
    {
        $tax = 0.0;

        foreach ($slabs as $slab) {
            $lowerBound = (float) $slab['min'];
            if ($lowerBound > 0) {
                $lowerBound -= 1;
            }

            $upperBound = $slab['max'] === null ? PHP_FLOAT_MAX : (float) $slab['max'];
            if ($taxableAmount <= $lowerBound) {
                continue;
            }

            $taxableInSlab = min($taxableAmount, $upperBound) - $lowerBound;
            if ($taxableInSlab <= 0) {
                continue;
            }

            $tax += $taxableInSlab * ((float) $slab['rate'] / 100);

            if ($taxableAmount <= $upperBound) {
                break;
            }
        }

        return $tax;
    }

    /**
     * Historic default: no statutory notice pay (the contract/execution
     * decides). Countries with a documented legal notice period override
     * this. FOCUS 2 (F-31).
     */
    public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
    {
        return 0.0;
    }

    /**
     * Historic default: one month of base salary per year of service
     * (F-08). Countries may override with tiered legal formulas. FOCUS 2
     * (F-31).
     */
    public function severanceMonthsPerYear(float $yearsOfService): float
    {
        return 1.0;
    }

    /**
     * ZONE-INFRA (#1820): single helper to compute a social contribution
     * with its statutory cap applied, used by every country's
     * calculateSocialCharges() so the cap logic (base = min(gross, cap))
     * lives in exactly one place instead of being re-implemented per
     * country (which is how caps get forgotten and bugs ship to
     * production). The rate and cap are resolved from the
     * `social_contributions` table when present (effective dating,
     * company overrides) with the provided defaults as fallback.
     */
    protected function computeContribution(
        float $grossSalary,
        string $code,
        float $defaultRate,
        ?float $defaultCap
    ): float {
        $rate = $this->resolveContributionRate($code, $defaultRate);
        $cap = $this->resolveContributionCap($code, $defaultCap);
        // Issue #1815 : le simulateur peut désactiver le plafonnement pour
        // comparer l'impact « avec/sans plafond légal ».
        $base = $this->capsEnabled && $cap !== null ? min($grossSalary, $cap) : $grossSalary;

        return round($base * $rate / 100, 2);
    }

    /**
     * ZONE-INFRA (#1820): default = no professional-expenses deduction.
     * Countries with a legal abatement (CM 30 % capped 350 000 XAF,
     * CI, SN...) override this and apply it inside calculateIncomeTax().
     *
     * @return array{rate: float, cap: float|null}
     */
    public function professionalExpensesDeduction(): array
    {
        return ['rate' => 0.0, 'cap' => null];
    }

    /**
     * ZONE-INFRA (#1820): default = no minimum bracket tax.
     */
    public function calculateBracketTax(float $grossSalary): float
    {
        return 0.0;
    }

    /**
     * Issue #1934 — défaut ADDITIF : l'impôt et la taxe de minimum fiscal
     * s'additionnent (CI : IR + CN, etc.). Les pays au mécanisme légal
     * « max » (Sénégal : le salarié paie le plus élevé de IR/TRIMF,
     * CGI SN §3) override cette méthode.
     */
    public function combineMinimumFiscalTax(float $incomeTax, float $bracketTax): float
    {
        return $incomeTax + $bracketTax;
    }

    /**
     * ZONE-INFRA (#1820): default label of the flat-tax deduction line —
     * "Taxe de minimum fiscal". Countries using the same mechanism for a
     * differently-named flat tax (CI's Contribution Nationale, #1825)
     * override this.
     */
    public function flatPayrollTaxLabel(): string
    {
        return 'Taxe de minimum fiscal';
    }

    /**
     * ZONE-INFRA (#1820): default = 13th month not legally mandatory
     * (contractual practice only, matching historic behaviour).
     */
    public function thirteenthMonthMandatory(): bool
    {
        return false;
    }

    /**
     * ZONE-INFRA (#1820): default = fully taxable like ordinary pay.
     */
    public function thirteenthMonthTaxTreatment(): string
    {
        return 'fully_taxable';
    }

    /**
     * ZONE-INFRA (#1820): default = no family allowance.
     */
    public function familyAllowancePerChild(): float
    {
        return 0.0;
    }

    /**
     * Issue #5241 (écart E5) — défaut INERTE : aucune indemnisation
     * statutaire modélisée (pas de délai de carence, pas de taux d'IJ, pas
     * de maintien patronal). Les pays avec un régime documenté (ex. DZ :
     * IJ CNAS 50 % J1-15 puis 100 % J16+) override cette méthode avec des
     * valeurs sourcées et conservent confidenceLevel()='pilot' tant qu'un
     * expert local n'a pas validé.
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
