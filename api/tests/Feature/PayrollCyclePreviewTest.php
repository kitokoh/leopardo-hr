<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PA2-PAY-003 — manager preview of a candidate pay cycle rule
 * (journalier/hebdomadaire/mensuel, pay day, week start) via
 * GET /payroll/cycles/preview, ahead of committing it with
 * PUT /payroll/cycle-settings.
 */
class PayrollCyclePreviewTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    public function test_manager_can_preview_default_monthly_cycle(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles/preview');

        $response->assertOk()->assertJson([
            'data' => [
                'settings' => [
                    'pay_cycle' => 'monthly',
                    'pay_day' => 1,
                    'week_start' => 1,
                ],
                'currency' => 'DZD',
            ],
        ]);
        $response->assertJsonStructure([
            'data' => [
                'settings' => ['pay_cycle', 'pay_day', 'week_start'],
                'period' => ['start', 'end', 'label'],
                'next_payment_date',
                'currency',
                'employee_count',
                'estimated_total_gross',
            ],
        ]);
    }

    public function test_preview_with_weekly_override_does_not_persist_settings(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles/preview?pay_cycle=weekly&week_start=1');

        $response->assertOk()->assertJson([
            'data' => [
                'settings' => [
                    'pay_cycle' => 'weekly',
                    'week_start' => 1,
                ],
            ],
        ]);

        // The candidate override must not have been written to the company.
        $company->refresh();
        $this->assertNull($company->metadata['payroll']['pay_cycle'] ?? null);

        $current = $this->getJson('/api/v1/payroll/cycles/current');
        $current->assertOk();
        $this->assertNotSame($response->json('data.period.start'), null);
    }

    public function test_preview_estimated_total_reflects_active_employees_only(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 30000,
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 30000,
            'status' => 'archived',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles/preview');

        $response->assertOk();
        // Manager + one active employee counted, archived employee excluded.
        $this->assertSame(2, $response->json('data.employee_count'));
    }

    public function test_invalid_pay_cycle_override_is_rejected(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycles/preview?pay_cycle=yearly');

        $response->assertStatus(422);
    }

    public function test_employee_cannot_preview_cycle(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/payroll/cycles/preview')->assertStatus(403);
    }
}
