<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmCostAxis;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Modules\FarmOs\Domain\Exceptions\FarmFinanceException;
use App\Modules\FarmOs\Domain\Models\FarmCampaign;
use App\Modules\FarmOs\Domain\Models\FarmExpense;
use App\Modules\FarmOs\Domain\Models\FarmSaleLink;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmProfitabilityService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — T4.1 Achats, ventes, depenses et rentabilite.
 *
 * Exigences portees par ce controleur :
 *   - l'outil `finance_expenses` doit etre REELLEMENT active (fail-closed) ;
 *   - AUCUN document commercial n'est cree ici : les ventes et les achats sont
 *     REFERENCÉS par identifiant vers les services existants ;
 *   - la rentabilite expose separement reel / estime / manquant et signale
 *     explicitement quand le resultat n'est pas fiable.
 */
final class FarmFinanceController
{
    public function __construct(
        private readonly FarmProfitabilityService $finance,
        private readonly FarmCapabilityGate $gate,
    ) {}

    /**
     * Campagnes agricoles.
     */
    public function indexCampaigns(): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        $campaigns = FarmCampaign::query()
            ->where('company_id', $this->company()->id)
            ->orderByDesc('start_year')
            ->get();

        return response()->json([
            'data' => $campaigns->map(fn (FarmCampaign $campaign): array => $this->campaignPayload($campaign))->all(),
            'meta' => [
                'reference' => [
                    'expense_categories' => FarmExpenseCategory::values(),
                    'cost_axes' => FarmCostAxis::values(),
                    'campaign_statuses' => FarmCampaign::STATUSES,
                ],
            ],
        ]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'start_year' => ['required', 'integer', 'min:1900', 'max:2200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'client_campaign_id' => ['nullable', 'string', 'max:80'],
        ]);

        $campaign = $this->finance->createCampaign(
            $this->company(),
            $validated['name'],
            (int) $validated['start_year'],
            isset($validated['starts_on']) ? Carbon::parse($validated['starts_on']) : null,
            isset($validated['ends_on']) ? Carbon::parse($validated['ends_on']) : null,
            $request->user()?->id,
            $validated['client_campaign_id'] ?? null,
        );

