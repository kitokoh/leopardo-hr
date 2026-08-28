<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Infrastructure\Services\FuelShiftPresenceService;
use App\Modules\FuelStation\Infrastructure\Services\FuelShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Présence opérateur sur les shifts FuelStation (FUEL-006, issue #5800).
 *
 * Résout la présence depuis `attendance_logs` (source canonique Attendance,
 * aucun contournement de TenantManager : tout est filtré par company_id).
 * - GET /fuel/shifts/{shift}/presence?date=Y-m-d  → rostre manager
 * - GET /fuel/me/presence?date=Y-m-d              → self-service pompiste
 */
class FuelPresenceController extends Controller
{
    public function __construct(
        private readonly FuelShiftPresenceService $presence,
        private readonly FuelShiftService $shifts,
    ) {}

    public function shiftPresence(Request $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAssignments', $shift);

        /** @var array{date: string} $validated */
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = $validated['date'];

        /** @var Company $company */
        $company = Company::query()->find($shift->company_id);

        $assignments = $this->shifts->assignmentsForShift($shift, Carbon::parse($date), Carbon::parse($date));

        $rows = $assignments->map(function ($assignment) use ($company, $shift, $date): array {
            $employeeId = (int) $assignment->employee_id;

            return array_merge(
                $this->presence->resolveForEmployee(
                    (string) $company->id,
                    (string) ($company->timezone ?? 'UTC'),
                    $employeeId,
                    $date,
                    (string) $shift->start_time,
                    (string) $shift->end_time,
                ),
                [
                    'assignment_id' => $assignment->id,
                    'employee' => [
                        'id' => $assignment->employee?->id,
                        'first_name' => $assignment->employee?->first_name,
                        'last_name' => $assignment->employee?->last_name,
                    ],
                ]
            );
        })->values();

        return response()->json([
            'data' => [
                'shift_id' => $shift->id,
                'date' => $date,
                'presence' => $rows,
            ],
        ]);
    }

    public function myPresence(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        /** @var array{date: string} $validated */
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = $validated['date'];

        /** @var Company $company */
        $company = Company::query()->find($actor->company_id);

        // Affectation du pompiste pour cette date (fenêtre = jour ciblé).
        $assignments = $this->shifts->assignmentsForEmployee(
            $actor,
            Carbon::parse($date),
            Carbon::parse($date),
        );

        $result = $assignments->map(function ($assignment) use ($company, $actor, $date): array {
            $shiftStart = $assignment->shift?->start_time !== null ? (string) $assignment->shift->start_time : null;
            $shiftEnd = $assignment->shift?->end_time !== null ? (string) $assignment->shift->end_time : null;

            return array_merge(
                $this->presence->resolveForEmployee(
                    (string) $company->id,
                    (string) ($company->timezone ?? 'UTC'),
                    $actor->id,
                    $date,
                    $shiftStart,
                    $shiftEnd,
                ),
                [
                    'assignment_id' => $assignment->id,
                    'shift' => $assignment->shift !== null
                        ? [
                            'id' => $assignment->shift->id,
                            'name' => $assignment->shift->name,
                            'start_time' => $assignment->shift->start_time,
                            'end_time' => $assignment->shift->end_time,
                        ]
                        : null,
                ]
            );
        })->values();

        return response()->json([
            'data' => [
                'date' => $date,
                'presence' => $result,
            ],
        ]);
    }



    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
