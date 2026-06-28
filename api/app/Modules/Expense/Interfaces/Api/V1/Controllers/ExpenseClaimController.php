<?php

declare(strict_types=1);

namespace App\Modules\Expense\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Application\Actions\CreateExpenseClaim;
use App\Modules\Expense\Application\Actions\SubmitExpenseClaim;
use App\Modules\Expense\Application\DTOs\CreateExpenseDTO;
use App\Modules\Expense\Domain\Models\ExpenseClaim;
use App\Modules\Expense\Infrastructure\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseClaimController extends Controller
{
    public function __construct(
        private readonly CreateExpenseClaim $createExpenseClaim,
        private readonly SubmitExpenseClaim $submitExpenseClaim,
        private readonly ExpenseService     $expenseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $claims = ExpenseClaim::query()
            ->with('items')
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($claims);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'       => 'required|integer|exists:employees,id',
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'currency'          => 'nullable|string|size:3',
            'items'             => 'required|array|min:1',
            'items.*.category'  => 'required|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.amount'    => 'required|numeric|min:0.01',
            'items.*.expense_date' => 'required|date',
        ]);

        $claim = $this->createExpenseClaim->handle(CreateExpenseDTO::fromArray($validated));

        return response()->json($claim, 201);
    }

    public function show(ExpenseClaim $expenseClaim): JsonResponse
    {
        return response()->json($expenseClaim->load('items'));
    }

    public function submit(ExpenseClaim $expenseClaim): JsonResponse
    {
        $claim = $this->submitExpenseClaim->handle($expenseClaim);

        return response()->json($claim);
    }

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $claim = $this->expenseService->approve($expenseClaim, (int) $request->user()->id);

        return response()->json($claim);
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $claim = $this->expenseService->reject($expenseClaim, (int) $request->user()->id, $request->reason);

        return response()->json($claim);
    }
}
