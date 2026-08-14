<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADMIN-PAIE (#1813) — workflow de validation des modifications de taux
 * légaux : draft → pending_validation → active (approbation platform_admin)
 * ou → draft (rejet avec motif). Couvre le contrat API complet + le fait
 * qu'une ligne non-active n'entre JAMAIS dans les calculs de paie.
 */
class TaxSlabValidationWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
        $this->superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    private function actingAsManager(): void
    {
        Sanctum::actingAs($this->manager);
    }

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slabPayload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche IRG test',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_DRAFT,
        ], $overrides);
    }

    public function test_full_validation_workflow_flow(): void
    {
        $this->actingAsManager();

        // 1. Un RH/comptable crée un barème → il naît en `draft`.
        /** @var array{id: int} $created */
        $created = $this->postJson('/api/v1/tax-slabs', $this->slabPayload())
            ->assertCreated()
            ->json('data');

        $id = $created['id'];

        // 2. Soumission → pending_validation.
        $this->putJson("/api/v1/tax-slabs/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_PENDING_VALIDATION);

        // 3. Un platform_admin approuve → active.
        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE)
            ->assertJsonPath('data.validated_by', $this->superAdmin->id);

        // 4. L'audit trail immuable trace chaque transition.
        $this->actingAsManager();
        /** @var list<array{action: string, reason: string|null}> $history */
        $history = $this->getJson("/api/v1/tax-slabs/{$id}/history")->assertOk()->json('data');

        $this->assertSame(
            [TaxRateChangeLog::ACTION_APPROVED, TaxRateChangeLog::ACTION_SUBMITTED, TaxRateChangeLog::ACTION_CREATED],
            array_column($history, 'action'),
        );
    }

    public function test_pending_slab_not_used_in_calculation(): void
    {
        // Barème actif existant → utilisé par le moteur.
        TaxSlab::query()->create($this->slabPayload(['status' => TaxSlab::STATUS_ACTIVE]));

        $rules = (new AlgeriaPayrollRules)->forCompany($this->company->id);

        // Aucune ligne non-active : les tranches actives sont résolues.
        $activeSlabs = $rules->taxSlabs();
        $this->assertNotEmpty($activeSlabs);
        $this->assertSame(23.0, $activeSlabs[0]['rate']);

        // On soumet un nouveau barème (pending_validation).
        $this->actingAsManager();
        $this->postJson('/api/v1/tax-slabs', $this->slabPayload([
            'name' => 'Nouveau barème',
            'rate' => 30,
            'effective_from' => '2026-02-01',
        ]))->assertCreated();

        // Les deux barèmes créés par l'API sont des drafts (workflow #1813) :
        // récupérons le plus récent (rate=30) et soumettons-le.
        /** @var TaxSlab $pending */
        $pending = TaxSlab::query()->where('company_id', $this->company->id)->where('rate', 30)->latest('id')->firstOrFail();
        $this->assertSame(TaxSlab::STATUS_DRAFT, $pending->status);

        // Tant qu'il est draft, le moteur ignore le nouveau barème.
        $this->assertSame(23.0, (new AlgeriaPayrollRules)->forCompany($this->company->id)->taxSlabs()[0]['rate']);

        // Soumission → pending_validation : toujours ignoré par le moteur.
        $this->putJson("/api/v1/tax-slabs/{$pending->id}/submit")->assertOk();
        $this->assertSame(23.0, (new AlgeriaPayrollRules)->forCompany($this->company->id)->taxSlabs()[0]['rate']);

        // Approbation platform_admin → le nouveau taux devient effectif.
        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$pending->id}/approve")->assertOk();

        $resolved = (new AlgeriaPayrollRules)->forCompany($this->company->id)->taxSlabs();
        $this->assertSame(30.0, $resolved[0]['rate']);
    }

    public function test_approval_supersedes_previous_active_line(): void
    {
        $active = TaxSlab::query()->create($this->slabPayload(['status' => TaxSlab::STATUS_ACTIVE]));

        $this->actingAsManager();
        /** @var array{id: int} $created */
        $created = $this->postJson('/api/v1/tax-slabs', $this->slabPayload(['name' => 'Nouveau barème', 'effective_from' => '2026-02-01']))
            ->assertCreated()
            ->json('data');
        $this->putJson("/api/v1/tax-slabs/{$created['id']}/submit")->assertOk();

        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$created['id']}/approve")->assertOk();

        $active->refresh();
        $this->assertSame(TaxSlab::STATUS_SUPERSEDED, $active->status);

        // Le superseding est lui-même tracé dans le log immuable.
        $this->actingAsManager();
        /** @var list<array{action: string}> $history */
        $history = $this->getJson("/api/v1/tax-slabs/{$active->id}/history")->assertOk()->json('data');
        $this->assertSame(TaxRateChangeLog::ACTION_SUPERSEDED, $history[0]['action']);
    }

    public function test_only_platform_admin_can_approve(): void
    {
        $pending = TaxSlab::query()->create($this->slabPayload(['status' => TaxSlab::STATUS_PENDING_VALIDATION]));

        // Un RH/comptable n'a pas d'endpoint d'approbation côté tenant.
        $this->actingAsManager();
        $this->putJson("/api/v1/tax-slabs/{$pending->id}/approve")->assertNotFound();

        // Un manager ne peut pas non plus appeler les routes platform.
        $this->actingAsManager();
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$pending->id}/approve")->assertUnauthorized();

        // Un employé non-manager ne peut pas soumettre.
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);
        $this->putJson("/api/v1/tax-slabs/{$pending->id}/submit")->assertForbidden();
    }

    public function test_rejection_reverts_to_draft_with_reason(): void
    {
        $this->actingAsManager();
        /** @var array{id: int} $created */
        $created = $this->postJson('/api/v1/tax-slabs', $this->slabPayload())->assertCreated()->json('data');
        $this->putJson("/api/v1/tax-slabs/{$created['id']}/submit")->assertOk();

        // Rejet sans motif → 422.
        $this->actingAsSuperAdmin();
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$created['id']}/reject", ['reason' => ''])
            ->assertUnprocessable();

        // Rejet avec motif → retour en draft + motif tracé.
        $this->putJson("/api/v1/platform/payroll/tax-rates/tax-slabs/{$created['id']}/reject", ['reason' => 'Taux incohérent avec le barème officiel 2026'])
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_DRAFT);

        $slab = TaxSlab::query()->findOrFail($created['id']);
        $this->assertSame('Taux incohérent avec le barème officiel 2026', $slab->rejection_reason);

        // Le motif est visible dans l'historique immuable.
        $this->actingAsManager();
        /** @var list<array{action: string, reason: string|null}> $history */
        $history = $this->getJson("/api/v1/tax-slabs/{$created['id']}/history")->assertOk()->json('data');
        $this->assertSame(TaxRateChangeLog::ACTION_REJECTED, $history[0]['action']);
        $this->assertSame('Taux incohérent avec le barème officiel 2026', $history[0]['reason']);
    }

    public function test_cannot_submit_twice_or_submit_foreign_slab(): void
    {
        $this->actingAsManager();
        /** @var array{id: int} $created */
        $created = $this->postJson('/api/v1/tax-slabs', $this->slabPayload())->assertCreated()->json('data');
        $this->putJson("/api/v1/tax-slabs/{$created['id']}/submit")->assertOk();
        // Double soumission → conflit métier (422 attendu côté service).
        $this->putJson("/api/v1/tax-slabs/{$created['id']}/submit")->assertStatus(422);

        // Isolation tenant : un manager d'une autre société ne voit pas le barème.
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/tax-slabs/{$created['id']}/history")->assertNotFound();
    }
}
