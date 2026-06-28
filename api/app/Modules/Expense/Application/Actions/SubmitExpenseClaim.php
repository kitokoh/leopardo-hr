<?php

declare(strict_types=1);

namespace App\Modules\Expense\Application\Actions;

use App\Modules\Expense\Domain\Exceptions\ExpenseNotDraftException;
use App\Modules\Expense\Domain\Models\ExpenseClaim;
use App\Modules\Expense\Infrastructure\Services\ExpenseService;

class SubmitExpenseClaim
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * @throws ExpenseNotDraftException
     */
    public function handle(ExpenseClaim $claim): ExpenseClaim
    {
        return $this->expenseService->submit($claim);
    }
}
