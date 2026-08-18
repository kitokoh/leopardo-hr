<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExpenseClaimResource;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Modules\Planning\Domain\Models\ExpenseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseClaimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = ExpenseClaim::query()
            ->where('company_id', $actor->company_id)
            ->with('employee:id,first_name,last_name');

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return ExpenseClaimResource::collection(
            $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.category' => 'required|in:transport,meals,accommodation,office,communication,other',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.date' => 'required|date',
        ]);

        $claim = DB::transaction(function () use ($actor, $validated) {
            $totalAmount = collect($validated['items'])->sum('amount');

            // PA2-COUNTRY-003: derive the claim currency from the employee's
            // company so DZD is never shown as the actual currency for
            // companies configured with another currency (MAD, TND, EUR, ...).
            // 'DZD' remains only as a last-resort technical fallback for the
            // rare case where the company record has no currency set.
            $claim = ExpenseClaim::create([
                'company_id' => $actor->company_id,
                'employee_id' => $actor->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'currency' => $actor->company?->currency ?? 'DZD',
            ]);

            foreach ($validated['items'] as $item) {
                ExpenseItem::create([
                    'expense_claim_id' => $claim->id,
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'date' => $item['date'],
                ]);
            }

            return $claim->fresh(['items']);
        });

        return response()->json(['data' => (new ExpenseClaimResource($claim))->resolve($request)], 201);
    }

    public function show(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        abort_unless(
            $expenseClaim->company_id === $actor->company_id &&
            ($actor->isManager() || $expenseClaim->employee_id === $actor->id),
            404
        );

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->load('items')))->resolve($request)]);
    }

    public function update(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $expenseClaim->employee_id === $actor->id, 403);
        // #4933 : modification possible tant que la demande est un brouillon
        // ou rejetée (resoumission après rejet) — jamais soumise/approuvée.
        abort_if(! in_array($expenseClaim->status, ['draft', 'rejected'], true), 422, 'EXPENSE_CLAIM_NOT_EDITABLE');

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.category' => 'required|in:transport,meals,accommodation,office,communication,other',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.date' => 'required|date',
        ]);

        $claim = DB::transaction(function () use ($expenseClaim, $validated): ExpenseClaim {
            $totalAmount = collect($validated['items'])->sum('amount');

            $expenseClaim->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'total_amount' => $totalAmount,
            ]);

            // Remplacement intégral des lignes (pas de mise à jour unitaire).
            $expenseClaim->items()->delete();
            foreach ($validated['items'] as $item) {
                ExpenseItem::create([
                    'expense_claim_id' => $expenseClaim->id,
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'date' => $item['date'],
                ]);
            }

            // Une demande rejetée repasse en brouillon pour resoumission.
            if ($expenseClaim->status === 'rejected') {
                $expenseClaim->update(['status' => 'draft']);
            }

            return $expenseClaim->fresh(['items']);
        });

        return response()->json(['data' => (new ExpenseClaimResource($claim))->resolve($request)], 200);
    }

    public function destroy(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $expenseClaim->employee_id === $actor->id, 403);
        // #4933 : seul un brouillon est supprimable (une demande soumise est
        // dans le circuit d'approbation).
        abort_if($expenseClaim->status !== 'draft', 422, 'EXPENSE_CLAIM_NOT_DELETABLE');

        DB::transaction(function () use ($expenseClaim): void {
            $expenseClaim->items()->delete();
            $expenseClaim->delete();
        });

        return response()->json(null, 204);
    }

    public function submit(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $expenseClaim->employee_id === $actor->id, 403);
        // #4933 : resoumission après rejet — une demande rejetée repasse
        // par le circuit d'approbation (avant : état terminal, l'employé ne
        // pouvait que recréer une demande).
        abort_if(! in_array($expenseClaim->status, ['draft', 'rejected'], true), 422, 'EXPENSE_CLAIM_NOT_SUBMITTABLE');

        $expenseClaim->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        // Return 404 for cross-tenant resources (security: don't leak existence)
        abort_unless($expenseClaim->company_id === $actor->company_id, 404);
        abort_unless($actor->isManager(), 403);

        // Issue #2677 (QA 2026-08-15) — garde de transition : seule une
        // demande soumise peut être approuvée (un brouillon doit d'abord être
        // soumis ; une demande déjà approuvée/rejetée est un état terminal).
        abort_if($expenseClaim->status !== 'submitted', 422, 'EXPENSE_CLAIM_NOT_SUBMITTED');

        $expenseClaim->update([
            'status' => 'approved',
            'approved_by' => (string) $actor->id,
            'approved_at' => now(),
        ]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        // Return 404 for cross-tenant resources (security: don't leak existence)
        abort_unless($expenseClaim->company_id === $actor->company_id, 404);
        abort_unless($actor->isManager(), 403);

        // Issue #2677 — garde de transition : on peut rejeter une demande
        // soumise ou déjà approuvée, jamais un brouillon ou un rejet existant.
        abort_if(! in_array($expenseClaim->status, ['submitted', 'approved'], true), 422, 'EXPENSE_CLAIM_NOT_REJECTABLE');

        $request->validate(['reason' => 'required|string|max:500']);

        $expenseClaim->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason'),
        ]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }
}
