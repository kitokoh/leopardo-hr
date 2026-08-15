<?php

declare(strict_types=1);

namespace App\Modules\Absence\Interfaces\Api\V1\Controllers;

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
        /** @var \App\Core\Auth\Domain\Models\Employee $actor */
        $actor = $request->user();

        // Issue #3055 : la copie Absence de ce contrôleur n'avait aucune garde
        // de rôle — un employé lisait les soldes de n'importe quel collègue.
        // Un employé ne lit que ses propres soldes ; les managers de
        // l'entreprise peuvent lire ceux de leurs équipes (même règle que la
        // copie Planning sous /leave-balances).
        if (! $actor->isManager() && (int) $actor->id !== $employeeId) {
            abort(403);
        }

        $balances = LeaveBalance::query()
            ->with('absenceType')
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
