<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\MeterReadingService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CorrectReadingRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ReviewIntervalRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreMeterReadingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relevés de compteur FuelStation — FUEL-004 (spec §13.4).
 *
 * Toutes les routes sont tenant-scoped ; la solution doit être ACTIVE sur
 * le tenant (feature flag `fuel_station`, ADR activation #5795), sinon
 * 403 (fail-closed). Enregistrement : employé authentifié du tenant.
 * Corrections et revues : manager principal/rh (Policy).
 */
class FuelMeterReadingController extends Controller
{
    public function __construct(
        private readonly MeterReadingService $service,
    ) {}

    public function record(
        StoreMeterReadingRequest $request,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
    ): JsonResponse {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        /** @var array{reading_value_minor: int, reading_unit?: string, captured_at?: string|null, timezone?: string, shift_id?: int|null, device_reference?: string|null, idempotency_key: string} $validated */
        $validated = $request->validated();

        $result = $this->service->record(
            $station,
            $pump,
            $meter,
            $validated,
            $actor,
        );

        // Rejeu idempotent → 200 (même résultat, aucune écriture).
        $status = ($result['replayed'] ?? false) === true ? 200 : 201;

        return response()->json(['data' => $result], $status);
    }

    public function index(
        Request $request,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
    ): JsonResponse {
        $this->assertSolutionActive();

        $readings = FuelMeterReading::query()
            ->where('meter_id', (int) $meter->getAttribute('id'))
            ->orderByDesc('captured_at_utc')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $readings->map(fn (FuelMeterReading $reading): array => [
                'id' => (int) $reading->getAttribute('id'),
                'reading_value_minor' => (int) $reading->getAttribute('reading_value_minor'),
                'reading_unit' => $reading->getAttribute('reading_unit'),
                'captured_at_utc' => $reading->getAttribute('captured_at_utc')?->toIso8601String(),
                'status' => $reading->getAttribute('status'),
                'source_code' => $reading->getAttribute('source_code'),
            ]),
        ]);
    }

    public function intervals(
        Request $request,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
    ): JsonResponse {
        $this->assertSolutionActive();

        $intervals = FuelMeterInterval::query()
            ->where('meter_id', (int) $meter->getAttribute('id'))
            ->orderByDesc('calculated_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $intervals->map(fn (FuelMeterInterval $interval): array => [
                'id' => (int) $interval->getAttribute('id'),
                'previous_reading_id' => (int) $interval->getAttribute('previous_reading_id'),
                'current_reading_id' => (int) $interval->getAttribute('current_reading_id'),
                'delta_minor' => (int) $interval->getAttribute('delta_minor'),
                'interval_seconds' => (int) $interval->getAttribute('interval_seconds'),
                'calculation_status' => $interval->getAttribute('calculation_status'),
            ]),
        ]);
    }

    public function correct(CorrectReadingRequest $request, FuelMeterReading $reading): JsonResponse
    {
        $this->assertSolutionActive();
        $this->authorize('correct', $reading);

        /** @var Employee $actor */
        $actor = $request->user();

        $result = $this->service->correct(
            $reading,
            (string) $request->input('reason'),
            (int) $request->input('reading_value_minor'),
            $actor,
        );

        return response()->json(['data' => $result]);
    }

    public function review(ReviewIntervalRequest $request, FuelMeterInterval $interval): JsonResponse
    {
        $this->assertSolutionActive();
        $this->authorize('review', $interval);

        /** @var Employee $actor */
        $actor = $request->user();

        $result = $this->service->review(
            $interval,
            (string) $request->input('decision'),
            $request->input('note'),
            $actor,
        );

        return response()->json(['data' => $result]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
