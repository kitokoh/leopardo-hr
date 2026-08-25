<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmitted;
use App\Listeners\NotifyTaxRateValidation;
use App\Modules\Notification\Domain\Models\AppNotification;
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
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Test',
            'email' => 'sa-rate-validation@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

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

    public function test_full_workflow_draft_submit_approve_closes_previous_window(): void
    {
        // Ancienne ligne active (même tranche), fenêtre ouverte depuis 2025.
        TaxSlab::create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'status' => TaxSlab::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->manager);

        // 1. Le comptable crée une proposition (draft), effective à partir de 2026.
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

        // 3. Approbation par le platform_admin (pending → active).
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE);

        /** @var TaxSlab $approved */
        $approved = TaxSlab::query()->findOrFail($id);
        $this->assertSame(TaxSlab::STATUS_ACTIVE, $approved->status);
        $this->assertSame($this->superAdmin->id, $approved->validated_by);
        $this->assertNotNull($approved->validated_at);

        // Issue #1923 (PA2-ARCH-004) — l'ancienne ligne n'est PLUS flippée
        // `superseded` : sa fenêtre d'effet est fermée au 31/12/2025 et elle
        // reste `active` pour préserver la rétroactivité.
        /** @var TaxSlab $old */
        $old = TaxSlab::query()
            ->where('rate', 23.0)
            ->where('company_id', $this->company->id)
            ->firstOrFail();

        $this->assertSame(23.0, (float) $old->rate);
        $this->assertSame(TaxSlab::STATUS_ACTIVE, $old->status);
        $this->assertSame('2025-12-31', $old->effective_to?->toDateString());

        // 4. Recalcul historique : l'ancien taux s'applique avant 2026, le
        // nouveau à partir de 2026 (résolution asOf de AbstractCountryRules).
        $rules = new AlgeriaPayrollRules;

        $pastSlabs = $rules->asOf('2025-06-01')->forCompany((string) $this->company->id)->taxSlabs();
        $this->assertCount(1, $pastSlabs);
        $this->assertSame(23.0, (float) $pastSlabs[0]['rate']);

        $currentSlabs = $rules->asOf('2026-06-01')->forCompany((string) $this->company->id)->taxSlabs();
        $this->assertCount(1, $currentSlabs);
        $this->assertSame(26.0, (float) $currentSlabs[0]['rate']);

        // 5. La ligne approuvée est maintenant utilisée dans les calculs.
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
        /** @var TaxSlab $freshSlab */
        $freshSlab = $slab->fresh();
        $this->assertSame(TaxSlab::STATUS_DRAFT, $freshSlab->status);

        // Issue #1923 — un rejet n'est PAS une validation : validated_by et
        // validated_at restent NULL (seule l'approbation tamponne « validé »).
        $this->assertNull($freshSlab->validated_by);
        $this->assertNull($freshSlab->validated_at);
        $this->assertNull($freshSlab->submitted_by, 'le rejet ne renseigne pas submitted_by sur une ligne créée sans soumission');
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

    public function test_listener_is_registered_and_runs_on_submit(): void
    {
        // Issue #1923 (écart 1) — le listener NotifyTaxRateValidation était
        // mort : les méthodes handleTaxRate* n'étaient enregistrées nulle
        // part (event discovery désactivée dans ce repo). Il doit être
        // câblé pour les 3 événements, en notation `Class@méthode`.
        $raw = app('events')->getRawListeners();

        $this->assertContains(NotifyTaxRateValidation::class.'@handleTaxRateSubmitted', $raw[TaxRateSubmitted::class] ?? []);
        $this->assertContains(NotifyTaxRateValidation::class.'@handleTaxRateApproved', $raw[TaxRateApproved::class] ?? []);
        $this->assertContains(NotifyTaxRateValidation::class.'@handleTaxRateRejected', $raw[TaxRateRejected::class] ?? []);

        // La table `app_notifications` est créée par la migration tenant
        // `2026_08_15_000001_create_app_notifications_table` (issue #2398,
        // dette #1813) — le schéma manuel local au test n'est plus nécessaire
        // (la chaîne listener → tenant → notification in-app est prouvée
        // de bout en bout avec la table réelle).
        // Un platform_admin existe : la soumission déclenche l'email best-effort.
        $createdSuperAdmin = new SuperAdmin([
            'name' => 'Admin Notif',
            'email' => 'sa-notif@leopardo-rh.com',
        ]);
        $createdSuperAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($this->manager);

        $created = $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche notif',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 20,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $this->putJson("/api/v1/tax-slabs/{$created['id']}/submit")->assertOk();

        // Approbation par le platform_admin : le listener résout le tenant du
        // soumissionnaire (company_id de la ligne) et écrit une notification
        // in-app dans le schéma tenant — preuve de bout en bout que le
        // listener (et sa restauration de search_path) fonctionne.
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson("/api/v1/admin/rate-validation/tax_slabs/{$created['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TaxSlab::STATUS_ACTIVE);

        $notification = AppNotification::query()
            ->where('user_id', $this->manager->id)
            ->where('type', 'tax_rate_validation')
            ->first();

        $this->assertNotNull($notification, 'le listener doit notifier le soumissionnaire in-app');
        $this->assertSame((int) $created['id'], (int) $notification->data['record_id']);
        $this->assertSame('tax_slabs', $notification->data['table']);
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
