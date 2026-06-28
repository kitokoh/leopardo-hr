<?php

declare(strict_types=1);

namespace App\Modules\Absence\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Absence\Domain\Models\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    /**
     * Get leave balances for an employee.
     */
    public function balances(Request $request, int $employeeId): JsonResponse
    {
        $balances = LeaveBalance::query()
            ->with('absenceType')
            ->where('employee_id', $employeeId)
            ->where('year', $request->input('year', now()->year))
            ->get()
            ->map(fn ($b) => [
                'absence_type' => $b->absenceType,
                'year'         => $b->year,
                'allocated'    => $b->allocated,
                'used'         => $b->used,
                'carried_over' => $b->carried_over ?? 0,
                'balance'      => $b->balance,
            ]);

        return response()->json($balances);
    }
}
