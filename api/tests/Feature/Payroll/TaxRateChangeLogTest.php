<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Application\Services\TaxRateValidationWorkflow;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADMIN-PAIE (#1813) — audit trail immuable des modifications de taux
 * légaux. La table est append-only : tout UPDATE/DELETE doit échouer au
 * niveau PostgreSQL (trigger), et chaque transition métier doit produire
 * une entrée de log.
 */
class TaxRateChangeLogTest extends TestCase
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
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    private function createEntry(): TaxRateChangeLog
    {
        /** @var TaxRateChangeLog $entry */
        $entry = TaxRateChangeLog::query()->create([
            'table_name' => TaxRateChangeLog::TABLE_TAX_SLABS,
            'record_id' => 42,
            'action' => TaxRateChangeLog::ACTION_CREATED,
            'actor_id' => $this->manager->id,
            'actor_role' => 'employee',
            'previous_value' => null,
            'new_value' => ['rate' => 23],
            'reason' => null,
        ]);

        return $entry;
    }

    public function test_log_is_append_only_update_blocked(): void
    {
        $entry = $this->createEntry();

        $this->expectException(QueryException::class);

        $entry->update(['reason' => 'tampered']);
    }

    public function test_log_is_append_only_delete_blocked(): void
    {
        $entry = $this->createEntry();

        $this->expectException(QueryException::class);

        $entry->delete();
    }

    public function test_every_transition_creates_log_entry(): void
    {
        $slab = TaxSlab::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche IRG',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 23,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_DRAFT,
        ]);

        $workflow = app(TaxRateValidationWorkflow::class);

        $workflow->recordCreation($slab, $this->manager);
        $workflow->submit($slab, $this->manager);

        $this->assertSame(2, TaxRateChangeLog::query()->count());
        $this->assertSame(
            [TaxRateChangeLog::ACTION_SUBMITTED, TaxRateChangeLog::ACTION_CREATED],
            TaxRateChangeLog::query()->orderByDesc('id')->pluck('action')->all(),
        );
    }

    public function test_rejection_reason_is_persisted_in_log(): void
    {
        $slab = TaxSlab::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche IRG',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 30,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING_VALIDATION,
        ]);

        app(TaxRateValidationWorkflow::class)->reject($slab, $this->superAdmin(), 'Taux erroné');

        /** @var TaxRateChangeLog $entry */
        $entry = TaxRateChangeLog::query()
            ->forRecord(TaxRateChangeLog::TABLE_TAX_SLABS, (int) $slab->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame(TaxRateChangeLog::ACTION_REJECTED, $entry->action);
        $this->assertSame('Taux erroné', $entry->reason);
        $this->assertSame('super_admin', $entry->actor_role);
        $this->assertSame('pending_validation', $entry->previous_value['status'] ?? null);
        $this->assertSame('draft', $entry->new_value['status'] ?? null);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin-log@example.com',
            'password_hash' => 'hashed',
        ]);
    }
}
