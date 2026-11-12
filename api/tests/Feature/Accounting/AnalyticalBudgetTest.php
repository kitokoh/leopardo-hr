<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingBudget;
use App\Modules\Accounting\Domain\Models\AccountingCostCenter;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\AnalyticalService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9375 — comptabilité analytique et budget.
 *
 * Le défaut audité : `accounting_documents.project_ref` était une chaîne libre,
 * jamais reportée sur l'écriture, et rien ne comparait un réalisé à un budget.
 */
class AnalyticalBudgetTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['currency' => 'DZD', 'country' => 'DZ']);

        AccountingSettings::query()->create([
            'company_id' => $company->id,
            'currency' => 'DZD',
            'document_language' => 'fr',
            'number_series' => [],
        ]);

        return $company;
    }

    private function center(Company $company, string $code): AccountingCostCenter
    {
        /** @var AccountingCostCenter $center */
        $center = AccountingCostCenter::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'label' => 'Axe '.$code,
            'is_active' => true,
        ]);

        return $center;
    }

    private function entry(Company $company, string $account, float $debit, float $credit, ?int $centerId = null, string $date = '2026-03-15'): void
    {
        AccountingJournalEntry::query()->create([
            'company_id' => $company->id,
            'entry_date' => $date,
            'period' => substr($date, 0, 7),
            'currency' => 'DZD',
            'amount_currency' => 'DZD',
            'exchange_rate' => 1,
            'journal_code' => 'OD',
            'line_no' => 1,
            'cost_center_id' => $centerId,
            'source_type' => 'manual',
            'source_id' => random_int(1, 100000),
            'account_code' => $account,
            'account_label' => 'Compte '.$account,
            'debit' => $debit,
            'credit' => $credit,
            'piece' => 'OD-'.uniqid(),
        ]);
    }

    /**
     * Réalisé par axe : les charges d'un axe s'agrègent, les autres axes ne
     * polluent pas le total.
     */
    public function test_actuals_are_grouped_by_cost_center(): void
    {
        $company = $this->company();
        $chantier = $this->center($company, 'CH-1');
        $admin = $this->center($company, 'ADM');

        $this->entry($company, '607', 1000, 0, $chantier->id);
        $this->entry($company, '628', 500, 0, $chantier->id);
        $this->entry($company, '641', 2000, 0, $admin->id);

        $actuals = app(AnalyticalService::class)->actualsByCostCenter(
            (string) $company->id,
            '2026-01-01',
            '2026-12-31',
        );

        $byCode = collect($actuals)->keyBy('code');

        $chantierRow = $byCode['CH-1'];
        $adminRow = $byCode['ADM'];
        $this->assertNotNull($chantierRow);
        $this->assertNotNull($adminRow);

        $this->assertSame(1500.0, $chantierRow['charges']);
        $this->assertSame(2000.0, $adminRow['charges']);
    }

    /**
     * Critère principal : consommation budgétaire.
     *
     * Budget 10 000 sur l'axe CH-1, réalisé 6 000 → reste 4 000, taux 60 %.
     */
    public function test_budget_consumption_computes_remaining_and_rate(): void
    {
        $company = $this->company();
        $chantier = $this->center($company, 'CH-1');

        AccountingBudget::query()->create([
            'company_id' => $company->id,
            'cost_center_id' => $chantier->id,
            'year' => 2026,
            'account_prefix' => '6',
            'amount' => 10000,
            'label' => 'Budget chantier 1',
        ]);

        $this->entry($company, '607', 4000, 0, $chantier->id);
        $this->entry($company, '628', 2000, 0, $chantier->id);

        $consumption = app(AnalyticalService::class)->budgetConsumption((string) $company->id, 2026);

        $this->assertCount(1, $consumption);
        $this->assertSame(10000.0, $consumption[0]['budget']);
        $this->assertSame(6000.0, $consumption[0]['actual']);
        $this->assertSame(4000.0, $consumption[0]['remaining']);
        $this->assertSame(60.0, $consumption[0]['consumption_rate']);
        $this->assertFalse($consumption[0]['over_budget']);
    }

    /**
     * Dépassement signalé explicitement.
     */
    public function test_over_budget_is_flagged(): void
    {
        $company = $this->company();
        $chantier = $this->center($company, 'CH-1');

        AccountingBudget::query()->create([
            'company_id' => $company->id,
            'cost_center_id' => $chantier->id,
            'year' => 2026,
            'account_prefix' => null,
            'amount' => 1000,
        ]);

        $this->entry($company, '607', 1500, 0, $chantier->id);

        $consumption = app(AnalyticalService::class)->budgetConsumption((string) $company->id, 2026);

        $this->assertTrue($consumption[0]['over_budget']);
        $this->assertSame(-500.0, $consumption[0]['remaining']);
        $this->assertSame(150.0, $consumption[0]['consumption_rate']);
    }

    /**
     * Le préfixe de compte filtre la nature de charge : un budget sur '60'
     * n'absorbe pas les salaires ('641').
     */
    public function test_account_prefix_scopes_the_budget(): void
    {
        $company = $this->company();
        $chantier = $this->center($company, 'CH-1');

        AccountingBudget::query()->create([
            'company_id' => $company->id,
            'cost_center_id' => $chantier->id,
            'year' => 2026,
            'account_prefix' => '60',
            'amount' => 5000,
        ]);

        $this->entry($company, '607', 1000, 0, $chantier->id);
        $this->entry($company, '641', 3000, 0, $chantier->id);

        $consumption = app(AnalyticalService::class)->budgetConsumption((string) $company->id, 2026);

        // Seul le 607 (préfixe 60) entre dans le réalisé.
        $this->assertSame(1000.0, $consumption[0]['actual']);
    }

    /**
     * Isolation tenant : le réalisé d'une entreprise n'inclut jamais les
     * écritures d'une autre.
     */
    public function test_actuals_are_tenant_scoped(): void
    {
        $mine = $this->company();
        $other = $this->company();

        $myCenter = $this->center($mine, 'CH-1');
        $otherCenter = $this->center($other, 'CH-1');

        $this->entry($mine, '607', 1000, 0, $myCenter->id);
        $this->entry($other, '607', 9999, 0, $otherCenter->id);

        $actuals = app(AnalyticalService::class)->actualsByCostCenter(
            (string) $mine->id,
            '2026-01-01',
            '2026-12-31',
        );

        $this->assertCount(1, $actuals);
        $this->assertSame(1000.0, $actuals[0]['charges']);
    }

    /**
     * Reclassement : on rattache des écritures à un axe, mais jamais à l'axe
     * d'une autre entreprise.
     */
    public function test_assign_cost_center_rejects_a_foreign_axis(): void
    {
        $mine = $this->company();
        $other = $this->company();

        $myCenter = $this->center($mine, 'CH-1');
        $foreignCenter = $this->center($other, 'CH-9');

        $this->entry($mine, '607', 1000, 0, null);

        $entryId = (int) AccountingJournalEntry::query()->where('company_id', $mine->id)->value('id');
        $service = app(AnalyticalService::class);

        // Axe d'une autre entreprise : refusé, rien n'est modifié.
        $this->assertSame(0, $service->assignCostCenter((string) $mine->id, [$entryId], $foreignCenter->id));

        // Axe du tenant : accepté.
        $this->assertSame(1, $service->assignCostCenter((string) $mine->id, [$entryId], $myCenter->id));
        $this->assertSame(
            $myCenter->id,
            (int) AccountingJournalEntry::query()->whereKey($entryId)->value('cost_center_id'),
        );
    }
}
