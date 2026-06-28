<?php

declare(strict_types=1);

namespace App\Modules\Expense\Infrastructure\Services;

use App\Modules\Expense\Application\DTOs\CreateExpenseDTO;
use App\Modules\Expense\Domain\Exceptions\ExpenseNotDraftException;
use App\Modules\Expense\Domain\Models\ExpenseClaim;
use App\Modules\Expense\Domain\Models\ExpenseItem;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function create(CreateExpenseDTO $dto): ExpenseClaim
    {
        return DB::transaction(function () use ($dto) {
            $totalAmount = collect($dto->items)->sum('amount');

            $claim = ExpenseClaim::create([
                'employee_id'  => $dto->employeeId,
                'title'        => $dto->title,
                'description'  => $dto->description,
                'currency'     => $dto->currency,
                'total_amount' => $totalAmount,
                'status'       => 'draft',
            ]);

            foreach ($dto->items as $item) {
                ExpenseItem::create(array_merge($item, [
                    'expense_claim_id' => $claim->id,
                ]));
            }

            return $claim->load('items');
        });
    }

    /**
     * @throws ExpenseNotDraftException
     */
    public function submit(ExpenseClaim $claim): ExpenseClaim
    {
        if ($claim->status !== 'draft') {
            throw new ExpenseNotDraftException();
        }

        $claim->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return $claim->fresh();
    }

    public function approve(ExpenseClaim $claim, int $approvedBy): ExpenseClaim
    {
        $claim->update([
            'status'      => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $claim->fresh();
    }

    public function reject(ExpenseClaim $claim, int $rejectedBy, string $reason): ExpenseClaim
    {
        $claim->update([
            'status'           => 'rejected',
            'approved_by'      => $rejectedBy,
            'rejection_reason' => $reason,
        ]);

        return $claim->fresh();
    }
}
