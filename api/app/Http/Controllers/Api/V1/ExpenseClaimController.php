<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExpenseClaimResource;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return ExpenseClaimResource::collection($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)))
            ->response();
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

        $claim = ExpenseClaim::create([
            'company_id' => $actor->company_id,
            'employee_id' => $actor->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'total_amount' => 0,
        ]);

        $total = 0;
        foreach ($validated['items'] as $itemData) {
            ExpenseItem::create([
                'expense_claim_id' => $claim->id,
                ...$itemData,
            ]);
            $total += $itemData['amount'];
        }

        $claim->update(['total_amount' => $total]);

        return (new ExpenseClaimResource($claim->load('items')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $expenseClaim->employee_id !== $actor->id) {
            abort(403);
        }

        return (new ExpenseClaimResource($expenseClaim->load(['employee:id,first_name,last_name', 'items'])))->response();
    }

    public function submit(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($expenseClaim->employee_id !== $actor->id) {
            abort(403);
        }
        if ($expenseClaim->status !== 'draft') {
            abort(422, 'Only draft claims can be submitted.');
        }

        $expenseClaim->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return (new ExpenseClaimResource($expenseClaim->fresh()))->response();
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }
        if ($expenseClaim->status !== 'submitted') {
            abort(422, 'Only submitted claims can be approved.');
        }

        $expenseClaim->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return (new ExpenseClaimResource($expenseClaim->fresh()))->response();
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($expenseClaim->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }
        if ($expenseClaim->status !== 'submitted') {
            abort(422, 'Only submitted claims can be rejected.');
        }

        $expenseClaim->update(['status' => 'rejected']);

        return (new ExpenseClaimResource($expenseClaim->fresh()))->response();
    }
}
