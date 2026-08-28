<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelReadingException;
use App\Modules\FuelStation\Domain\Models\FuelMeter;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Infrastructure\Services\FuelMeterReadingService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CorrectFuelMeterReadingRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMeterReadingRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Resources\FuelMeterReadingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Relevés de compteur FuelStation (issue #5798).
 *
 * RBAC : api.manager:principal,rh. Idempotence par (company_id, meter_id,
 * reading_at) ; décroissance → anomalie ; corrections versionnées.
 */
class FuelMeterReadingController extends Controller
{
    public function __construct(private readonly FuelMeterReadingService $service) {}

    public function store(StoreFuelMeterReadingRequest $request, string $meter): JsonResponse
    {
        $meterModel = $this->meterOrFail($meter);

        $reading = $this->service->record(
            $meterModel,
            (float) $request->validated('reading_value'),
            new \Illuminate\Support\Carbon($request->validated('reading_at')),
            [
                'pump_id' => $meterModel->pump_id,
                'operator_id' => $request->input('operator_id'),
                'shift_id' => $request->input('shift_id'),
                'reading_at_local' => $request->input('reading_at_local'),
                'source' => $request->input('source', 'manual'),
                'note' => $request->input('note'),
                'created_by' => (string) ($request->user()?->id ?? ''),
            ],
        );

        return (new FuelMeterReadingResource($reading))->response()->setStatusCode(201);
    }

    /** @return AnonymousResourceCollection<int, FuelMeterReadingResource> */
    public function index(string $meter): AnonymousResourceCollection
    {
        $this->meterOrFail($meter);

        return FuelMeterReadingResource::collection(
            FuelMeterReading::query()
                ->where('meter_id', $meter)
                ->orderByDesc('reading_at')
                ->paginate(50),
        );
    }

    public function correct(CorrectFuelMeterReadingRequest $request, string $reading): JsonResponse
    {
        $readingModel = FuelMeterReading::query()->where('id', $reading)->first();
        if ($readingModel === null) {
            throw new FuelReadingException('Relevé introuvable.', 'FUEL_READING_NOT_FOUND', 404);
        }

        try {
            $updated = $this->service->correct(
                $readingModel,
                (float) $request->validated('new_value'),
                (string) $request->validated('reason'),
                (string) ($request->user()?->id ?? ''),
            );
        } catch (FuelReadingException $e) {
            return new JsonResponse(['error' => $e->errorCode()], $e->httpStatus());
        }

        return (new FuelMeterReadingResource($updated))->response();
    }

    private function meterOrFail(string $meterId): FuelMeter
    {
        $meter = FuelMeter::query()->where('id', $meterId)->first();
        if ($meter === null) {
            throw new FuelReadingException('Compteur introuvable dans le tenant courant.', 'FUEL_METER_NOT_FOUND', 404);
        }

        return $meter;
    }
}
