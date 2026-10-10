<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Vtc\Application\Actions\CancelRideAction;
use App\Modules\Vtc\Application\Actions\RequestRideAction;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Modules\Vtc\Domain\ValueObjects\IdempotencyKey;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\RideCancelRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\RideStoreRequest;
use App\Modules\Vtc\Interfaces\Api\V1\Resources\VtcRideResource;
use App\Shared\Geo\GeoPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Courses VTC — surface passager (BC-34 VTC, VTC-03/#8359).
 *
 *   - `POST /v1/vtc/rides` : création IDEMPOTENTE (Idempotency-Key → rejeu
 *     transparent, 200 la course existante ; 201 sinon) ;
 *   - `GET /v1/vtc/rides/{id}` : consultation — SA course uniquement (les
 *     managers du tenant conservent la visibilité ops ; 404 fail-closed
 *     uniforme sinon, jamais un 403 qui révélerait l'existence) ;
 *   - `POST /v1/vtc/rides/{id}/cancel` : annulation avant acceptation,
 *     motif tracé.
 */
final class VtcRideController
{
    public function __construct(
        private readonly RequestRideAction $requestRide,
        private readonly CancelRideAction $cancelRide,
    ) {}

    public function store(RideStoreRequest $request): JsonResponse
    {
        /** @var array{idempotency_key: string, pickup: array<string, mixed>, dropoff: array<string, mixed>, pickup_address?: string|null, dropoff_address?: string|null, passenger_name?: string|null, passenger_phone?: string|null, fare_profile_id?: int|null} $validated */
        $validated = $request->validated();

        [$ride, $replayed] = $this->requestRide->execute(
            [
                'passenger_user_id' => $request->user()?->getAuthIdentifier(),
                'passenger_name' => $validated['passenger_name'] ?? null,
                'passenger_phone' => $validated['passenger_phone'] ?? null,
                'pickup_address' => $validated['pickup_address'] ?? null,
                'dropoff_address' => $validated['dropoff_address'] ?? null,
                'fare_profile_id' => $validated['fare_profile_id'] ?? null,
            ],
            GeoPoint::fromArray($validated['pickup']),
            GeoPoint::fromArray($validated['dropoff']),
            IdempotencyKey::fromString($validated['idempotency_key']),
        );

        return (new VtcRideResource($ride))
            ->response()
            ->setStatusCode($replayed ? 200 : 201);
    }

    public function show(Request $request, int $id): VtcRideResource
    {
        return new VtcRideResource($this->findVisibleRide($request, $id));
    }

    public function cancel(RideCancelRequest $request, int $id): VtcRideResource
    {
        $ride = $this->findVisibleRide($request, $id);

        /** @var array{reason: string} $validated */
        $validated = $request->validated();

        return new VtcRideResource(
            $this->cancelRide->execute($ride, $validated['reason'], 'passenger')
        );
    }

    /**
     * Course visible par l'appelant : la SIENNE, ou n'importe quelle course
     * du tenant pour un manager (ops) — 404 uniforme sinon (fail-closed).
     */
    private function findVisibleRide(Request $request, int $id): VtcRide
    {
        /** @var VtcRide|null $ride */
        $ride = VtcRide::query()->whereKey($id)->first();

        if (! $ride instanceof VtcRide) {
            abort(404);
        }

        $user = $request->user();

        $isOwner = $user !== null && $ride->passenger_user_id === $user->getAuthIdentifier();
        $isManager = $user instanceof Employee && $user->isManager();

        if (! $isOwner && ! $isManager) {
            abort(404);
        }

        return $ride;
    }
}
