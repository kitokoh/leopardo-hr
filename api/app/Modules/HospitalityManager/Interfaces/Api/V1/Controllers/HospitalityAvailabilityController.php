<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Disponibilités d'un établissement par type de chambre — HOSP-004 (#7946).
 */
class HospitalityAvailabilityController extends Controller
{
    use ChecksHospitalitySolution;

    public function __construct(
        private readonly HospitalityReservationService $reservations
    ) {
    }

    public function show(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('view', $property);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        return response()->json([
            'data' => [
                'property_id' => (int) $property->getKey(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'room_types' => $this->reservations->availability($actor->company_id, (int) $property->getKey(), $from, $to),
            ],
        ]);
    }
}
