<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA #2332 — la garde `acknowledge_placeholder` (simulation, issue #1872)
 * doit aussi s'appliquer au calcul d'un run réel : un pays placeholder
 * (TG/BJ/NE/CF/TD/GQ) ne peut pas produire un run sans confirmation
 * explicite, et l'acceptation est auditée.
 */
class PayrollRunPlaceholderGateTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'TG', 'currency' => 'XOF']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    private function makeRun(string $countryCode = 'TG'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $countryCode,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        return $run;
    }

    public function test_calculate_rejects_placeholder_country_without_acknowledgement(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun('TG');

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['acknowledge_placeholder']);

        // Le run ne doit PAS être passé en 'calculating'.
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->fresh()->status);
    }

    public function test_calculate_with_acknowledgement_is_audited_and_proceeds(): void
    {
        Sanctum::actingAs($this->manager);

        $run = $this->makeRun('TG');

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate", [
            'acknowledge_placeholder' => true,
        ]);

        // Le run est vide (0 bulletin) → le flux existant répond 422
        // zero_slips, PAS la garde placeholder : la confirmation a bien passé.
        $response->assertStatus(422);
        $this->assertArrayNotHasKey('acknowledge_placeholder', $response->json('errors') ?? []);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'placeholder_warning_acknowledged',
        ]);

        $log = AuditLog::query()
            ->where('company_id', $this->company->id)
            ->where('action', 'placeholder_warning_acknowledged')
            ->firstOrFail();
        $this->assertSame('payroll_run_calculate', $log->metadata['context'] ?? null);
        $this->assertSame('TG', $log->metadata['country_code'] ?? null);
        $this->assertSame($run->id, $log->auditable_id);
    }

    public function test_calculate_for_non_placeholder_country_does_not_require_acknowledgement(): void
    {
        Sanctum::actingAs($this->manager);

        // DZ est pilot — aucun ack requis : la réponse ne doit pas porter la
        // validation acknowledge_placeholder (run vide → 422 zero_slips).
        $run = $this->makeRun('DZ');

        $response = $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate");

        $response->assertStatus(422);
        $this->assertArrayNotHasKey('acknowledge_placeholder', $response->json('errors') ?? []);
    }

    public function test_calculate_cross_tenant_returns_404_before_gate(): void
    {
        $run = $this->makeRun('TG');

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/calculate")
            ->assertNotFound();
    }
}
