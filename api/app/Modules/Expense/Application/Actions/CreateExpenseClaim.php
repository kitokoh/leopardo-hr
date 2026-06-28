<?php

declare(strict_types=1);

namespace App\Modules\Expense\Application\Actions;

use App\Modules\Expense\Application\DTOs\CreateExpenseDTO;
use App\Modules\Expense\Domain\Models\ExpenseClaim;
use App\Modules\Expense\Infrastructure\Services\ExpenseService;

class CreateExpenseClaim
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    public function handle(CreateExpenseDTO $dto): ExpenseClaim
    {
        return $this->expenseService->create($dto);
    }
}
