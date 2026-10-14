<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use App\Modules\Trade\Application\Actions\CreateRfqAction;
use App\Modules\Trade\Application\Actions\SendRfqAction;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Interfaces\Api\V1\Requests\RfqSendRequest;
use App\Modules\Trade\Interfaces\Api\V1\Requests\RfqStoreRequest;
use App\Modules\Trade\Interfaces\Api\V1\Resources\TradeRfqResource;
use App\Shared\Enums\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Demandes de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Toutes les routes sont derriere `module.trade` + `trade.role:purchase,sales`
 * (voir routes/modules/trade.php). Le perimetre tenant est applique par
 * `BelongsToCompany` : une ressource d'une autre entreprise est introuvable,
 * donc la reponse est un 404 et non un 403 qui revelerait son existence.
 */
final class TradeRfqController
{
    public function __construct(
        private readonly CreateRfqAction $createRfq,
        private readonly SendRfqAction $sendRfq,
    ) {}

    /**
     * Liste les RFQ du tenant. Filtre `status` optionnel (validation legere :
     * un statut inconnu est simplement sans resultat, pas une erreur 500).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TradeRfq::query()
            ->with(['lines'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('deal_id')) {
            $query->where('trade_deal_id', $request->integer('deal_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return new JsonResponse([
            'data' => TradeRfqResource::collection($query->paginate($perPage))->resolve(),
        ]);
    }

    public function store(RfqStoreRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var list<array<string, mixed>> $lines */
        $lines = $validated['lines'];
        unset($validated['lines']);

        $rfq = $this->createRfq->execute(
            $validated,
            $lines,
            $request->user()?->id,
            isset($validated['trade_deal_id']) ? (int) $validated['trade_deal_id'] : null,
        );

        $rfq->load(['lines', 'invitations']);

        return new JsonResponse(['data' => TradeRfqResource::make($rfq)->resolve()], 201);
    }

    public function show(TradeRfq $rfq): JsonResponse
    {
        $rfq->load(['lines', 'invitations', 'quotes']);

        return new JsonResponse(['data' => TradeRfqResource::make($rfq)->resolve()]);
    }

    /**
     * Diffuse la RFQ aux contreparties et retourne les liens de reponse EN
     * CLAIR, une seule fois. Ils ne sont jamais relisibles ensuite.
     */
    public function send(RfqSendRequest $request, TradeRfq $rfq): JsonResponse
    {
        /** @var list<int> $counterpartyIds */
        $counterpartyIds = $request->validated('counterparty_ids');

        try {
            [$updated, $tokens] = $this->sendRfq->execute(
                $rfq,
                $counterpartyIds,
                $request->user()?->id,
                $request->validated('message'),
                (int) ($request->validated('token_lifetime_days') ?? 30),
            );
        } catch (InvalidTradeStateTransitionException $exception) {
            return ApiError::INVALID_STATUS_TRANSITION->response([
                'from' => $exception->from,
                'to' => $exception->to,
            ]);
        }

        $updated->load(['lines', 'invitations']);

        return new JsonResponse([
            'data' => TradeRfqResource::make($updated)->resolve(),
            // ⚠️ Transmis UNE SEULE FOIS : ces liens ne sont pas relisibles.
            'response_links' => array_map(
                static fn (int $counterpartyId, string $token): array => [
                    'counterparty_id' => $counterpartyId,
                    'token' => $token,
                ],
                array_keys($tokens),
                array_values($tokens),
            ),
            'notice' => 'Ces liens de reponse ne sont affiches qu\'une fois. Conservez-les avant de fermer cette page.',
        ]);
    }
}
