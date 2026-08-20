<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1813 — Audit trail immuable des modifications de taux légaux.
 */
class TaxRateChangeLogTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_log_is_append_only(): void
    {
        $log = TaxRateChangeLog::create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 1,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
        ]);

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_log_delete_is_blocked(): void
    {
        $log = TaxRateChangeLog::create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 1,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
        ]);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_every_transition_creates_log_entry(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var SuperAdmin $admin */
        $admin = new SuperAdmin([
            'name' => 'Super Admin Test',
            'email' => 'sa-rate-log@leopardo-rh.com',
        ]);
        $admin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        /** @var TaxSlab $slab */
        $slab = TaxSlab::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_DRAFT,
        ]);

        $service = $this->app->make(TaxRateValidationService::class);

        // created (via création) + submitted + approved + superseded (ancienne ligne).
        $service->logCreated($slab, $manager);
        $service->submit($slab, $manager);
        $service->approve($slab, $admin);

        $entries = TaxRateChangeLog::query()
            ->where('record_id', $slab->id)
            ->where('table_name', TaxRateChangeLog::TABLE_TAX_SLABS)
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [
                TaxRateChangeLog::ACTION_CREATED,
                TaxRateChangeLog::ACTION_SUBMITTED,
                TaxRateChangeLog::ACTION_APPROVED,
            ],
            $entries->pluck('action')->all(),
        );

        // previous_value/new_value sont bien remplis (avant/après).
        /** @var TaxRateChangeLog|null $submitted */
        $submitted = $entries->firstWhere('action', TaxRateChangeLog::ACTION_SUBMITTED);
        $this->assertNotNull($submitted);
        $this->assertSame(TaxSlab::STATUS_DRAFT, $submitted['previous_value']['status']);
        $this->assertSame(TaxSlab::STATUS_PENDING, $submitted['new_value']['status']);

        // Rejet : nouvelle entrée avec motif.
        /** @var TaxSlab $draft */
        $draft = TaxSlab::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 50,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING,
        ]);
        $service->reject($draft, $admin, 'Taux hors plafond.');

        /** @var TaxRateChangeLog $rejected */
        $rejected = TaxRateChangeLog::query()
            ->where('record_id', $draft->id)
            ->where('action', TaxRateChangeLog::ACTION_REJECTED)
            ->firstOrFail();

        $this->assertSame('Taux hors plafond.', $rejected->reason);
        $this->assertSame(TaxSlab::STATUS_PENDING, $rejected['previous_value']['status']);
        $this->assertSame(TaxSlab::STATUS_DRAFT, $rejected['new_value']['status']);
    }

    // ── Issue #1927 : immutabilité AU NIVEAU BASE (trigger PostgreSQL) ──────

    /**
     * Le trigger `BEFORE UPDATE OR DELETE` (migration 000011) bloque les
     * mutations directes en SQL — même pour un rôle possédant la table
     * (le blocage modèle #1813 ne couvrait pas l'accès brut).
     */
    public function test_db_level_update_is_blocked_by_trigger(): void
    {
        /** @var TaxRateChangeLog $log */
        $log = TaxRateChangeLog::create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 1,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
        ]);

        $this->expectException(QueryException::class);

        // Le RAISE du trigger avorte la transaction PG : on isole la levée
        // dans un savepoint (DB::transaction imbriquée) pour ne pas empoisonner
        // la transaction RefreshDatabase du test (sinon 25P02 en cascade sur
        // le tearDown et tous les tests suivants du process).
        DB::transaction(function () use ($log): void {
            DB::table('tax_rate_change_log')
                ->where('id', $log->id)
                ->update(['action' => 'tampered']);
        });
    }

    public function test_db_level_delete_is_blocked_by_trigger(): void
    {
        /** @var TaxRateChangeLog $log */
        $log = TaxRateChangeLog::create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 2,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
        ]);

        $this->expectException(QueryException::class);

        // Même isolation en savepoint que test_db_level_update_is_blocked_by_trigger.
        DB::transaction(function () use ($log): void {
            DB::table('tax_rate_change_log')
                ->where('id', $log->id)
                ->delete();
        });
    }

    public function test_db_level_insert_still_works_with_trigger(): void
    {
        // L'append reste autorisé : le trigger ne couvre que UPDATE/DELETE.
        $id = DB::table('tax_rate_change_log')->insertGetId([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 3,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => json_encode(['rate' => 23]),
            'created_at' => now(),
        ]);

        $this->assertNotNull(
            TaxRateChangeLog::query()->find($id),
        );
    }

    public function test_db_level_truncate_is_blocked_by_trigger(): void
    {
        // Issue #2024 — le trigger append-only (#1927) couvrait UPDATE/DELETE
        // mais pas TRUNCATE : le propriétaire de la table pouvait vider la
        // piste d'audit en un seul vidage. BEFORE TRUNCATE lève la même
        // exception (P0001) — QueryException côté Laravel.
        /** @var TaxRateChangeLog $log */
        $log = TaxRateChangeLog::create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 4,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
        ]);

        $this->assertDatabaseHas('tax_rate_change_log', ['id' => $log->id]);

        $this->expectException(QueryException::class);

        // TRUNCATE est transactionnel en PG : même isolation en savepoint.
        DB::transaction(function (): void {
            DB::table('tax_rate_change_log')->truncate();
        });
    }

    public function test_mass_assignment_allowlist_blocks_unknown_fields(): void
    {
        $log = TaxRateChangeLog::query()->create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 1,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => 1,
            'actor_role' => 'employee',
            'new_value' => ['rate' => 23],
            'previous_value' => null,
            'reason' => 'création initiale',
            'id' => 999_999, // tentative de forçage de la clé primaire
            'company_id' => 'attacker-uuid', // colonne inexistante/forcée
        ]);

        $this->assertNotSame(999_999, $log->id);
        $this->assertSame(TaxRateChangeLog::TABLE_TAX_SLABS, $log->table_name);
        $this->assertNull($log->getAttribute('company_id'));
    }
}
