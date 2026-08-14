<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
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
        $admin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-rate-log@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);

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
        $submitted = $entries->firstWhere('action', TaxRateChangeLog::ACTION_SUBMITTED);
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

        $rejected = TaxRateChangeLog::query()
            ->where('record_id', $draft->id)
            ->where('action', TaxRateChangeLog::ACTION_REJECTED)
            ->firstOrFail();

        $this->assertSame('Taux hors plafond.', $rejected->reason);
        $this->assertSame(TaxSlab::STATUS_PENDING, $rejected->previous_value['status']);
        $this->assertSame(TaxSlab::STATUS_DRAFT, $rejected->new_value['status']);
    }
}
