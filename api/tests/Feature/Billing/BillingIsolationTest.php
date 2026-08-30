<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6252) — isolation tenant & RBAC billing.
 *
 * 1. Un manager ne voit/jamais modifie la souscription ou les factures d'une
 *    autre entreprise (404/403, pas de fuite).
 * 2. Un employé simple n'accède pas aux endpoints billing (403).
 * 3. Le super-admin plateforme passe par les contrats /platform/* — les
 *    routes tenant billing lui sont inaccessibles (401).
 * 4. Les commandes de recouvrement s'exécutent CROSS-tenant en console
 *    (traitent toutes les entreprises) sans jamais fuiter en surface API.
 */
class BillingIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function subscription(Company $company, string $plan = 'business'): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => SubscriptionStatus::Active->value,
            'payment_method' => 'stripe',
        ]);

        return $subscription;
    }

    // ── Isolation cross-tenant ──────────────────────────────────────────────

    public function test_manager_cannot_view_other_tenant_invoice(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $managerA = $this->manager($companyA);
        $subscriptionB = $this->subscription($companyB, 'enterprise');

        $invoiceB = Invoice::create([
            'company_id' => $companyB->id,
            'subscription_id' => $subscriptionB->id,
            'number' => 'LEO-B-1',
            'amount' => 299.00,
            'currency' => 'EUR',
            'total' => 299.00,
            'status' => InvoiceStatus::Sent->value,
            'due_date' => now()->addDays(10),
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/billing/invoices/{$invoiceB->id}")
            ->assertStatus(404);

        $this->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manager_sees_only_own_subscription(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $this->subscription($companyA, 'business');
        $this->subscription($companyB, 'enterprise');

        Sanctum::actingAs($this->manager($companyA));

        $this->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan', 'business')
            ->assertJsonPath('data.company_id', $companyA->id);
    }

    // ── RBAC ────────────────────────────────────────────────────────────────

    public function test_employee_cannot_access_billing_endpoints(): void
    {
        $company = $this->company();
        $this->subscription($company);

        Sanctum::actingAs($this->employee($company));

        $this->postJson('/api/v1/billing/subscription/upgrade', ['plan' => 'enterprise'])
            ->assertStatus(403);
        $this->postJson('/api/v1/billing/subscription/cancel')
            ->assertStatus(403);
        $this->getJson('/api/v1/billing/invoices')
            ->assertStatus(403);
    }

    public function test_super_admin_guard_cannot_use_tenant_billing_routes(): void
    {
        $company = $this->company();
        $this->subscription($company);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        // Le super-admin passe par /platform/* — jamais par les routes tenant.
        $this->getJson('/api/v1/billing/subscription')
            ->assertStatus(401);
    }

    // ── Commande console : cross-tenant en console, jamais en API ───────────

    public function test_enforce_delinquency_processes_all_companies_in_console(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $subA = $this->subscription($companyA);
        $subB = $this->subscription($companyB);
        $subA->update(['current_period_end' => now()->subDay()]);
        $subB->update(['current_period_end' => now()->subDay()]);

        Artisan::call('billing:enforce-delinquency');

        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subA->refresh()->status,
            'la commande console traite la company A'
        );
        self::assertSame(
            SubscriptionStatus::PastDue->value,
            $subB->refresh()->status,
            'la commande console traite AUSSI la company B (cross-tenant en console)'
        );
    }

    public function test_feature_flag_check_is_scoped_to_own_company_plan(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $this->subscription($companyA, 'pilot');
        $this->subscription($companyB, 'enterprise');

        Sanctum::actingAs($this->manager($companyA));

        // Le plan de B (enterprise) ne peut pas fuiter vers la lecture de A.
        $this->getJson('/api/v1/feature-flags/check/hr_automation')
            ->assertOk()
            ->assertJsonPath('data.plan', 'pilot');
    }
}
