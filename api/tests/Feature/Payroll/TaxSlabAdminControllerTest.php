<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1923 — CRUD admin des barèmes fiscaux nationaux (#1814) :
 * audit trail `tax_rate_change_log`, transactions et garde d'unicité
 * (pas de doublon ACTIF sur la même identité / fenêtre d'effet).
 */
class TaxSlabAdminControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Tax Slabs',
            'email' => 'sa-tax-slabs-admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        $this->superAdmin = $superAdmin;

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
    }

    public function test_admin_store_writes_audit_entry(): void
    {
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        $entry = TaxRateChangeLog::query()
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->where('action', TaxRateChangeLog::ACTION_CREATED)
            ->first();

        $this->assertNotNull($entry, 'la création admin doit écrire une entrée d\'audit');
        $this->assertSame('platform_admin', $entry->actor_role);
        $this->assertSame($this->superAdmin->id, $entry->actor_id);
        $this->assertSame('active', $entry->new_value['status']);
    }

    public function test_admin_update_and_destroy_write_audit_entries(): void
    {
        $created = $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'MA',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 40000,
            'rate' => 10,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $id = $created['id'];

        $this->putJson("/api/v1/admin/tax-slabs/{$id}", ['rate' => 12])
            ->assertOk()
            ->assertJsonPath('data.rate', 12);

        $updated = TaxRateChangeLog::query()
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->where('record_id', $id)
            ->where('action', TaxRateChangeLog::ACTION_UPDATED)
            ->first();

        $this->assertNotNull($updated, 'la mise à jour admin doit être tracée');
        $this->assertSame(10.0, (float) ($updated->previous_value['rate'] ?? 0.0));
        $this->assertSame(12.0, (float) $updated->new_value['rate']);

        $this->deleteJson("/api/v1/admin/tax-slabs/{$id}")->assertStatus(204);

        $deleted = TaxRateChangeLog::query()
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->where('record_id', $id)
            ->where('action', TaxRateChangeLog::ACTION_DELETED)
            ->first();

        $this->assertNotNull($deleted, 'la suppression admin doit être tracée');
        $this->assertSame(12.0, (float) $deleted->new_value['rate']);
        // Scopé au pays : la migration backfill BF (000012) laisse une ligne nationale BF.
        $this->assertSame(0, TaxSlab::query()->where('country_code', 'MA')->count());
    }

    public function test_admin_store_rejects_overlapping_active_duplicate(): void
    {
        // Première ligne active, fenêtre ouverte.
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        // Même identité (pays/min/max), fenêtre qui CHEVAUCHE → 422.
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1 (doublon)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 30,
            'fixed_deduction' => 0,
            'effective_from' => '2026-06-01',
        ])->assertStatus(422);

        $this->assertSame(1, TaxSlab::query()->where('country_code', 'DZ')->count(), 'aucun doublon actif ne doit être créé');
    }

    public function test_admin_store_accepts_non_overlapping_window(): void
    {
        // Ligne active fermée au 31/12/2025.
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1 (2025)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ])->assertCreated();

        // Nouvelle ligne à partir de 2026 : fenêtres disjointes → acceptée.
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1 (2026)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 30,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        $this->assertSame(2, TaxSlab::query()->where('country_code', 'DZ')->count());
    }

    public function test_admin_reset_defaults_is_transactional_and_audited(): void
    {
        // Une ligne nationale DZ pré-existante.
        $this->postJson('/api/v1/admin/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche obsolète',
            'min_amount' => 0,
            'max_amount' => 999999999,
            'rate' => 1,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/tax-slabs/reset-defaults', ['country_code' => 'DZ'])
            ->assertOk()
            ->assertJsonPath('data.country_code', 'DZ');

        // Les lignes par défaut du moteur DZ ont été recréées.
        $this->assertGreaterThan(1, TaxSlab::query()->whereNull('company_id')->where('country_code', 'DZ')->count());

        // Suppressions + créations tracées dans l'audit trail.
        $deleted = TaxRateChangeLog::query()
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->where('action', TaxRateChangeLog::ACTION_DELETED)
            ->count();

        $created = TaxRateChangeLog::query()
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->where('action', TaxRateChangeLog::ACTION_CREATED)
            ->count();

        $this->assertSame(1, $deleted, 'la ligne obsolète supprimée doit être tracée');
        $this->assertSame(1 + TaxSlab::query()->whereNull('company_id')->where('country_code', 'DZ')->count(), $created, 'création initiale + lignes reset tracées');
    }
}
