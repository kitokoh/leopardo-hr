<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Application\Actions\UpdateVtcDriverAvailabilityAction;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\DriverAvailabilityRequest;
use Illuminate\Http\JsonResponse;

/**
 * Disponibilité du chauffeur (BC-34 VTC, VTC-05/#8361).
 *
 * `POST /v1/vtc/driver/availability` : passage available ⇄ offline —
 * `available` rend le chauffeur éligible au dispatch (VTC-04). Les statuts
 * `busy` et `suspended` verrouillent la bascule (409 VTC_AVAILABILITY_LOCKED).
 */
final class VtcDriverAvailabilityController extends VtcDriverBaseController
{
    public function __construct(
        private readonly UpdateVtcDriverAvailabilityAction $updateAvailability,
    ) {}

    public function update(DriverAvailabilityRequest $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        /** @var array{available: bool} $validated */
        $validated = $request->validated();

        $driver = $this->updateAvailability->execute($driver, (bool) $validated['available']);

        return response()->json([
            'data' => [
                'driver_id' => $driver->id,
                'status' => $driver->status->value,
            ],
        ]);
    }
}
