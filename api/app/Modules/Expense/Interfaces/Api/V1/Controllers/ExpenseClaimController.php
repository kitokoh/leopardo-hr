<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExpenseClaimResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
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
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'title'               => 'required|string|max:200',
            'description'         => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.category'    => 'required|in:transport,meals,accommodation,office,communication,other',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount'      => 'required|numeric|min:0.01',
            'items.*.date'        => 'required|date',
        ]);

        $claim = DB::transaction(function () use ($actor, $validated) {
            $totalAmount = collect($validated['items'])->sum('amount');

            $claim = ExpenseClaim::create([
                'company_id'   => $actor->company_id,
                'employee_id'  => $actor->id,
                'title'        => $validated['title'],
                'description'  => $validated['description'] ?? null,
                'status'       => 'draft',
                'total_amount' => $totalAmount,
                'currency'     => 'DZD',
            ]);

            foreach ($validated['items'] as $item) {
                ExpenseItem::create([
                    'expense_claim_id' => $claim->id,
                    'category'         => $item['category'],
                    'description'      => $item['description'],
                    'amount'           => $item['amount'],
                    'date'             => $item['date'],
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

    public function submit(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $expenseClaim->employee_id === $actor->id, 403);
        abort_if($expenseClaim->status !== 'draft', 422);

        $expenseClaim->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $actor->isManager(), 403);

        $expenseClaim->update([
            'status'      => 'approved',
            'approved_by' => (string) $actor->id,
            'approved_at' => now(),
        ]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($expenseClaim->company_id === $actor->company_id && $actor->isManager(), 403);

        $request->validate(['reason' => 'required|string|max:500']);

        $expenseClaim->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->input('reason'),
        ]);

        return response()->json(['data' => (new ExpenseClaimResource($expenseClaim->fresh()))->resolve($request)]);
    }
}
