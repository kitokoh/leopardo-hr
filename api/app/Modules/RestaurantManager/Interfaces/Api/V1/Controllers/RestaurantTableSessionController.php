<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\CloseTableSessionAction;
use App\Modules\RestaurantManager\Application\Actions\OpenTableSessionAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\CloseRestaurantTableRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\OpenRestaurantTableRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantTableSessionResource;
use Illuminate\Http\JsonResponse;

/**
 * RESTO-409 (#6196) — Occupation des tables : ouverture / clôture de session.
 *
 * `POST /restaurant/tables/{table}/open` : table occupée → 409.
 * `POST /restaurant/tables/{table}/close` : clôture immuable + événement
 * `restaurant.table.closed.v1`. 404 sûr cross-tenant.
 */
class RestaurantTableSessionController extends Controller
{
    public function __construct(
        private readonly OpenTableSessionAction $openAction,
        private readonly CloseTableSessionAction $closeAction,
    ) {
    }

    public function open(OpenRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTable->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantTableSession::class)) {
            abort(403);
        }

        $session = $this->openAction->open($actor, $restaurantTable, $request->validated());

        return (new RestaurantTableSessionResource($session))->response()->setStatusCode(201);
    }

    public function close(CloseRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTable->company_id) {
            abort(404);
        }

        $session = RestaurantTableSession::query()
            ->where('company_id', $actor->company_id)
            ->where('table_id', $restaurantTable->id)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->first();

        if (! $session instanceof RestaurantTableSession) {
            abort(404, 'No open session for this table.');
        }

        if ($actor->cannot('close', $session)) {
            abort(403);
        }

        $session = $this->closeAction->close($actor, $session);

        return (new RestaurantTableSessionResource($session))->response();
    }
}
