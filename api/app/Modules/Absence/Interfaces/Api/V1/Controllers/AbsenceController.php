<?php

declare(strict_types=1);

namespace App\Modules\Absence\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Absence\Application\Actions\ApproveAbsence;
use App\Modules\Absence\Application\Actions\RejectAbsence;
use App\Modules\Absence\Application\Actions\RequestAbsence;
use App\Modules\Absence\Application\DTOs\RequestAbsenceDTO;
use App\Modules\Absence\Domain\Models\Absence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function __construct(
        private readonly RequestAbsence $requestAbsence,
        private readonly ApproveAbsence $approveAbsence,
        private readonly RejectAbsence  $rejectAbsence,
    ) {}

    /**
     * List absences for the authenticated employee's company.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Absence::query()
            ->with(['employee', 'absenceType'])
            ->when($request->has('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->status))
            ->latest();

        return response()->json($query->paginate(20));
    }

    /**
     * Submit a new absence request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'      => 'required|integer|exists:employees,id',
            'absence_type_id'  => 'required|integer|exists:absence_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string|max:1000',
        ]);

        $absence = $this->requestAbsence->handle(RequestAbsenceDTO::fromArray($validated));

        return response()->json($absence, 201);
    }

    /**
     * Show a single absence.
     */
    public function show(Absence $absence): JsonResponse
    {
        return response()->json($absence->load(['employee', 'absenceType']));
    }

    /**
     * Approve a pending absence.
     */
    public function approve(Request $request, Absence $absence): JsonResponse
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        $absence = $this->approveAbsence->handle(
            $absence,
            (int) $request->user()->id,
            $request->comment
        );

        return response()->json($absence);
    }

    /**
     * Reject a pending absence.
     */
    public function reject(Request $request, Absence $absence): JsonResponse
    {
        $request->validate(['comment' => 'required|string|max:500']);

        $absence = $this->rejectAbsence->handle(
            $absence,
            (int) $request->user()->id,
            $request->comment
        );

        return response()->json($absence);
    }
}
