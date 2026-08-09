<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PA2-PAY-011 — company-configurable pay cycle rule (journalier/hebdomadaire/
 * mensuel, pay day, week start) exposed via GET/PUT /payroll/cycle-settings.
 */
class PayrollCycleSettingsTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    public function test_manager_can_read_default_cycle_settings(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll/cycle-settings');

        $response->assertOk()->assertJson([
            'data' => [
                'pay_cycle' => 'monthly',
                'pay_day' => 1,
                'week_start' => 1,
            ],
        ]);
    }

    public function test_manager_can_update_cycle_to_weekly(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/payroll/cycle-settings', [
            'pay_cycle' => 'weekly',
            'week_start' => 1,
        ]);

        $response->assertOk()->assertJson([
            'data' => [
                'pay_cycle' => 'weekly',
                'week_start' => 1,
            ],
        ]);

        $company->refresh();
        $this->assertSame('weekly', $company->metadata['payroll']['pay_cycle'] ?? null);
    }

    public function test_updated_cycle_is_reflected_immediately_in_current_cycle(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/payroll/cycle-settings', ['pay_cycle' => 'daily'])->assertOk();

        $current = $this->getJson('/api/v1/payroll/cycles/current');

        $current->assertOk();
        $this->assertSame(
            now($company->timezone ?: 'UTC')->toDateString(),
            $current->json('period_start')
        );
        $this->assertSame(
            now($company->timezone ?: 'UTC')->toDateString(),
            $current->json('period_end')
        );
    }

    public function test_partial_update_only_changes_provided_fields(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/payroll/cycle-settings', [
            'pay_cycle' => 'monthly',
            'pay_day' => 15,
        ])->assertOk();

        $response = $this->putJson('/api/v1/payroll/cycle-settings', [
            'week_start' => 3,
        ]);

        $response->assertOk()->assertJson([
            'data' => [
                'pay_cycle' => 'monthly',
                'pay_day' => 15,
                'week_start' => 3,
            ],
        ]);
    }

    public function test_invalid_pay_cycle_is_rejected(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/v1/payroll/cycle-settings', [
            'pay_cycle' => 'yearly',
        ]);

        $response->assertStatus(422);
    }

    public function test_employee_cannot_read_or_update_cycle_settings(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/payroll/cycle-settings')->assertStatus(403);
        $this->putJson('/api/v1/payroll/cycle-settings', ['pay_cycle' => 'weekly'])->assertStatus(403);
    }
}
