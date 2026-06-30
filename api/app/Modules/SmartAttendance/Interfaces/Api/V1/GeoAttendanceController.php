<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\SmartAttendance\Application\Actions\ProcessGeoEntry;
use App\Modules\SmartAttendance\Application\Actions\ProcessGeoExit;
use App\Modules\SmartAttendance\Application\DTOs\GeoEventDTO;
use App\Modules\SmartAttendance\Domain\Exceptions\OutsideGeofenceException;
use App\Modules\SmartAttendance\Domain\Exceptions\SessionAlreadyOpenException;
use App\Modules\SmartAttendance\Interfaces\Api\V1\Requests\GeoEventRequest;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints mobiles — réception des événements GPS depuis l'app employé.
 * Middleware: auth:sanctum + tenant
 */
class GeoAttendanceController extends Controller
{
    public function __construct(
        private readonly ProcessGeoEntry $processEntry,
        private readonly ProcessGeoExit  $processExit,
    ) {}

    /**
     * POST /api/v1/smart-attendance/geo-events
     *
     * Reçoit un événement zone_enter ou zone_exit depuis le mobile.
     */
    public function event(GeoEventRequest $request): JsonResponse
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = auth()->user();
        $company  = currentCompany();

        $dto = GeoEventDTO::fromRequest(
            employeeId: $employee->id,
            companyId:  (string) $company->id,
            data:       $request->validated(),
        );

        try {
            $result = match ($dto->eventType) {
                'zone_enter' => $this->processEntry->handle($dto),
                'zone_exit'  => $this->processExit->handle($dto),
                default      => null,
            };

            if ($result === null) {
                return response()->json([
                    'message' => 'Event processed (no open session found for exit).',
                    'data'    => null,
                ]);
            }

            return response()->json([
                'message'    => 'Geo event processed successfully.',
                'data'       => [
                    'session_id'       => $result->id,
                    'status'           => $result->status,
                    'started_at'       => $result->started_at?->toIso8601String(),
                    'ended_at'         => $result->ended_at?->toIso8601String(),
                    'duration_seconds' => $result->duration_seconds,
                ],
            ], 201);

        } catch (SessionAlreadyOpenException $e) {
            return response()->json([
                'message' => 'Une session est déjà ouverte pour cet employé.',
                'code'    => 'SESSION_ALREADY_OPEN',
            ], 409);

        } catch (OutsideGeofenceException $e) {
            return response()->json([
                'message' => 'Position hors zone de présence.',
                'code'    => 'OUTSIDE_GEOFENCE',
                'detail'  => $e->getMessage(),
            ], 422);
        }
    }
}
