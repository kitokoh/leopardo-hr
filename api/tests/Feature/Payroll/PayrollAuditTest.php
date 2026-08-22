<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Expectation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1874 — audit et observabilité de chaque calcul de paie.
 *
 * Couvre les critères d'acceptation : audit créé à chaque run de paie et
 * chaque simulation, identifiant de corrélation exposé (réponse + run +
 * logs), isolation tenant stricte (audit du tenant A invisible pour B),
 * RBAC (manager principal/RH + platform admin ; 403 pour l'employé lambda
 * et le manager non autorisé), absence de secrets dans les logs et les
 * snapshots d'audit, reproduction du contexte via GET /payroll/audit/{id}.
 */
class PayrollAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = $companyB;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create([
            'company_id' => $companyA->id,
            // Contrat antérieur à la période d’audit : aucun prorata aléatoire.
            'contract_start' => '2026-01-01',
            'contract_end' => null,
        ]);
        $this->managerA = $managerA;
    }

    /**
     * Seed minimal d'un run calculable (structure + employé ancré avant la
     * période, même pattern que CountryIsolationMatrixTest).
     */
    private function seedCalculableRun(Company $company, float $base = 60000.0): PayrollRun
    {
        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => "Grille audit {$base}",
            'base_salary' => $base,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => $base,
            // Contrat ancré avant la période : évite le prorata aléatoire.
            'contract_start' => '2026-01-01',
            'contract_end' => null,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        return $run;
    }

    public function test_run_calculation_creates_audit_entry_with_correlation_id(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();

        $run->refresh();
        $this->assertNotNull($run->correlation_id, 'Le run doit porter un correlation_id.');

        $audit = PayrollCalculationAudit::query()->where('correlation_id', $run->correlation_id)->first();
        $this->assertNotNull($audit, 'Un enregistrement d\'audit doit être créé à chaque run.');

        // Contexte conservé pour reproduction : pays, période, version de règles.
        $this->assertSame('DZ', $audit->country_code);
        $this->assertSame('2026-07-01', $audit->period_start?->toDateString());
        $this->assertSame('2026-07-31', $audit->period_end?->toDateString());
        $this->assertNotEmpty($audit->rules_version);
        $this->assertNotEmpty($audit->rules_identifier);
        $this->assertSame(PayrollCalculationAudit::STATUS_SUCCESS, $audit->status);

        // Acteur : le manager qui a déclenché le calcul.
        $this->assertSame(PayrollCalculationAudit::ACTOR_USER, $audit->actor_type);
        $this->assertSame($this->managerA->id, $audit->actor_id);

        // Agrégats uniquement (jamais de salaires individuels). Le manager et
        // l'employé seedé reçoivent chacun un bulletin (repli structure
        // d'entreprise par défaut) → 2 bulletins de 60 000.
        $this->assertSame(2, (int) ($audit->input_snapshot['employee_count'] ?? 0));
        $this->assertSame(120000.0, (float) ($audit->result_snapshot['total_gross'] ?? 0.0));
        $this->assertSame(2, (int) ($audit->result_snapshot['employee_count'] ?? 0));
        $this->assertGreaterThan(0.0, (float) ($audit->result_snapshot['total_net'] ?? 0.0));
        $this->assertGreaterThan(0.0, (float) ($audit->result_snapshot['total_employer_cost'] ?? 0.0));
    }

    public function test_simulations_expose_correlation_id_and_create_audit(): void
    {
        Sanctum::actingAs($this->managerA);

        // POST /cotisation-simulation
        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertOk();

        $correlationId = $response->json('data.correlation_id');
        $this->assertIsString($correlationId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $correlationId);

        $audit = PayrollCalculationAudit::query()->where('correlation_id', $correlationId)->first();
        $this->assertNotNull($audit, 'La simulation doit créer un enregistrement d\'audit.');
        $this->assertSame(PayrollCalculationAudit::STATUS_SUCCESS, $audit->status);
        $this->assertSame('DZ', $audit->country_code);
        $this->assertSame(60000.0, (float) ($audit->input_snapshot['gross_salary'] ?? 0.0));
        $this->assertSame(47558.0, (float) ($audit->result_snapshot['net_salary'] ?? 0.0));

        // POST /payroll/simulate
        $response2 = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertOk();

        $correlationId2 = $response2->json('data.correlation_id');
        $this->assertIsString($correlationId2);
        $this->assertNotSame($correlationId, $correlationId2);
        $this->assertDatabaseHas('payroll_calculation_audits', ['correlation_id' => $correlationId2]);
    }

    public function test_request_correlation_header_echoes_and_links_audit(): void
    {
        Sanctum::actingAs($this->managerA);

        $response = $this->withHeader('X-Correlation-ID', '11111111-2222-3333-4444-555555555555')
            ->postJson('/api/v1/payroll/simulate', [
                'country_code' => 'DZ',
                'gross_salary' => 60000,
            ])->assertOk();

        // Le header est échoé en réponse (RequestIdMiddleware) et l'audit
        // reprend le MÊME identifiant : requête → job → résultat traçables.
        $this->assertSame('11111111-2222-3333-4444-555555555555', $response->headers->get('X-Correlation-ID'));
        $this->assertSame('11111111-2222-3333-4444-555555555555', $response->headers->get('X-Request-Id'));

        $audit = PayrollCalculationAudit::query()->where('correlation_id', '11111111-2222-3333-4444-555555555555')->first();
        $this->assertInstanceOf(PayrollCalculationAudit::class, $audit);
        $this->assertSame(PayrollCalculationAudit::STATUS_SUCCESS, $audit->status);
    }

    public function test_audit_show_returns_reproduction_context(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();

        $run->refresh();
        $correlationId = (string) $run->correlation_id;

        $response = $this->getJson("/api/v1/payroll/audit/{$correlationId}")->assertOk();

        $data = $response->json('data');
        $this->assertSame($correlationId, $data['correlation_id']);
        $this->assertSame('DZ', $data['country_code']);
        $this->assertSame('2026-07-01', $data['period_start']);
        $this->assertSame('2026-07-31', $data['period_end']);
        $this->assertNotEmpty($data['rules_version']);
        $this->assertNotEmpty($data['rules_identifier']);
        $this->assertSame('success', $data['status']);
        $this->assertSame('user', $data['actor']['type']);
        $this->assertArrayHasKey('input', $data);
        $this->assertArrayHasKey('result', $data);
    }

    public function test_run_calculated_outside_http_request_is_audited_as_job(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        // Aucun utilisateur authentifié (équivalent ProcessPayrollBatchJob /
        // commande) : l'acteur de l'audit doit être `job`.
        (new PayrollCalculator)->calculateRun($run);

        $run->refresh();
        $this->assertNotNull($run->correlation_id);

        $audit = PayrollCalculationAudit::query()->where('correlation_id', $run->correlation_id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(PayrollCalculationAudit::ACTOR_JOB, $audit->actor_type);
        $this->assertNull($audit->actor_id);
        $this->assertSame(PayrollCalculationAudit::STATUS_SUCCESS, $audit->status);
    }

    public function test_tenant_isolation_audit_of_company_a_invisible_to_company_b(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        Sanctum::actingAs($this->managerA);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();
        $correlationId = (string) $run->refresh()->correlation_id;

        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $this->companyB->id]);

        Sanctum::actingAs($managerB);

        // La liste du tenant B ne contient aucun audit du tenant A.
        $this->getJson('/api/v1/payroll/audit')->assertOk()->assertJsonCount(0, 'data');

        // La consultation directe d'un audit du tenant A est un 404 (pas de fuite).
        $this->getJson("/api/v1/payroll/audit/{$correlationId}")->assertNotFound();
    }

    public function test_rbac_employee_and_unauthorized_manager_are_forbidden(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        Sanctum::actingAs($this->managerA);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();
        $correlationId = (string) $run->refresh()->correlation_id;

        // Employé lambda : 403 (middleware api.manager + PayrollAuditPolicy).
        /** @var Employee $plainEmployee */
        $plainEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        Sanctum::actingAs($plainEmployee);
        $this->getJson('/api/v1/payroll/audit')->assertForbidden();
        $this->getJson("/api/v1/payroll/audit/{$correlationId}")->assertForbidden();

        // Manager comptable : pas dans le périmètre principal/RH → 403.
        /** @var Employee $comptable */
        $comptable = Employee::factory()->manager()->create([
            'company_id' => $this->companyA->id,
            'manager_role' => 'comptable',
        ]);
        Sanctum::actingAs($comptable);
        $this->getJson('/api/v1/payroll/audit')->assertForbidden();

        // Manager RH : autorisé (périmètre de l'issue).
        /** @var Employee $managerRh */
        $managerRh = Employee::factory()->managerRh()->create(['company_id' => $this->companyA->id]);
        Sanctum::actingAs($managerRh);
        $this->getJson('/api/v1/payroll/audit')->assertOk();
        $this->getJson("/api/v1/payroll/audit/{$correlationId}")->assertOk();
    }

    public function test_platform_admin_can_read_audit_cross_tenant(): void
    {
        $run = $this->seedCalculableRun($this->companyA);

        Sanctum::actingAs($this->managerA);
        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();
        $correlationId = (string) $run->refresh()->correlation_id;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Audit',
            'email' => 'sa-audit@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/admin/payroll/audit')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/payroll/audit?company_id='.$this->companyA->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/payroll/audit?company_id='.$this->companyB->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/payroll/audit/{$correlationId}")->assertOk();
    }

    public function test_audit_and_logs_contain_no_secrets(): void
    {
        // Spy sur le logger : tout ce qui est journalisé pendant les calculs
        // (y compris Log::withContext) est vérifié — aucun secret ne doit
        // apparaître dans les messages ni les contextes.
        $log = Log::spy();
        /** @var Expectation $channelExpectation */
        $channelExpectation = $log->shouldReceive('channel');
        $channelExpectation->andReturn($log);

        // Données sensibles présentes en base : mot de passe + références
        // biométriques marquées — elles ne doivent JAMAIS fuiter dans les
        // logs ni dans les snapshots d'audit.
        SalaryStructure::create([
            'company_id' => $this->companyA->id,
            'name' => 'Grille secrets',
            'base_salary' => 60000.0,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'contract_start' => '2026-01-01',
            'password_hash' => 'SUPERSECRETPASSWORD123',
            'biometric_face_reference_path' => 'biometric-face-SUPERSECRET123',
            'biometric_fingerprint_reference_path' => 'biometric-finger-SUPERSECRET123',
        ]);

        $run = PayrollRun::create([
            'company_id' => $this->companyA->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")->assertOk();
        $this->postJson('/api/v1/cotisation-simulation', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertOk();

        // Aucun secret dans les snapshots d'audit (agrégats uniquement).
        $audits = PayrollCalculationAudit::query()->get();
        $this->assertNotEmpty($audits);
        foreach ($audits as $audit) {
            $this->assertAuditFreeOfSecrets($audit);
        }

        // Corrélation : Log::withContext a été appelé avec un correlation_id.
        $log->shouldHaveReceived('withContext', [
            Mockery::on(static fn (array $ctx): bool => isset($ctx['correlation_id']) && is_string($ctx['correlation_id'])),
        ]);

        // Le lien log ↔ audit est journalisé (correlation_id dans le contexte).
        $log->shouldHaveReceived('info', [
            'payroll.audit.recorded',
            Mockery::on(static fn (array $ctx): bool => isset($ctx['correlation_id'], $ctx['status'])),
        ]);

        // Aucun secret dans AUCUN log (messages et contextes, tous niveaux).
        $secretMatcher = fn (mixed $value): bool => $this->containsAnySecretMarker($value);
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            $log->shouldNotHaveReceived($level, [Mockery::on($secretMatcher)]);
            $log->shouldNotHaveReceived($level, [Mockery::any(), Mockery::on($secretMatcher)]);
        }
        $log->shouldNotHaveReceived('withContext', [Mockery::on($secretMatcher)]);
    }

    /**
     * @return list<string>
     */
    private function secretMarkers(): array
    {
        return [
            'SUPERSECRETPASSWORD123',
            'biometric-face-SUPERSECRET123',
            'biometric-finger-SUPERSECRET123',
        ];
    }

    private function containsAnySecretMarker(mixed $value): bool
    {
        $serialized = strtolower((string) json_encode($value, JSON_THROW_ON_ERROR));

        foreach ($this->secretMarkers() as $marker) {
            if (str_contains($serialized, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    private function assertAuditFreeOfSecrets(PayrollCalculationAudit $audit): void
    {
        $payload = [
            'input' => $audit->input_snapshot,
            'result' => $audit->result_snapshot,
            'error_message' => $audit->error_message,
        ];

        $serialized = strtolower((string) json_encode($payload, JSON_THROW_ON_ERROR));

        foreach ($this->secretMarkers() as $marker) {
            $this->assertStringNotContainsString(strtolower($marker), $serialized);
        }
        $this->assertStringNotContainsString('password', $serialized, 'Aucune clé/valeur password dans les snapshots.');
        $this->assertStringNotContainsString('token', $serialized);
    }
}
