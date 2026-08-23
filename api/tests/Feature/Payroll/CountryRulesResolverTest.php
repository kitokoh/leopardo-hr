<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\CountryRulesContextMismatchException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1868) — résolveur unique des règles pays.
 *
 * Couvre : matrice de tous les codes enregistrés, non-fallback CI/DZ,
 * pays inconnu → erreur typée (jamais de calcul approximatif), scopes
 * entreprise/période transmis, incohérence de contexte refusée.
 */
class CountryRulesResolverTest extends TestCase
{
    public function test_matrix_all_supported_codes_resolve(): void
    {
        $resolver = new CountryRulesResolver;

        $supported = $resolver->supportedCountryCodes();
        $this->assertContains('DZ', $supported);
        $this->assertContains('FR', $supported);
        $this->assertContains('CM', $supported);
        $this->assertContains('CI', $supported);
        $this->assertContains('CA', $supported);

        // Chaque code enregistré résout une règle qui expose CE code.
        foreach ($supported as $countryCode) {
            $rules = $resolver->resolve($countryCode);
            $this->assertSame($countryCode, $rules->countryCode(), "countryCode mismatch for {$countryCode}");
        }
    }

    public function test_ci_resolves_to_cedeao_never_dz(): void
    {
        $resolver = new CountryRulesResolver;

        $ci = $resolver->resolve('CI');
        $this->assertInstanceOf(CedeaoPayrollRules::class, $ci);
        $this->assertSame('CI', $ci->countryCode());

        // La CI ne peut JAMAIS retomber sur les règles DZ.
        $this->assertNotSame('DZ', $ci->countryCode());
    }

    public function test_sn_and_cm_resolve_to_dedicated_implementations(): void
    {
        $resolver = new CountryRulesResolver;

        $this->assertInstanceOf(SenegalPayrollRules::class, $resolver->resolve('SN'));
        $this->assertInstanceOf(CemacPayrollRules::class, $resolver->resolve('CM'));
        $this->assertSame('CM', $resolver->resolve('CM')->countryCode());
    }

    public function test_unknown_country_throws_typed_error(): void
    {
        $resolver = new CountryRulesResolver;

        try {
            $resolver->resolve('XX');
            $this->fail('UnsupportedCountryRulesException attendue pour un pays inconnu.');
        } catch (UnsupportedCountryRulesException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertSame('UNSUPPORTED_COUNTRY_RULES', $e->errorCode());
        }

        // Le résolveur normalise la casse.
        $this->assertSame('DZ', $resolver->resolve('dz')->countryCode());
    }

    public function test_resolve_forwards_company_and_period_scopes(): void
    {
        $scopedCompany = null;
        $scopedAsOf = null;

        $base = new class($scopedCompany, $scopedAsOf) implements CountryRulesInterface
        {
            /** @var string|null */
            public $receivedCompany;

            /** @var \DateTimeInterface|null */
            public $receivedAsOf;

            public function __construct(?string &$company, ?\DateTimeInterface &$asOf)
            {
                $this->receivedCompany = &$company;
                $this->receivedAsOf = &$asOf;
            }

            public function countryCode(): string
            {
                return 'ZZ';
            }

            public function currency(): string
            {
                return 'XOF';
            }

            public function minimumWage(): float
            {
                return 0.0;
            }

            public function socialContributions(): array
            {
                return [];
            }


            public function combineMinimumFiscalTax(float $incomeTax, float $bracketTax): float
            {
                return $incomeTax + $bracketTax;
            }

            public function taxSlabs(): array
            {
                return [];
            }

            public function withTaxSlabs(array $slabs): static
            {
                return $this;
            }

            public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
            {
                return 0.0;
            }

            public function calculateSocialCharges(float $grossSalary): array
            {
                return ['employee' => 0.0, 'employer' => 0.0];
            }

            public function timezone(): string
            {
                return 'UTC';
            }

            public function weeklyRestDays(): array
            {
                return [7];
            }

            public function overtimeThresholdWeeklyHours(): float
            {
                return 40.0;
            }

            public function overtimeRateTiers(): array
            {
                return [['up_to_hours' => 40.0, 'multiplier' => 1.5]];
            }

            public function supportedPayCycles(): array
            {
                return ['monthly'];
            }

            public function publicHolidaysSource(): string
            {
                return 'test';
            }

            public function confidenceLevel(): string
            {
                return 'test';
            }

            public function language(): string
            {
                return 'fr';
            }

            public function complianceWarning(): string
            {
                return '';
            }

            public function complianceSource(): string
            {
                return 'docs/payroll/_TEST_COMPLIANCE.md';
            }

            public function verificationDate(): ?string
            {
                return null;
            }

            public function familyTaxReduction(float $familyParts = 1.0): float
            {
                return 0.0;
            }

            public function complianceWarningKey(): string
            {
                return 'payroll.compliance_warning_placeholder';
            }

            /**
             * @return array<int, string>
             */
            public function legalSources(): array
            {
                return [];
            }

            public function rulesVersion(): string
            {
                return 'test-rules-v1';
            }

            public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
            {
                return 0.0;
            }

            public function severanceMonthsPerYear(float $yearsOfService): float
            {
                return 0.0;
            }

            public function professionalExpensesDeduction(): array
            {
                return ['rate' => 0.0, 'cap' => null];
            }

            public function calculateBracketTax(float $grossSalary): float
            {
                return 0.0;
            }

            public function flatPayrollTaxLabel(): string
            {
                return '';
            }

            public function thirteenthMonthMandatory(): bool
            {
                return false;
            }

            public function thirteenthMonthTaxTreatment(): string
            {
                return 'fully_taxable';
            }

            public function familyAllowancePerChild(): float
            {
                return 0.0;
            }

            public function sickLeavePolicy(): array
            {
                return [
                    'waiting_days' => 0,
                    'daily_allowance_rates' => [],
                    'max_paid_days' => 0,
                    'employer_maintenance_days' => 0,
                ];
            }

            public function forCompany(?string $companyId): static
            {
                $this->receivedCompany = $companyId;

                return $this;
            }

            public function asOf(\DateTimeInterface|string|null $date): static
            {
                $this->receivedAsOf = $date === null ? null : Carbon::parse($date);

                return $this;
            }

            public function withCapsEnabled(bool $enabled): static
            {
                return $this;
            }

        };

        $resolver = new CountryRulesResolver([$base]);

        $resolved = $resolver->resolve('ZZ', 'company-123', new \DateTimeImmutable('2026-01-15'));

        $this->assertSame($base, $resolved);
        $this->assertSame('company-123', $base->receivedCompany);
        $this->assertNotNull($base->receivedAsOf);
        $this->assertSame('2026-01-15', $base->receivedAsOf->format('Y-m-d'));
    }

