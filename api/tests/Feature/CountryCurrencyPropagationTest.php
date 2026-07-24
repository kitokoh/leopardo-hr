<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COUNTRY-003: DZD must never leak into runtime API responses for
 * companies configured with a different currency. 'DZD' is only allowed
 * as a last-resort technical fallback when no tenant/company currency
 * can be resolved at all.
 */
class CountryCurrencyPropagationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_expense_claim_created_without_currency_uses_company_currency(): void
    {
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/expense-claims', [
            'title' => 'Repas client Paris',
            'items' => [[
                'category' => 'meals',
                'description' => 'Restaurant',
                'amount' => 42.5,
                'date' => now()->subDay()->toDateString(),
            ]],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.currency', 'EUR');
    }

    public function test_salary_advance_resource_reports_company_currency_not_dzd_fallback(): void
    {
        $company = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        // Legacy row created before the PA2-PAY-002 currency snapshot
        // existed: no `currency` value stored, so the resource must fall
        // back to the owning company's current currency.
        SalaryAdvance::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 1500,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/salary-advances');

        $response->assertOk();
        $response->assertJsonPath('data.0.currency', 'MAD');
    }

    /**
     * PA2-PAY-002: a salary advance must keep the currency that was active
     * on the tenant when it was created, even after the company's currency
     * setting is changed afterwards (e.g. a company migrating from MAD to
     * EUR must not have its historical advance receipts silently reported
     * in the new currency).
     */
    public function test_salary_advance_keeps_creation_time_currency_after_company_currency_changes(): void
    {
        $company = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/salary-advances', [
            'amount' => 2000,
            'reason' => 'Car repair',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.currency', 'MAD');

        $advanceId = $response->json('data.id');

        // Company later migrates to a new currency.
        $company->update(['currency' => 'EUR']);

        $again = $this->getJson("/api/v1/salary-advances/{$advanceId}");

        $again->assertOk();
        $again->assertJsonPath('data.currency', 'MAD');
    }
}