        return response()->json(['data' => $this->campaignPayload($campaign)], 201);
    }

    /**
     * Depenses agricoles.
     */
    public function indexExpenses(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        $expenses = FarmExpense::query()
            ->where('company_id', $this->company()->id)
            ->when($request->filled('parcel_id'), fn ($query) => $query->where('parcel_id', $request->integer('parcel_id')))
            ->when($request->filled('farm_livestock_batch_id'), fn ($query) => $query->where('farm_livestock_batch_id', $request->integer('farm_livestock_batch_id')))
            ->when($request->filled('farm_campaign_id'), fn ($query) => $query->where('farm_campaign_id', $request->integer('farm_campaign_id')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->orderByDesc('spent_on')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $expenses->getCollection()->map(fn (FarmExpense $expense): array => $this->expensePayload($expense))->all(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(FarmExpenseCategory::values())],
            'label' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_estimated' => ['nullable', 'boolean'],
            'spent_on' => ['required', 'date'],
            'farm_site_id' => ['nullable', 'integer'],
            'parcel_id' => ['nullable', 'integer'],
            'crop_cycle_id' => ['nullable', 'integer'],
            'farm_livestock_batch_id' => ['nullable', 'integer'],
            'farm_equipment_id' => ['nullable', 'integer'],
            'farm_campaign_id' => ['nullable', 'integer'],
            // References vers les services EXISTANTS (achats, fournisseurs,
            // fichiers) : Farm OS ne cree aucun document d'achat.
            'supplier_id' => ['nullable', 'integer'],
            'purchase_document_id' => ['nullable', 'integer'],
            'farm_input_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:24'],
            'file_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_expense_id' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->run(fn (): FarmExpense => $this->finance->recordExpense(
            $this->company(),
            FarmExpenseCategory::from($validated['category']),
            $validated['label'],
            (string) $validated['amount'],
            $validated['currency'],
            Carbon::parse($validated['spent_on']),
            (bool) ($validated['is_estimated'] ?? false),
            isset($validated['farm_site_id']) ? (int) $validated['farm_site_id'] : null,
            isset($validated['parcel_id']) ? (int) $validated['parcel_id'] : null,
            isset($validated['crop_cycle_id']) ? (int) $validated['crop_cycle_id'] : null,
            isset($validated['farm_livestock_batch_id']) ? (int) $validated['farm_livestock_batch_id'] : null,
            isset($validated['farm_equipment_id']) ? (int) $validated['farm_equipment_id'] : null,
            isset($validated['farm_campaign_id']) ? (int) $validated['farm_campaign_id'] : null,
            isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            isset($validated['purchase_document_id']) ? (int) $validated['purchase_document_id'] : null,
            isset($validated['farm_input_id']) ? (int) $validated['farm_input_id'] : null,
            isset($validated['quantity']) ? (string) $validated['quantity'] : null,
            $validated['unit'] ?? null,
            isset($validated['file_id']) ? (int) $validated['file_id'] : null,
            $validated['notes'] ?? null,
            $request->user()?->id,
            $validated['client_expense_id'] ?? null,
        ), $this->expensePayload(...));
    }

    /**
     * Liens vers les documents de vente EXISTANTS.
     */
    public function indexSaleLinks(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::SalesCustomers);

        $links = FarmSaleLink::query()
            ->where('company_id', $this->company()->id)
            ->when($request->filled('farm_harvest_lot_id'), fn ($query) => $query->where('farm_harvest_lot_id', $request->integer('farm_harvest_lot_id')))
            ->when($request->filled('farm_livestock_batch_id'), fn ($query) => $query->where('farm_livestock_batch_id', $request->integer('farm_livestock_batch_id')))
            ->orderByDesc('sold_on')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $links->getCollection()->map(fn (FarmSaleLink $link): array => $this->saleLinkPayload($link))->all(),
            'meta' => [
                'current_page' => $links->currentPage(),
                'last_page' => $links->lastPage(),
                'total' => $links->total(),
            ],
        ]);
    }

    public function storeSaleLink(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::SalesCustomers);

        $validated = $request->validate([
            // Document du service de VENTES EXISTANT (obligatoire).
            'sale_document_id' => ['required', 'integer', 'min:1'],
            'sale_line_id' => ['nullable', 'integer'],
            'farm_harvest_lot_id' => ['nullable', 'integer'],
            'farm_livestock_batch_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'customer_id' => ['nullable', 'integer'],
            'sold_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_sale_link_id' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->run(fn (): FarmSaleLink => $this->finance->linkSale(
            $this->company(),
            (int) $validated['sale_document_id'],
            (string) $validated['amount'],
            $validated['currency'],
            Carbon::parse($validated['sold_on']),
            isset($validated['farm_harvest_lot_id']) ? (int) $validated['farm_harvest_lot_id'] : null,
            isset($validated['farm_livestock_batch_id']) ? (int) $validated['farm_livestock_batch_id'] : null,
            isset($validated['sale_line_id']) ? (int) $validated['sale_line_id'] : null,
            isset($validated['quantity']) ? (string) $validated['quantity'] : null,
            $validated['unit'] ?? null,
            isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            $validated['notes'] ?? null,
            $request->user()?->id,
            $validated['client_sale_link_id'] ?? null,
        ), $this->saleLinkPayload(...));
    }

    /**
     * Rentabilite par axe d'analyse.
     */
    public function profitability(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        $validated = $request->validate([
            'axis' => ['required', 'string', Rule::in(FarmCostAxis::values())],
            'axis_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $result = $this->finance->profitability(
            $this->company(),
            FarmCostAxis::from($validated['axis']),
            isset($validated['axis_id']) ? (int) $validated['axis_id'] : null,
            isset($validated['from']) ? Carbon::parse($validated['from']) : null,
            isset($validated['to']) ? Carbon::parse($validated['to']) : null,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Rentabilite par campagne.
     */
    public function profitabilityByCampaign(): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::FinanceExpenses);

        return response()->json(['data' => $this->finance->profitabilityByCampaign($this->company())]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(): T  $action
     * @param  callable(T): array<string, mixed>  $payload
     */
    private function run(callable $action, callable $payload): JsonResponse
    {
        try {
            $result = $action();
        } catch (FarmFinanceException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->errorCode(),
            ], 422);
        }

        return response()->json(['data' => $payload($result)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignPayload(FarmCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'start_year' => $campaign->start_year,
            'starts_on' => $campaign->starts_on?->toDateString(),
            'ends_on' => $campaign->ends_on?->toDateString(),
            'status' => $campaign->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(FarmExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'category' => $expense->category,
            'label' => $expense->label,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            // Qualite de la donnee exposee explicitement au client.
            'is_estimated' => $expense->is_estimated,
            'data_quality' => $expense->dataQuality()->value,
            'spent_on' => $expense->spent_on->toDateString(),
            'parcel_id' => $expense->parcel_id,
            'crop_cycle_id' => $expense->crop_cycle_id,
            'farm_livestock_batch_id' => $expense->farm_livestock_batch_id,
            'farm_equipment_id' => $expense->farm_equipment_id,
            'farm_campaign_id' => $expense->farm_campaign_id,
            'supplier_id' => $expense->supplier_id,
            'purchase_document_id' => $expense->purchase_document_id,
            'farm_input_id' => $expense->farm_input_id,
            'quantity' => $expense->quantity,
            'unit' => $expense->unit,
            'file_id' => $expense->file_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleLinkPayload(FarmSaleLink $link): array
    {
        return [
            'id' => $link->id,
            // Reference vers le document du service de ventes EXISTANT.
            'sale_document_id' => $link->sale_document_id,
            'sale_line_id' => $link->sale_line_id,
            'farm_harvest_lot_id' => $link->farm_harvest_lot_id,
            'farm_livestock_batch_id' => $link->farm_livestock_batch_id,
            'quantity' => $link->quantity,
            'unit' => $link->unit,
            'amount' => $link->amount,
            'currency' => $link->currency,
            'customer_id' => $link->customer_id,
            'sold_on' => $link->sold_on->toDateString(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
