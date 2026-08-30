<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\ClosePosSessionAction;
use App\Modules\RestaurantManager\Application\Actions\OpenPosSessionAction;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\CloseRestaurantPosSessionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPosSessionRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPosSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-401 (#6188) — Sessions de caisse POS (ouverture / consultation /
 * clôture). Une seule session ouverte par branche (409), clôture immuable
 * (verrou optimiste `version`), totaux recalculés serveur.
 */
class RestaurantPosSessionController extends Controller
{
    public function __construct(
        private readonly OpenPosSessionAction $openAction,
        private readonly ClosePosSessionAction $closeAction,
    ) {
    }

    public function store(StoreRestaurantPosSessionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantPosSession::class)) {
            abort(403);
        }

        $session = $this->openAction->open($actor, $request->validated());

        return (new RestaurantPosSessionResource($session))->response()->setStatusCode(201);
    }

    /**
     * Session en cours de la branche (ou de la première branche du tenant).
     */
    public function current(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantPosSession::class)) {
            abort(403);
        }

        $query = RestaurantPosSession::query()
            ->where('company_id', $actor->company_id)
            ->where('status', PosSessionStatus::OPEN->value);

        if ($request->has('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        $session = $query->orderByDesc('opened_at')->first();

        if (! $session instanceof RestaurantPosSession) {
            return new JsonResponse(['data' => null]);
        }

        return (new RestaurantPosSessionResource($session))->response();
    }

    public function show(Request $request, RestaurantPosSession $restaurantPosSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPosSession->company_id) {
            abort(404);
        }

        return (new RestaurantPosSessionResource($restaurantPosSession))->response();
    }

    public function close(CloseRestaurantPosSessionRequest $request, RestaurantPosSession $restaurantPosSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPosSession->company_id) {
            abort(404);
        }

        if ($actor->cannot('close', $restaurantPosSession)) {
            abort(403);
        }

        $session = $this->closeAction->close($actor, $restaurantPosSession, $request->validated());

        return (new RestaurantPosSessionResource($session))->response();
    }
}
