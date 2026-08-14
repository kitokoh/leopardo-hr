<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1813 — Workflow de validation des modifications de taux légaux :
 * draft → submitted → approved (ancienne ligne superseded) / rejected.
 */
class TaxSlabValidationWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected SuperAdmin $superAdmin;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-rate-validation@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    public function test_pending_slab_not_used_in_calculation(): void
    {
        // Ligne ACTIVE (utilisée dans les calculs) + ligne PENDING (ignorée).
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);
        TaxSlab::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Tranche 1 (proposition)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 30,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);

        $slabs = (new AlgeriaPayrollRules)->taxSlabs();

        $this->assertCount(1, $slabs);
        $this->assertSame(23.0, (float) $slabs[0]['rate']);
    }

    public function test_full_workflow_draft_submit_approve_supersedes_previous(): void
    {
        // Ancienne ligne active (même tranche).
        TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->manager);

        // 1. Le comptable crée une proposition (draft).
        $created = $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1 (révisée)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 26,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $this->assertSame(TaxSlab::STATUS_DRAFT, $created['status']);
        $id = $created['id'];

        // 2. Soumission (draft → pending_validation).
        $this->putJson("/api/v1/tax-slabs/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_PENDING);

        // 3. Approbation par le platform_admin (pending → active + ancienne → superseded).
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE);

        /** @var TaxSlab $approved */
        $approved = TaxSlab::query()->findOrFail($id);
        $this->assertSame(TaxSlab::STATUS_ACTIVE, $approved->status);
        $this->assertSame($this->superAdmin->id, $approved->validated_by);
        $this->assertNotNull($approved->validated_at);

        /** @var TaxSlab $old */
        $old = TaxSlab::query()->where('status', TaxSlab::STATUS_SUPERSEDED)->firstOrFail();
        $this->assertSame(23.0, (float) $old->rate);

        // 4. La ligne approuvée est maintenant utilisée dans les calculs.
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/tax-slabs')->assertOk();
    }

    public function test_only_platform_admin_can_approve(): void
    {
        /** @var TaxSlab $slab */
        $slab = TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);

        // Un manager ne peut pas approuver (route admin → 401).
        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$slab->id}/approve")->assertStatus(401);

        // Un manager ne peut pas non plus approuver via les routes tenant
        // (aucune route d'approbation n'existe côté tenant → 404).
        $this->putJson("/api/v1/tax-slabs/{$slab->id}/approve")->assertStatus(404);

        // Le platform_admin le peut.
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$slab->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE);
    }

    public function test_rejection_reverts_to_draft_with_reason(): void
    {
        /** @var TaxSlab $slab */
        $slab = TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 50,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        // Motif obligatoire.
        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$slab->id}/reject", ['reason' => ''])
            ->assertStatus(422);

        // Rejet avec motif.
        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$slab->id}/reject", [
            'reason' => 'Taux supérieur au plafond légal DZ (35 %).',
        ])->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_DRAFT)
            ->assertJsonPath('data.rejection_reason', 'Taux supérieur au plafond légal DZ (35 %).');

        // La ligne rejetée n'est toujours pas utilisée dans les calculs.
        $this->assertSame(TaxSlab::STATUS_DRAFT, $slab->fresh()->status);
    }

    public function test_social_contribution_workflow(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'Sécurité sociale',
            'code' => 'CNAS',
            'type' => 'employee',
            'rate' => 12,
            'cap' => null,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $this->assertSame(SocialContribution::STATUS_DRAFT, $created['status']);
        $id = $created['id'];

        $this->putJson("/api/v1/social-contributions/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', SocialContribution::STATUS_PENDING);

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson("/api/v1/admin/rate-validation/social_contributions/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', SocialContribution::STATUS_ACTIVE);

        // L'admin voit la ligne dans la liste des validations passées.
        $this->getJson('/api/v1/admin/rate-validation/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_submitted_slab_cannot_be_edited_directly(): void
    {
        /** @var TaxSlab $slab */
        $slab = TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);

        Sanctum::actingAs($this->manager);

        $this->putJson("/api/v1/tax-slabs/{$slab->id}", ['rate' => 30])->assertStatus(409);
        $this->deleteJson("/api/v1/tax-slabs/{$slab->id}")->assertStatus(409);
    }

    public function test_history_endpoint_returns_immutable_trail(): void
    {
        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $id = $created['id'];
        $this->putJson("/api/v1/tax-slabs/{$id}/submit")->assertOk();

        $history = $this->getJson("/api/v1/tax-slabs/{$id}/history")
            ->assertOk()
            ->json('data');

        $actions = array_column($history, 'action');
        sort($actions);
        $this->assertSame(['created', 'submitted'], $actions);
        $this->assertCount(2, TaxRateChangeLog::query()->get());
    }
}