    public function test_context_mismatch_is_rejected(): void
    {
        // Une règle dont le pays exposé ne correspond plus à la clé demandée
        // (le code devient incohérent entre l'enregistrement et la résolution).
        $rogue = new class implements CountryRulesInterface
        {
            public string $code = 'ZZ';

            public function countryCode(): string
            {
                return $this->code;
            }

            public function currency(): string
            {
                return 'DZD';
            }

            public function minimumWage(): float
            {
                return 0.0;
            }

            public function socialContributions(): array
            {
                return [];
            }


            public function combineMinimumFiscalTax(float $incomeTax, float $bracketTax): float
            {
                return $incomeTax + $bracketTax;
            }

            public function taxSlabs(): array
            {
                return [];
            }

            public function withTaxSlabs(array $slabs): static
            {
                return $this;
            }

            public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
            {
                return 0.0;
            }

            public function calculateSocialCharges(float $grossSalary): array
            {
                return ['employee' => 0.0, 'employer' => 0.0];
            }

            public function timezone(): string
            {
                return 'Africa/Algiers';
            }

            public function weeklyRestDays(): array
            {
                return [5, 6];
            }

            public function overtimeThresholdWeeklyHours(): float
            {
                return 40.0;
            }

            public function overtimeRateTiers(): array
            {
                return [['up_to_hours' => 40.0, 'multiplier' => 1.5]];
            }

            public function supportedPayCycles(): array
            {
                return ['monthly'];
            }

            public function publicHolidaysSource(): string
            {
                return 'test';
            }

            public function confidenceLevel(): string
            {
                return 'test';
            }

            public function language(): string
            {
                return 'fr';
            }

            public function complianceWarning(): string
            {
                return '';
            }

            public function complianceSource(): string
            {
                return 'docs/payroll/_TEST_COMPLIANCE.md';
            }

            public function verificationDate(): ?string
            {
                return null;
            }

            public function familyTaxReduction(float $familyParts = 1.0): float
            {
                return 0.0;
            }

            public function complianceWarningKey(): string
            {
                return 'payroll.compliance_warning_placeholder';
            }

            /**
             * @return array<int, string>
             */
            public function legalSources(): array
            {
                return [];
            }

            public function rulesVersion(): string
            {
                return 'test-rules-v1';
            }

            public function noticePeriodDays(float $yearsOfService, ?string $category = null): float
            {
                return 0.0;
            }

            public function severanceMonthsPerYear(float $yearsOfService): float
            {
                return 0.0;
            }

            public function professionalExpensesDeduction(): array
            {
                return ['rate' => 0.0, 'cap' => null];
            }

            public function calculateBracketTax(float $grossSalary): float
            {
                return 0.0;
            }

            public function flatPayrollTaxLabel(): string
            {
                return '';
            }

            public function thirteenthMonthMandatory(): bool
            {
                return false;
            }

            public function thirteenthMonthTaxTreatment(): string
            {
                return 'fully_taxable';
            }

            public function familyAllowancePerChild(): float
            {
                return 0.0;
            }

            public function sickLeavePolicy(): array
            {
                return [
                    'waiting_days' => 0,
                    'daily_allowance_rates' => [],
                    'max_paid_days' => 0,
                    'employer_maintenance_days' => 0,
                ];
            }

            public function withCapsEnabled(bool $enabled): static
            {
                return $this;
            }

            public function forCompany(?string $companyId): static
            {
                return $this;
            }

            public function asOf(\DateTimeInterface|string|null $date): static
            {
                return $this;
            }

            /**
             * @param  array<int, array{min: float|int, max: float|int|null, rate: float|int, fixed_deduction: float|int}>|null  $slabs
             */
            public function withSlabs(?array $slabs): static
            {
                return $this;
            }
        };

        $resolver = new CountryRulesResolver([$rogue]);

        // Le pays exposé devient incohérent avec la clé enregistrée.
        $rogue->code = 'DZ';

        $this->expectException(CountryRulesContextMismatchException::class);

        $resolver->resolve('ZZ');
    }
}
