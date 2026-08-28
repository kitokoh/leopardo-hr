<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC07 (#5883) — Invariants du cycle de vie PayrollRun + isolation
 * cross-tenant.
 *
 * Verrouille : (1) un manager ne voit/jamais ne calcule sur les runs d'un
 * autre tenant (404 fail-closed) ; (2) un run `locked` ne peut pas être
 * annulé (422) ; (3) le déverrouillage exige une raison (422).
 */
class PayrollRunInvariantTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->tenant('tenant-a', 'a.test');
        $this->companyB = $this->tenant('tenant-b', 'b.test');
        $this->managerA = $this->manager($this->companyA, 'a.test');
    }

    private function tenant(string $slug, string $domain): Company
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        return $company;
    }

    private function manager(Company $company, string $domain): Employee
    {
        $manager = new Employee([
            'email' => 'manager@'.$domain,
            'first_name' => 'Mgr',
            'last_name' => 'A',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return $manager;
    }

    private function makeRun(Company $company, string $status = 'draft'): PayrollRun
    {
        return PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'DZ',
            'status' => $status,
        ]);
    }

    public function test_manager_cannot_show_other_tenants_run(): void
    {
        $runB = $this->makeRun($this->companyB);

        Sanctum::actingAs($this->managerA);

        $this->getJson("/api/v1/payroll-runs/{$runB->id}")->assertNotFound();
    }

    public function test_manager_cannot_calculate_other_tenants_run(): void
    {
        $runB = $this->makeRun($this->companyB);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$runB->id}/calculate")->assertNotFound();
    }

    public function test_locked_run_cannot_be_cancelled(): void
    {
        $run = $this->makeRun($this->companyA, 'locked');

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', __('errors.PAYROLL_RUN_CANCEL_NOT_ALLOWED'));
    }

    public function test_unlock_requires_reason(): void
    {
        $run = $this->makeRun($this->companyA, 'locked');

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/payroll-runs/{$run->id}/unlock")->assertStatus(422);
    }
}
