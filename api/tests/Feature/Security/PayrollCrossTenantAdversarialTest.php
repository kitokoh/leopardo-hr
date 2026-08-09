<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-19 (#1549) : tests adversarial multi-tenant sur les
 * surfaces paie restantes (cycles, documents de paiement par run).
 *
 * Chaque test : 2 tenants, tentative croisée d'un manager tenant B sur une
 * ressource tenant A → 404 (jamais de fuite ni d'erreur révélatrice).
 */
class PayrollCrossTenantAdversarialTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $a */
        $a = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $a;
        /** @var Company $b */
        $b = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = $b;
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $b->id]);
        $this->managerB = $managerB;
    }

    private function makeRunA(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->companyA->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'calculated',
        ]);

        /** @var Employee $empA */
        $empA = Employee::factory()->create(['company_id' => $this->companyA->id]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->companyA->id,
            'employee_id' => $empA->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 60000,
            'net_salary' => 48000,
            'status' => 'calculated',
        ]);

        return $run;
    }

    public function test_cross_tenant_payroll_run_payment_documents_are_forbidden(): void
    {
        $run = $this->makeRunA();
        PaymentDocument::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => Employee::query()->where('company_id', $this->companyA->id)->value('id'),
            'document_type' => PaymentDocument::TYPE_PAYROLL_SUMMARY,
            'status' => PaymentDocument::STATUS_PENDING,
            'payroll_run_id' => $run->id,
            'requested_by' => $this->managerB->id,
        ]);

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/payment-documents")->assertStatus(404);
    }

    public function test_cross_tenant_payroll_run_export_is_forbidden(): void
    {
        $run = $this->makeRunA();

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/export")->assertStatus(404);
    }

    public function test_cross_tenant_payroll_run_pay_slips_are_forbidden(): void
    {
        $run = $this->makeRunA();

        Sanctum::actingAs($this->managerB);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/pay-slips")->assertStatus(404);
    }

    public function test_cross_tenant_send_slips_is_forbidden(): void
    {
        $run = $this->makeRunA();

        Sanctum::actingAs($this->managerB);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/send-slips")->assertStatus(404);
    }

    public function test_cross_tenant_bulk_pay_is_forbidden(): void
    {
        $run = $this->makeRunA();

        Sanctum::actingAs($this->managerB);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/bulk-pay")->assertStatus(404);
    }
}
