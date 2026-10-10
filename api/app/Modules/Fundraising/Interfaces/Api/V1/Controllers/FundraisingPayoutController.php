<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Application\Actions\RequestPayoutAction;
use App\Modules\Fundraising\Application\Actions\TransitionPayoutAction;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\RequestPayoutRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\PayoutResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reversements des cagnottes vers les bénéficiaires (verticale
 * FUNDRAISING — spec §5.2). Argent sortant : gestion strictement réservée
 * au responsable du tenant (policy + groupe api.manager:principal,rh).
 */
final class FundraisingPayoutController extends Controller
{
    public function __construct(
        private readonly RequestPayoutAction $requestPayout,
        private readonly TransitionPayoutAction $transitions,
    ) {}

    public function index(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $fundraiser)) {
            abort(403);
        }

        $payouts = FundraisingPayout::query()
            ->where('fundraiser_id', $fundraiser->id)
            ->latest('id')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 15))));

        return PayoutResource::collection($payouts)->response();
    }

    public function store(RequestPayoutRequest $request, Fundraiser $fundraiser): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', FundraisingPayout::class)) {
            abort(403);
        }

        $payout = $this->requestPayout->handle(
            $fundraiser,
            $request->validated(),
            is_scalar($actor->id ?? null) ? (string) $actor->id : null,
        );

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    public function process(Request $request, FundraisingPayout $payout): JsonResponse
    {
        return $this->transition($request, $payout, 'process');
    }

    public function markPaid(Request $request, FundraisingPayout $payout): JsonResponse
    {
        return $this->transition($request, $payout, 'markPaid');
    }

    public function fail(Request $request, FundraisingPayout $payout): JsonResponse
    {
        return $this->transition($request, $payout, 'fail');
    }

    public function cancel(Request $request, FundraisingPayout $payout): JsonResponse
    {
        return $this->transition($request, $payout, 'cancel');
    }

    private function transition(Request $request, FundraisingPayout $payout, string $verb): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $payout)) {
            abort(403);
        }

        $actorId = is_scalar($actor->id ?? null) ? (string) $actor->id : null;

        $payout = match ($verb) {
            'process' => $this->transitions->process($payout, $actorId),
            'markPaid' => $this->transitions->markPaid(
                $payout,
                is_string($request->input('provider_reference')) ? $request->input('provider_reference') : null,
                $actorId,
            ),
            'fail' => $this->transitions->fail(
                $payout,
                (string) $request->input('reason', 'échec déclaré par le responsable'),
                $actorId,
            ),
            'cancel' => $this->transitions->cancel($payout),
            default => throw FundraisingException::invalidStatusTransition($payout->status->value, $verb),
        };

        return (new PayoutResource($payout))->response();
    }
}
