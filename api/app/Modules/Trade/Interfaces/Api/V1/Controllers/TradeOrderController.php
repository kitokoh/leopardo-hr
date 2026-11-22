<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Application\Actions\TransitionOrderAction;
use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;
use App\Modules\Trade\Domain\Models\TradeOrder;
use App\Modules\Trade\Interfaces\Api\V1\Requests\OrderTransitionRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeOrderResource;
use App\Shared\Enums\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes Trade — BC-36 TRADE, #9779.
 *
 * ## Le trou que ce controleur ferme
 *
 * `AcceptQuoteAction` cree une `TradeOrder` (statut `confirmed`, total fige) et
 * `TradeOrderResource` existait deja — mais **aucune route ne servait cette
 * ressource**. Concretement : le vendeur acceptait une offre, la commande
 * naissait... et disparaissait de son espace. Pas de liste, pas de detail, pas
 * de changement de statut, alors que `TradeOrderStateMachine` implementait tout
 * le cycle.
 *
 * Une commande qu'on ne peut ni voir ni faire avancer n'est pas une commande :
 * c'est une ligne en base.
 *
 * ## Une seule ressource, deux vues
 *
 * `?direction=purchase|sale` distingue, comme pour les offres, ce que
 * l'entreprise ACHETE de ce qu'elle VEND. Un importateur-exportateur a besoin
 * des deux depuis le meme espace : c'est la meme entreprise, deux flux.
 *
 * ## Verdict de transition
 *
 * Une transition interdite n'est PAS un 500 : c'est un `422
 * INVALID_STATUS_TRANSITION` qui nomme l'etat de depart et l'etat vise.
 */
final class TradeOrderController
{
    public function __construct(
        private readonly TransitionOrderAction $transitionOrder,
    ) {}

    /**
     * GET /trade/orders — liste paginee.
     *
     * Filtres : `direction`, `status`, `trade_counterparty_id`.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeOrder::query()
            ->with('shipments')
            ->orderByDesc('id');

        if ($request->filled('direction')) {
            $query->where('direction', (string) $request->string('direction'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('trade_counterparty_id')) {
            $query->where('trade_counterparty_id', $request->integer('trade_counterparty_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeOrderResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    /**
     * GET /trade/orders/{order} — detail, expeditions incluses.
     */
    public function show(TradeOrder $order): JsonResponse
    {
        $order->load('shipments');

        return new JsonResponse(['data' => TradeOrderResource::make($order)->resolve()]);
    }

    /**
     * POST /trade/orders/{order}/transition — avance (ou annule) la commande.
     *
     * L'utilisateur envoie la CIBLE, pas un chemin : la machine a etats decide.
     */
    public function transition(OrderTransitionRequest $request, TradeOrder $order): JsonResponse
    {
        /** @var string $target */
        $target = $request->validated('status');

        try {
            $updated = $this->transitionOrder->execute(
                $order,
                TradeOrderStatus::from($target),
                [
                    'cancel_reason' => $request->validated('cancel_reason'),
                    'expected_delivery_at' => $request->validated('expected_delivery_at'),
                ],
            );
        } catch (InvalidTradeStateTransitionException $exception) {
            return ApiError::INVALID_STATUS_TRANSITION->response([
                'from' => $exception->from,
                'to' => $exception->to,
            ]);
        }

        $updated->load('shipments');

        return new JsonResponse(['data' => TradeOrderResource::make($updated)->resolve()]);
    }
}
