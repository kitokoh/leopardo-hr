<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExpenseAccountingEntryResource;
use App\Modules\Expense\Infrastructure\Services\ExpenseAccountingEntryService;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5235 — Phase C : écritures comptables des notes de frais.
 *
 * Lecture (principal/comptable) et régénération (comptable) des écritures
 * générées automatiquement à l'approbation d'un ExpenseClaim. RH/employé
 * exclus (il ne touche qu'à sa note).
 */
class ExpenseAccountingController extends Controller
{
    public function __construct(
        private readonly ExpenseAccountingEntryService $entries,
    ) {}

    /**
     * GET /api/v1/expense-claims/{claim}/accounting-entries
     * — écritures de la note (traçabilité note → comptabilité).
     */
    public function index(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }

        return ExpenseAccountingEntryResource::collection($this->entries->entriesForClaim($expenseClaim))->response();
    }

    /**
     * POST /api/v1/expense-claims/{claim}/accounting-entries/regenerate
     * — régénération idempotente (comptable).
     */
    public function regenerate(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }
        // #5235 : la régénération des écritures est réservée au comptable.
        if ($actor->hasManagerRole('comptable') === false) {
            abort(403, 'INSUFFICIENT_ROLE');
        }

        try {
            $count = $this->entries->generateForClaim($expenseClaim, $actor);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'EXPENSE_ENTRIES_GENERATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'expense_claim_id' => $expenseClaim->id,
            'generated_lines' => $count,
            'balance' => $this->entries->balanceForClaim($expenseClaim),
        ]);
    }
}
