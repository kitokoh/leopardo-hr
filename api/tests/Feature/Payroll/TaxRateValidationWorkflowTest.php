<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADMIN-PAIE (issue #1813) — workflow de validation des modifications de taux
 * légaux : double signature (RH/comptable → platform admin) + audit trail
 * immuable (tax_rate_change_log, table append-only).
 *
 * Couvre : création en draft + log 'created', submit → pending_validation,
 * approve → active + ancienne version superseded, reject → draft (motif
 * obligatoire), exclusion des lignes non actives des calculs, isolation
 * tenant, immutabilité base (UPDATE/DELETE refusés par trigger), workflow
 * miroir sur les cotisations sociales.
 */
class TaxRateValidationWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        Sanctum::actingAs($this->manager);
    }

    private function superAdmin(): SuperAdmin
    {
        /** @var SuperAdmin $admin */
        $admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);

        return $admin;
    }

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');
    }

    private function createDraftSlab(string $effectiveFrom = '2026-01-01'): TaxSlab
    {
        $response = $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche test',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 10,
            'fixed_deduction' => 0,
            'effective_from' => $effectiveFrom,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', TaxSlab::STATUS_DRAFT);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::query()->findOrFail($response->json('data.id'));

        return $slab;
    }

    // ── Workflow barème fiscal ─────────────────────────────────────────────

    public function test_store_creates_draft_and_logs_created(): void
    {
        $slab = $this->createDraftSlab();

        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'tax_slabs',
            'record_id' => $slab->id,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_role' => 'manager',
        ]);
    }

    public function test_submit_moves_to_pending_and_logs_submitted(): void
    {
        $slab = $this->createDraftSlab();

        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_PENDING);

        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'tax_slabs',
            'record_id' => $slab->id,
            'action' => TaxRateChangeLog::ACTION_SUBMITTED,
            'actor_role' => 'manager',
        ]);
    }

    public function test_approve_activates_and_supersedes_older_version(): void
    {
        // Version 1 active (2025) — sera supplantée.
        TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Ancien barème',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 5,
            'fixed_deduction' => 0,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);

        // Version 2 : draft → submitted → approuvée (2026).
        $slab = $this->createDraftSlab('2026-01-01');
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertOk();

        $admin = $this->superAdmin();
        Sanctum::actingAs($admin, ['*'], 'super_admin_api');
        $this->putJson("/api/v1/platform/payroll/tax-slabs/{$slab->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE)
            ->assertJsonPath('data.validated_by', $admin->id);

        $this->assertDatabaseHas('tax_slabs', [
            'id' => $slab->id,
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);
        // Ancienne version → superseded.
        $this->assertDatabaseHas('tax_slabs', [
            'country_code' => 'DZ',
            'effective_from' => '2025-01-01',
            'status' => TaxSlab::STATUS_SUPERSEDED,
        ]);
        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'tax_slabs',
            'record_id' => $slab->id,
            'action' => TaxRateChangeLog::ACTION_APPROVED,
            'actor_role' => 'platform_admin',
        ]);
        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'tax_slabs',
            'action' => TaxRateChangeLog::ACTION_SUPERSEDED,
            'actor_role' => 'platform_admin',
        ]);
    }

    public function test_reject_returns_to_draft_with_reason(): void
    {
        $slab = $this->createDraftSlab();
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertOk();

        $this->actingAsSuperAdmin();
        // Motif obligatoire.
        $this->putJson("/api/v1/platform/payroll/tax-slabs/{$slab->id}/reject", ['reason' => ''])
            ->assertUnprocessable();

        $this->putJson("/api/v1/platform/payroll/tax-slabs/{$slab->id}/reject", ['reason' => 'Taux hors plafond légal'])
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_DRAFT)
            ->assertJsonPath('data.rejection_reason', 'Taux hors plafond légal');

        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'tax_slabs',
            'record_id' => $slab->id,
            'action' => TaxRateChangeLog::ACTION_REJECTED,
            'actor_role' => 'platform_admin',
            'reason' => 'Taux hors plafond légal',
        ]);
    }

    public function test_submit_non_draft_returns_422(): void
    {
        $slab = $this->createDraftSlab();
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertOk();

        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/tax-slabs/{$slab->id}/approve")->assertOk();

        // Ligne déjà active → submit refusé (422).
        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertUnprocessable();
    }

    public function test_cross_tenant_submit_blocked(): void
    {
        $slab = $this->createDraftSlab();

        /** @var Company $otherCompany */
        /** @var \App\Core\Tenant\Domain\Models\Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherManager */
        /** @var \App\Core\Auth\Domain\Models\Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertNotFound();
    }

    public function test_history_endpoint_returns_append_only_entries(): void
    {
        $slab = $this->createDraftSlab();
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertOk();

        $this->getJson("/api/v1/tax-slabs/{$slab->id}/history")
            ->assertOk()
            ->assertJsonCount(2, 'data') // created + submitted
            ->assertJsonPath('data.0.action', TaxRateChangeLog::ACTION_SUBMITTED);
    }

    public function test_pending_slab_is_ignored_by_calculations(): void
    {
        // Barème actif.
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Barème actif',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 5,
            'fixed_deduction' => 0,
            'effective_from' => '2020-01-01',
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);
        // Proposition en attente (ne doit PAS être utilisée par les calculs).
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Barème en attente',
            'min_amount' => 0,
            'max_amount' => null,
            'rate' => 99,
            'fixed_deduction' => 0,
            'effective_from' => '2020-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);

        $slabs = (new PayrollCalculator)->getRules('DZ')->taxSlabs();

        $this->assertCount(1, $slabs);
        $this->assertSame(5.0, $slabs[0]['rate']);
    }

    // ── Workflow cotisation sociale ────────────────────────────────────────

    public function test_social_contribution_full_workflow(): void
    {
        $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'CNAS salariale',
            'code' => 'CNAS_DZ_EMP_TEST',
            'type' => 'employee',
            'rate' => 9,
            'cap' => null,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.status', SocialContribution::STATUS_DRAFT);

        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::query()->where('code', 'CNAS_DZ_EMP_TEST')->firstOrFail();

        $this->putJson("/api/v1/social-contributions/{$contribution->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', SocialContribution::STATUS_PENDING);

        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/social-contributions/{$contribution->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', SocialContribution::STATUS_ACTIVE);

        $this->assertDatabaseHas('tax_rate_change_log', [
            'table_name' => 'social_contributions',
            'record_id' => $contribution->id,
            'action' => TaxRateChangeLog::ACTION_APPROVED,
            'actor_role' => 'platform_admin',
        ]);
    }

    // ── Liste pending plateforme ───────────────────────────────────────────

    public function test_platform_pending_list_requires_super_admin(): void
    {
        $slab = $this->createDraftSlab();
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/submit")->assertOk();

        // Un manager tenant n'accède pas aux routes plateforme.
        $this->getJson('/api/v1/platform/payroll/tax-slabs/pending')->assertUnauthorized();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/v1/platform/payroll/tax-slabs/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Immutabilité base ──────────────────────────────────────────────────

    public function test_change_log_is_append_only_at_database_level(): void
    {
        $slab = $this->createDraftSlab();

        $entryId = TaxRateChangeLog::query()
            ->where('table_name', 'tax_slabs')
            ->where('record_id', $slab->id)
            ->value('id');

        $this->expectException(QueryException::class);

        DB::table('tax_rate_change_log')
            ->where('id', $entryId)
            ->update(['action' => 'hacked']);
    }
}
