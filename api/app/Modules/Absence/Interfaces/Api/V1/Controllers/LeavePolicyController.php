<?php

declare(strict_types=1);

namespace App\Modules\Absence\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    /**
     * Get leave balances for an employee.
     */
    public function balances(Request $request, int $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $balances = LeaveBalance::query()
            ->with('absenceType')
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $employeeId)
            ->where('year', $request->input('year', now()->year))
            ->get()
            ->map(fn ($b) => [
                'absence_type' => $b->absenceType,
                'year'         => $b->year,
                'balance'      => $b->balance,
                'used'         => $b->used,
                'pending'      => $b->pending,
                'remaining'    => $b->balance - $b->used,
            ]);

        return response()->json($balances);
    }
}
