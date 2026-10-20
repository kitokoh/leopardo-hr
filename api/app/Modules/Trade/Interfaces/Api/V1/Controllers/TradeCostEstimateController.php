<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Application\Actions\ComputeLandedCostAction;
use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Infrastructure\Services\TradeCustomsChecklist;
use App\Modules\Trade\Interfaces\Api\V1\Requests\CostEstimateRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeCostEstimateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cout total rendu et aide a la preparation douaniere — TRADE-007/#8482.
 *
 * `disclaimer()` expose le texte de non-responsabilite : l'API le sert pour
 * que TOUTE interface qui affiche une estimation ou une checklist l'affiche
 * aussi, sans avoir a le redupliquer.
 */
final class TradeCostEstimateController
{
    public function __construct(
        private readonly ComputeLandedCostAction $computeLandedCost,
        private readonly TradeCustomsChecklist $customsChecklist,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TradeCostEstimate::query()
            ->with('lines')
            ->orderByDesc('id');

        if ($request->filled('deal_id')) {
            $query->where('trade_deal_id', $request->integer('deal_id'));
        }

        if ($request->filled('order_id')) {
            $query->where('trade_order_id', $request->integer('order_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeCostEstimateResource::collection($query->paginate($perPage))->resolve(),
            'disclaimer' => $this->customsChecklist->disclaimer(),
        ]);
    }

    public function store(CostEstimateRequest $request): JsonResponse
    {
        /** @var array{goods_value_minor: int, goods_currency: string, display_currency: string, lines?: list<array{type: string, amount_minor: int, currency: string, source?: string|null, label?: string|null, note?: string|null}>, fx?: array{rate: float, date: string, source: string}|null, origin_country?: string|null, destination_country?: string|null, incoterm?: string|null, trade_deal_id?: int|null, trade_quote_id?: int|null, trade_order_id?: int|null} $validated */
        $validated = $request->validated();

        $lines = $validated['lines'] ?? [];
        $fx = $validated['fx'] ?? null;

        unset($validated['lines'], $validated['fx']);

        $estimate = $this->computeLandedCost->execute(
            $validated,
            $lines,
            $fx,
            $request->user()?->id,
        );

        $estimate->load('lines');

        return new JsonResponse([
            'data' => TradeCostEstimateResource::make($estimate)->resolve(),
            // Exigence de conformite : le disclaimer accompagne CHAQUE reponse
            // qui presente une estimation (cf. docblock de la classe).
            'disclaimer' => $this->customsChecklist->disclaimer(),
        ], 201);
    }

    public function show(TradeCostEstimate $costEstimate): JsonResponse
    {
        $costEstimate->load('lines');

        return new JsonResponse([
            'data' => TradeCostEstimateResource::make($costEstimate)->resolve(),
            'disclaimer' => $this->customsChecklist->disclaimer(),
        ]);
    }

    /**
     * Checklist documentaire + informations manquantes.
     *
     * ⚠️ La reponse porte TOUJOURS le `disclaimer` et un `duty_rate: null`
     * explicite : aucun taux de douane n'etant fourni par un prestataire, la
     * reponse dit « non estime » plutot que d'inventer un chiffre.
     */
    public function customs(Request $request): JsonResponse
    {
        $hsCode = $request->filled('hs_code') ? (string) $request->string('hs_code') : null;
        $incoterm = $request->filled('incoterm') ? (string) $request->string('incoterm') : null;
        $transportMode = $request->filled('transport_mode') ? (string) $request->string('transport_mode') : null;
        $origin = $request->filled('origin_country') ? (string) $request->string('origin_country') : null;
        $destination = $request->filled('destination_country') ? (string) $request->string('destination_country') : null;
        $goodsValue = $request->filled('goods_value_minor') ? $request->integer('goods_value_minor') : null;

        $missing = $this->customsChecklist->missingInformation(
            $hsCode,
            $origin,
            $destination,
            $incoterm,
            $goodsValue,
        );

        $documents = $this->customsChecklist->requiredDocuments(
            $transportMode,
            $incoterm,
            $hsCode !== null,
        );

        return new JsonResponse([
            'data' => [
                'required_documents' => $documents,
                'missing_information' => $missing,
                'is_quotable' => $this->customsChecklist->isComplete($missing),
                // Explicitement null : aucune source de tarifs douaniers n'est
                // configuree — la capacite est INDISPONIBLE, pas « estimee a 0 ».
                'duty_rate_percent' => null,
                'duty_rate_available' => false,
                'duty_source' => null,
                'notices' => [
                    'Leopardo fournit une aide a la preparation documentaire et a la mise en relation avec des prestataires competents.',
                    'Aucune source de tarifs douaniers n\'est configuree : les droits et taxes ne sont pas estimes par la plateforme.',
                ],
            ],
            'disclaimer' => $this->customsChecklist->disclaimer(),
        ]);
    }
}
