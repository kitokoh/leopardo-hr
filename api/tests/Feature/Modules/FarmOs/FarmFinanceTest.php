<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCostAxis;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Modules\FarmOs\Domain\Exceptions\FarmFinanceException;
use App\Modules\FarmOs\Domain\Models\FarmCampaign;
use App\Modules\FarmOs\Domain\Services\FarmProfitabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T4.1 : rentabilite et absence de systeme financier parallele.
 *
 * Ces tests exercent le service metier sur un schema tenant. Ils exigent
 * PostgreSQL et ne tournent donc qu'en CI.
 *
 * DEUX INVARIANTS VERIFIES ICI :
 *   1. Farm OS ne cree aucun document commercial : une vente doit referencer un
 *      document du service de ventes existant, et un meme document ne peut pas
 *      etre impute deux fois au meme lot ;
 *   2. une rentabilite calculee sur des donnees incompletes n'est JAMAIS
 *      presentee comme fiable : `is_reliable` est faux et `missing_inputs`
 *      liste explicitement ce qui manque.
 */
final class FarmFinanceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);
    }

    public function test_campaign_is_created_open(): void
    {
        $campaign = $this->makeCampaign();

        $this->assertSame('open', $campaign->status);
        $this->assertFalse($campaign->isClosed());
    }

    public function test_expense_is_recorded_with_its_data_quality(): void
    {
        $service = app(FarmProfitabilityService::class);

        $real = $service->recordExpense(
            $this->company,
            FarmExpenseCategory::Seeds,
            'wheat seed',
            '1200.00',
            'EUR',
            Carbon::parse('2026-03-01'),
        );

        $estimated = $service->recordExpense(
            $this->company,
            FarmExpenseCategory::Labour,
            'casual labour',
            '300.00',
            'EUR',
            Carbon::parse('2026-03-02'),
            true,
        );

        $this->assertSame('real', $real->dataQuality()->value);
        $this->assertSame('estimated', $estimated->dataQuality()->value);
    }

    /**
     * Un montant nul ou negatif est refuse : on n'ecrit jamais un zero de
     * remplissage qui fausserait la marge.
     */
    public function test_a_zero_or_negative_expense_is_refused(): void
    {
        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->recordExpense(
            $this->company,
            FarmExpenseCategory::Seeds,
            'free seed',
            '0',
            'EUR',
            Carbon::parse('2026-03-01'),
        );
    }

    public function test_expense_on_a_closed_campaign_is_refused(): void
    {
        $campaign = $this->makeCampaign();
        $campaign->status = 'closed';
        $campaign->save();

        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->recordExpense(
            $this->company,
            FarmExpenseCategory::Seeds,
            'late seed',
            '100.00',
            'EUR',
            Carbon::parse('2026-03-01'),
            false,
            null,
            null,
            null,
            null,
            null,
            $campaign->id,
        );
    }

    public function test_expense_on_a_foreign_campaign_is_refused(): void
    {
        $other = Company::factory()->create();

        // BelongsToCompany ECASE `company_id` avec le tenant actif (#7646) :
        // une campagne ne peut pas naitre chez un autre tenant via Eloquent.
        // Insertion SQL brute + relecture cross-tenant pour eprouver le vrai
        // controle d'appartenance du service de rentabilite.
        DB::table('farm_campaigns')->insert([
            'id' => 900003,
            'company_id' => $other->id,
            'name' => 'foreign campaign',
            'start_year' => 2026,
            'status' => 'open',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        /** @var FarmCampaign $foreign */
        $foreign = FarmCampaign::crossTenantForPlatformAdmin()->findOrFail(900003);

        $this->assertSame($other->id, $foreign->company_id);

        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->recordExpense(
            $this->company,
            FarmExpenseCategory::Seeds,
            'seed',
            '100.00',
            'EUR',
            Carbon::parse('2026-03-01'),
            false,
            null,
            null,
            null,
            null,
            null,
            $foreign->id,
        );
    }

    /**
     * Farm OS ne fabrique JAMAIS de document commercial : un lien de vente sans
     * document existant est refuse.
     */
    public function test_sale_link_requires_an_existing_sale_document(): void
    {
        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->linkSale(
            $this->company,
            0,
            '500.00',
            'EUR',
            Carbon::parse('2026-06-01'),
            1,
        );
    }

    /**
     * Le chiffre d'affaires doit etre rattache a un objet agricole, sinon il
     * n'est ventilable sur aucune parcelle ni aucun lot.
     */
    public function test_sale_link_requires_an_agricultural_target(): void
    {
        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->linkSale(
            $this->company,
            42,
            '500.00',
            'EUR',
            Carbon::parse('2026-06-01'),
        );
    }

    /**
     * GARDE ANTI-DOUBLE-COMPTAGE DU CHIFFRE D'AFFAIRES : un meme document de
     * vente ne peut pas etre impute deux fois au meme lot.
     */
    public function test_the_same_sale_document_cannot_be_linked_twice_to_the_same_lot(): void
    {
        $service = app(FarmProfitabilityService::class);

        $service->linkSale($this->company, 77, '500.00', 'EUR', Carbon::parse('2026-06-01'), 10);

        $this->expectException(FarmFinanceException::class);

        $service->linkSale($this->company, 77, '500.00', 'EUR', Carbon::parse('2026-06-02'), 10);
    }

    public function test_sale_link_recording_is_idempotent_on_client_id(): void
    {
        $service = app(FarmProfitabilityService::class);

        $first = $service->linkSale($this->company, 88, '500.00', 'EUR', Carbon::parse('2026-06-01'), 11, null, null, null, null, null, null, null, 'offline-sale-link-1');
        $second = $service->linkSale($this->company, 88, '500.00', 'EUR', Carbon::parse('2026-06-01'), 11, null, null, null, null, null, null, null, 'offline-sale-link-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('farm_sales_links')->where('company_id', $this->company->id)->count());
    }

    /**
     * HONNETETE : sans aucune donnee, la rentabilite n'est PAS fiable et la
     * liste de ce qui manque est explicite. On ne presente jamais une marge
     * calculee sur des donnees incompletes comme un resultat certain.
     */
    public function test_profitability_without_data_is_not_reliable(): void
    {
        $result = app(FarmProfitabilityService::class)->profitability($this->company, FarmCostAxis::Farm);

        $this->assertSame('0.00', $result['costs_total']);
        $this->assertSame('0.00', $result['revenue_recorded']);
        $this->assertFalse($result['is_reliable']);
        $this->assertContains('expenses', $result['missing_inputs']);
        $this->assertContains('revenue', $result['missing_inputs']);
        $this->assertSame('estimated', $result['data_quality']);
    }

    public function test_profitability_separates_real_from_estimated_costs(): void
    {
        $service = app(FarmProfitabilityService::class);

        $service->recordExpense($this->company, FarmExpenseCategory::Seeds, 'seed', '1000.00', 'EUR', Carbon::parse('2026-03-01'));
        $service->recordExpense($this->company, FarmExpenseCategory::Labour, 'labour', '400.00', 'EUR', Carbon::parse('2026-03-02'), true);

        $result = $service->profitability($this->company, FarmCostAxis::Farm);

        $this->assertSame('1000.00', $result['costs_real']);
        $this->assertSame('400.00', $result['costs_estimated']);
        $this->assertSame('1400.00', $result['costs_total']);
        $this->assertSame('1000.00', $result['by_category']['seeds']['real']);
        $this->assertSame('400.00', $result['by_category']['labour']['estimated']);
        // Des couts estimes empechent un resultat fiable.
        $this->assertContains('estimated_costs', $result['missing_inputs']);
        $this->assertFalse($result['is_reliable']);
    }

    public function test_profitability_is_reliable_only_when_nothing_is_missing(): void
    {
        $service = app(FarmProfitabilityService::class);

        $service->recordExpense($this->company, FarmExpenseCategory::Seeds, 'seed', '1000.00', 'EUR', Carbon::parse('2026-03-01'));
        $service->linkSale($this->company, 101, '2500.00', 'EUR', Carbon::parse('2026-06-01'), 5);

        $result = $service->profitability($this->company, FarmCostAxis::Farm);

        $this->assertSame('1000.00', $result['costs_total']);
        $this->assertSame('2500.00', $result['revenue_recorded']);
        $this->assertSame('1500.00', $result['margin_estimated']);
        $this->assertSame([], $result['missing_inputs']);
        $this->assertTrue($result['is_reliable']);
        $this->assertSame('real', $result['data_quality']);
    }

    public function test_profitability_filters_by_axis(): void
    {
        $service = app(FarmProfitabilityService::class);

        $service->recordExpense($this->company, FarmExpenseCategory::Seeds, 'parcel 1 seed', '100.00', 'EUR', Carbon::parse('2026-03-01'), false, null, 1);
        $service->recordExpense($this->company, FarmExpenseCategory::Seeds, 'parcel 2 seed', '900.00', 'EUR', Carbon::parse('2026-03-01'), false, null, 2);

        $result = $service->profitability($this->company, FarmCostAxis::Parcel, 1);

        $this->assertSame('100.00', $result['costs_total']);
        $this->assertSame(1, $result['axis_id']);
    }

    public function test_profitability_by_campaign_returns_one_row_per_campaign(): void
    {
        $service = app(FarmProfitabilityService::class);
        $campaign = $this->makeCampaign();

        $service->recordExpense($this->company, FarmExpenseCategory::Seeds, 'seed', '500.00', 'EUR', Carbon::parse('2026-03-01'), false, null, null, null, null, null, $campaign->id);

        $rows = $service->profitabilityByCampaign($this->company);

        $this->assertCount(1, $rows);
        $this->assertSame('500.00', $rows[0]['costs_total']);
        $this->assertSame($campaign->name, $rows[0]['campaign_name']);
    }

    public function test_finance_data_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        app(FarmProfitabilityService::class)->recordExpense($this->company, FarmExpenseCategory::Seeds, 'seed', '100.00', 'EUR', Carbon::parse('2026-03-01'));

        $this->assertSame(1, DB::table('farm_expenses')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('farm_expenses')->where('company_id', $other->id)->count());
    }

    private function makeCampaign(): FarmCampaign
    {
        return app(FarmProfitabilityService::class)->createCampaign(
            $this->company,
            'Campaign '.random_int(1000, 9999),
            2026,
        );
    }
}
