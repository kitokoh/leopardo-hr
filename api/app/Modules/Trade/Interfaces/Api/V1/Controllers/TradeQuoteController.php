<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Application\Actions\AcceptQuoteAction;
use App\Modules\Trade\Application\Actions\SubmitQuoteAction;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Interfaces\Api\V1\Requests\QuoteAcceptRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\QuoteStoreRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeOrderResource;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeQuoteResource;
use App\Shared\Enums\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offres commerciales — BC-36 TRADE, TRADE-004/#8479.
 *
 * MEME CONTROLEUR POUR LES DEUX ESPACES : `index` accepte un filtre
 * `direction` (`incoming` = offres recues cote acheteur, `outgoing` = offres
 * emises cote vendeur). C'est la vue metier partagee demandee par la spec — il
 * n'existe pas deux implementations a maintenir.
 */
final class TradeQuoteController
{
    public function __construct(
        private readonly SubmitQuoteAction $submitQuote,
        private readonly AcceptQuoteAction $acceptQuote,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TradeQuote::query()
            ->with(['lines'])
            ->orderByDesc('id');

        if ($request->filled('direction')) {
            $query->where('direction', (string) $request->string('direction'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('rfq_id')) {
            $query->where('trade_rfq_id', $request->integer('rfq_id'));
        }

        if ($request->filled('deal_id')) {
            $query->where('trade_deal_id', $request->integer('deal_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeQuoteResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    public function store(QuoteStoreRequest $request): JsonResponse
    {
        /** @var array{currency: string, lines: list<array{description: string, quantity: float|string, unit_price_minor: int, unit?: string|null, trade_product_id?: int|null, moq_quantity?: float|string|null, lead_time_days?: int|null}>, incoterm?: string|null, validity_until?: string|null, lead_time_days?: int|null, payment_terms?: string|null, notes?: string|null, submit?: bool, trade_rfq_id?: int|null, trade_rfq_invitation_id?: int|null, trade_deal_id?: int|null, trade_counterparty_id?: int|null} $validated */
        $validated = $request->validated();

        $lines = $validated['lines'];
        unset($validated['lines']);

        $submit = (bool) ($validated['submit'] ?? true);

        $rfqId = isset($validated['trade_rfq_id']) ? (int) $validated['trade_rfq_id'] : null;
        $invitationId = isset($validated['trade_rfq_invitation_id']) ? (int) $validated['trade_rfq_invitation_id'] : null;
        $dealId = isset($validated['trade_deal_id']) ? (int) $validated['trade_deal_id'] : null;
        $counterpartyId = isset($validated['trade_counterparty_id']) ? (int) $validated['trade_counterparty_id'] : null;

        // Les cles de liaison ne font pas partie des donnees de l'offre
        // elle-meme : elles sont passees separement.
        unset(
            $validated['submit'],
            $validated['trade_rfq_id'],
            $validated['trade_rfq_invitation_id'],
            $validated['trade_deal_id'],
            $validated['trade_counterparty_id'],
        );

        $quote = $this->submitQuote->execute(
            $validated,
            $lines,
            $request->user()?->id,
            $rfqId,
            $invitationId,
            $counterpartyId,
            $dealId,
            $submit,
        );

        $quote->load('lines');

        return new JsonResponse(['data' => TradeQuoteResource::make($quote)->resolve()], 201);
    }

    public function show(TradeQuote $quote): JsonResponse
    {
        $quote->load('lines');

        return new JsonResponse(['data' => TradeQuoteResource::make($quote)->resolve()]);
    }

    /**
     * Accepte l'offre et cree la commande correspondante.
     * Une transition refusee (offre deja acceptee, brouillon jamais emis) est
     * un 422 metier, pas une erreur serveur.
     */
    public function accept(QuoteAcceptRequest $request, TradeQuote $quote): JsonResponse
    {
        try {
            $order = $this->acceptQuote->execute(
                $quote,
                $request->user()?->id,
                $request->validated('expected_delivery_at'),
            );
        } catch (InvalidTradeStateTransitionException $exception) {
            return ApiError::INVALID_STATUS_TRANSITION->response([
                'from' => $exception->from,
                'to' => $exception->to,
            ]);
        }

        $order->load('shipments');

        return new JsonResponse(['data' => TradeOrderResource::make($order)->resolve()], 201);
    }
}
