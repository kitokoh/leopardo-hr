<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Exceptions\PeriodClosedException;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingDepreciation;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\DepreciationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9378 — amortissements.
 *
 * Le défaut audité : aucune notion d'immobilisation ni de dotation. Les achats
 * d'actifs restaient en charges de l'exercice et le résultat était surévalué du
 * montant des amortissements non pratiqués.
 *
 * Les montants attendus sont calculés à la main dans chaque test.
 */
class DepreciationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['currency' => 'DZD', 'country' => $country]);

        AccountingSettings::query()->create([
            'company_id' => $company->id,
            'currency' => 'DZD',
            'document_language' => 'fr',
            'number_series' => [],
        ]);

        return $company;
    }

    private function asset(Company $company, float $cost = 12000.0, int $months = 12, float $residual = 0.0): AccountingFixedAsset
    {
        /** @var AccountingFixedAsset $asset */
        $asset = AccountingFixedAsset::query()->create([
            'company_id' => $company->id,
            'code' => 'IMM-'.uniqid(),
            'label' => 'Ordinateur portable',
            'in_service_date' => '2026-01-15',
            'acquisition_cost' => $cost,
            'residual_value' => $residual,
            'useful_life_months' => $months,
            'method' => AccountingFixedAsset::METHOD_LINEAR,
            'currency' => 'DZD',
            'status' => AccountingFixedAsset::STATUS_ACTIVE,
        ]);

        return $asset;
    }

    /**
     * Critère principal : 12 000 sur 12 mois → 1 000 par mois.
     * Écriture : D 681 = 1 000 / C 281 = 1 000.
     */
    public function test_monthly_linear_depreciation_posts_a_balanced_entry(): void
    {
        $company = $this->company();
        $asset = $this->asset($company);

        $amount = app(DepreciationService::class)->postForAsset($asset, '2026-01');

        $this->assertSame(1000.0, $amount);

        $entries = AccountingJournalEntry::query()
            ->where('source_type', 'depreciation')
            ->where('source_id', $asset->id)
            ->get();

        $this->assertCount(2, $entries);

        $debit = $entries->firstWhere('debit', '>', 0);
        $credit = $entries->firstWhere('credit', '>', 0);

        $this->assertSame('681', $debit->account_code);
        $this->assertSame(1000.0, (float) $debit->debit);
        $this->assertSame('281', $credit->account_code);
        $this->assertSame(1000.0, (float) $credit->credit);

        // Écriture équilibrée.
        $this->assertSame(
            round((float) $entries->sum('debit'), 2),
            round((float) $entries->sum('credit'), 2),
        );
    }

    /**
     * Idempotence : relancer la dotation d'un mois déjà traité ne double pas la
     * charge.
     */
    public function test_running_the_same_period_twice_does_not_double_the_charge(): void
    {
        $company = $this->company();
        $asset = $this->asset($company);
        $service = app(DepreciationService::class);

        $service->postForAsset($asset, '2026-01');
        $second = $service->postForAsset($asset, '2026-01');

        $this->assertNull($second);
        $this->assertSame(1, AccountingDepreciation::query()->where('fixed_asset_id', $asset->id)->count());
        $this->assertSame(1000.0, (float) AccountingDepreciation::query()
            ->where('fixed_asset_id', $asset->id)
            ->sum('amount'));
    }

    /**
     * Plafonnement : la dotation cumulée ne dépasse jamais la base amortissable,
     * même quand la division ne tombe pas juste.
     *
     * Calcul manuel : 1 000 sur 3 mois → 333,33 / 333,33 / 333,34 (le dernier
     * solde exactement la base, pas 333,33).
     */
    public function test_last_period_caps_at_the_depreciable_base(): void
    {
        $company = $this->company();
        $asset = $this->asset($company, 1000.0, 3);
        $service = app(DepreciationService::class);

        $service->postForAsset($asset, '2026-01');
        $service->postForAsset($asset, '2026-02');
        $service->postForAsset($asset, '2026-03');

        $total = (float) AccountingDepreciation::query()
            ->where('fixed_asset_id', $asset->id)
            ->sum('amount');

        $this->assertSame(1000.0, round($total, 2));

        // Une 4e période ne passe plus rien.
        $this->assertNull($service->postForAsset($asset, '2026-04'));
    }

    /**
     * La valeur résiduelle n'est jamais amortie.
     *
     * Calcul manuel : 12 000 − 2 000 = 10 000 amortissables sur 10 mois → 1 000.
     */
    public function test_residual_value_is_not_depreciated(): void
    {
        $company = $this->company();
        $asset = $this->asset($company, 12000.0, 10, 2000.0);

        $this->assertSame(10000.0, $asset->depreciableBase());
        $this->assertSame(1000.0, $asset->monthlyDepreciation());
    }

    /**
     * Aucune dotation avant la mise en service.
     */
    public function test_no_depreciation_before_the_in_service_date(): void
    {
        $company = $this->company();
        $asset = $this->asset($company);

        $this->assertNull(app(DepreciationService::class)->postForAsset($asset, '2025-12'));
    }

    /**
     * Une période clôturée refuse la dotation (même garde que le posting).
     */
    public function test_closed_period_rejects_depreciation(): void
    {
        $company = $this->company();
        $this->asset($company);

        AccountingClosedPeriod::query()->create([
            'company_id' => $company->id,
            'period' => '2026-01',
            'closed_at' => now(),
        ]);

        $this->expectException(PeriodClosedException::class);

        app(DepreciationService::class)->runForPeriod((string) $company->id, '2026-01');
    }

    /**
     * Le tableau prévisionnel couvre exactement la durée d'utilité et solde la
     * base.
     */
    public function test_schedule_covers_the_useful_life_and_balances(): void
    {
        $company = $this->company();
        $asset = $this->asset($company, 1000.0, 3);

        $schedule = app(DepreciationService::class)->schedule($asset);

        $this->assertCount(3, $schedule);
        $this->assertSame('2026-01', $schedule[0]['period']);
        $this->assertSame('2026-03', $schedule[2]['period']);
        $this->assertSame(1000.0, $schedule[2]['accumulated']);
        $this->assertSame(0.0, $schedule[2]['net_book_value']);
    }

    /**
     * Le run global ne traite que les actifs actifs et renvoie un récapitulatif.
     */
    public function test_run_for_period_reports_posted_and_skipped(): void
    {
        $company = $this->company();
        $this->asset($company, 1200.0, 12);
        $this->asset($company, 2400.0, 12);

        $result = app(DepreciationService::class)->runForPeriod((string) $company->id, '2026-01');

        $this->assertSame(2, $result['posted']);
        $this->assertSame(0, $result['skipped']);
        // 100 + 200
        $this->assertSame(300.0, $result['total']);

        // Deuxième passage : tout est déjà doté.
        $second = app(DepreciationService::class)->runForPeriod((string) $company->id, '2026-01');
        $this->assertSame(0, $second['posted']);
        $this->assertSame(2, $second['skipped']);
    }
}
