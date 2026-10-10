<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Application\Actions\RecordVtcDriverPositionAction;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\DriverPositionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Ingestion des positions chauffeurs (BC-34 VTC, VTC-05/#8361).
 *
 * `POST /v1/vtc/driver/position` (throttle strict, spec §7) : idempotente
 * par (driver_id, recorded_at) — un rejeu retourne 200 sans doublon ; la
 * dernière position connue (base du dispatch) n'avance jamais en arrière.
 */
final class VtcDriverPositionController extends VtcDriverBaseController
{
    public function __construct(
        private readonly RecordVtcDriverPositionAction $recordPosition,
    ) {}

    public function store(DriverPositionRequest $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        /** @var array{lat: numeric-string, lng: numeric-string, recorded_at?: string|null, source?: string|null} $validated */
        $validated = $request->validated();

        $recordedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : Carbon::now();

        [$position, $replayed] = $this->recordPosition->execute(
            $driver,
            (float) $validated['lat'],
            (float) $validated['lng'],
            $recordedAt,
            $validated['source'] ?? 'app',
        );

        return response()->json([
            'data' => [
                'id' => $position->id,
                'recorded_at' => $position->recorded_at?->toIso8601String(),
                'idempotent_replay' => $replayed,
            ],
        ], $replayed ? 200 : 201);
    }
}
