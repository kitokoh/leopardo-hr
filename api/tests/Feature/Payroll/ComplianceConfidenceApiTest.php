<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1872 — avertissements de conformité par niveau de confiance.
 *
 * Le niveau (production / pilot / placeholder) doit être visible avant le
 * premier calcul, une règle placeholder exige une confirmation explicite
 * (auditée), et le contrat de calcul expose source + date de vérification.
 */
class ComplianceConfidenceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Employee $manager;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    // ── Contrat : bloc compliance structuré ───────────────────────────────

    public function test_contract_exposes_compliance_block_with_source_and_verification_date(): void
    {
        $presenter = new PayrollCalculationPresenter(
            (new PayrollCalculator)->rulesResolver()
        );
        $contract = $presenter->present('DZ', 60000.0);

        $this->assertSame('pilot', $contract['compliance']['level']);
        $this->assertSame($contract['confidence_level'], $contract['compliance']['level']);
        $this->assertSame('docs/payroll/DZ_COMPLIANCE.md', $contract['compliance']['source']);
        $this->assertArrayHasKey('warning', $contract['compliance']);
        $this->assertSame('payroll.compliance_warning_pilot', $contract['compliance']['warning_key']);
        $this->assertArrayHasKey('verification_date', $contract['compliance']);

        // Second pays pilot : la même structure, niveau pilot.
        $pilot = $presenter->present('CI', 500000.0);
        $this->assertSame('pilot', $pilot['compliance']['level']);
        $this->assertStringContainsString('CI_COMPLIANCE', $pilot['compliance']['source']);
    }

    // ── Simulation (cotisations) : placeholder exige confirmation ─────────

    public function test_cotisation_simulation_placeholder_requires_acknowledgement(): void
    {
        Sanctum::actingAs($this->manager);

        // BJ est un membre CEDEAO en « placeholder » (aucune valeur légale).
        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.acknowledge_placeholder.0', __('payroll.placeholder_acknowledge_required', ['country' => 'BJ']));
    }

    public function test_cotisation_simulation_placeholder_accepted_with_acknowledgement(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
            'acknowledge_placeholder' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.contract.compliance.level', 'placeholder');
        $response->assertJsonPath('data.contract.compliance.warning_key', 'payroll.compliance_warning_placeholder');
    }

    public function test_placeholder_acknowledgement_is_audited(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
            'acknowledge_placeholder' => true,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'placeholder_warning_acknowledged',
        ]);

        /** @var AuditLog $log */
        $log = AuditLog::query()->where('action', 'placeholder_warning_acknowledged')->firstOrFail();
        $this->assertSame('BJ', $log->new_values['country_code']);
        $this->assertSame('placeholder', $log->new_values['confidence_level']);
        // Jamais de secrets/biométrie dans l'audit (#1874).
        $this->assertArrayNotHasKey('gross_salary', $log->new_values);
    }

    public function test_no_audit_when_placeholder_rejected(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'placeholder_warning_acknowledged',
        ]);
    }

    // ── Simulation dry-run (barème) : même garde ──────────────────────────

    public function test_payroll_simulation_placeholder_guard(): void
    {
        Sanctum::actingAs($this->manager);

        $rejected = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
        ]);
        $rejected->assertStatus(422);

        $accepted = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'BJ',
            'gross_salary' => 300000.0,
            'acknowledge_placeholder' => true,
        ]);
        $accepted->assertOk();
    }

    // ── Pilot : pas de garde, avertissement visible ───────────────────────
    // NB : aucun pays n'a encore le niveau 'production' (tous pilot ou
    // placeholder) — DZ est pilot tant que la validation experte complète
    // (DZ_COMPLIANCE.md §7) n'est pas actée.

    public function test_pilot_countries_do_not_require_acknowledgement(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'DZ',
            'gross_salary' => 60000.0,
        ])->assertOk()->assertJsonPath('data.contract.compliance.level', 'pilot');

        $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'CI',
            'gross_salary' => 500000.0,
        ])->assertOk()->assertJsonPath('data.contract.compliance.level', 'pilot');
    }

    // ── complianceWarning dérive du niveau (PA2-COUNTRY-006) ──────────────

    public function test_compliance_warning_derives_from_confidence_level(): void
    {
        $placeholder = new CedeaoPayrollRules('BJ');
        $pilot = new CedeaoPayrollRules('CI');

        $this->assertSame('placeholder', $placeholder->confidenceLevel());
        $this->assertStringContainsStringIgnoringCase('placeholder', $placeholder->complianceWarning());

        $this->assertSame('pilot', $pilot->confidenceLevel());
        $this->assertStringContainsStringIgnoringCase('pilot', $pilot->complianceWarning());
    }

    // ── i18n : les 4 catalogues portent les clés (PA2-I18N-007) ───────────

    #[DataProvider('localeProvider')]
    public function test_i18n_keys_present_in_all_locales(string $locale): void
    {
        $this->assertNotSame('payroll.placeholder_acknowledge_required', __(
            'payroll.placeholder_acknowledge_required',
            ['country' => 'BJ'],
            $locale
        ));
        $this->assertNotSame('payroll.compliance_warning_placeholder', __(
            'payroll.compliance_warning_placeholder',
            ['country' => 'BJ'],
            $locale
        ));
        $this->assertNotSame('payroll.compliance_warning_pilot', __(
            'payroll.compliance_warning_pilot',
            ['country' => 'CI'],
            $locale
        ));
        $this->assertNotSame('payroll.compliance_warning_production', __(
            'payroll.compliance_warning_production',
            ['country' => 'DZ'],
            $locale
        ));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'fr' => ['fr'],
            'en' => ['en'],
            'ar' => ['ar'],
            'tr' => ['tr'],
        ];
    }

    public function test_abstract_rules_compliance_defaults(): void
    {
        $rules = new class extends AbstractCountryRules
        {
            protected function defaultTaxSlabs(): array
            {
                return [];
            }

            // Stubs des méthodes abstraites (interface #1868) — le test ne
            // vérifie que les défauts de conformité (source + date).
            public function countryCode(): string
            {
                return 'XX';
            }

            public function currency(): string
            {
                return 'XOF';
            }

            public function minimumWage(): float
            {
                return 0.0;
            }

            /** @return list<array<string, mixed>> */
            public function socialContributions(): array
            {
                return [];
            }

            public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
            {
                return 0.0;
            }

            /** @return array{employee: float, employer: float} */
            public function calculateSocialCharges(float $grossSalary): array
            {
                return ['employee' => 0.0, 'employer' => 0.0];
            }

            public function timezone(): string
            {
                return 'UTC';
            }

            /** @return list<int> */
            public function weeklyRestDays(): array
            {
                return [0];
            }

            /** @return list<string> */
            public function supportedPayCycles(): array
            {
                return ['monthly'];
            }

            public function publicHolidaysSource(): string
            {
                return 'none';
            }

            public function confidenceLevel(): string
            {
                return 'placeholder';
            }

            public function language(): string
            {
                return 'fr';
            }

            public function overtimeThresholdWeeklyHours(): float
            {
                return 40.0;
            }

            /** @return list<array{up_to_hours: float|null, multiplier: float}> */
            public function overtimeRateTiers(): array
            {
                return [['up_to_hours' => null, 'multiplier' => 1.0]];
            }
        };

        $this->assertSame('docs/payroll/XX_COMPLIANCE.md', $rules->complianceSource());
        $this->assertNull($rules->verificationDate());
    }
}
