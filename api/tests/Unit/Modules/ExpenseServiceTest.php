<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\Expense\Application\DTOs\CreateExpenseDTO;
use App\Modules\Expense\Domain\Exceptions\ExpenseNotDraftException;
use App\Modules\Expense\Domain\Models\ExpenseClaim;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    public function test_create_expense_dto_fromArray(): void
    {
        $dto = CreateExpenseDTO::fromArray([
            'employee_id' => 5,
            'title'       => 'Mission Lyon',
            'description' => 'Déplacement professionnel',
            'currency'    => 'EUR',
            'items'       => [
                ['category' => 'transport', 'amount' => 120.0, 'expense_date' => '2026-07-10'],
                ['category' => 'hotel',     'amount' => 200.0, 'expense_date' => '2026-07-10'],
            ],
        ]);

        $this->assertSame(5, $dto->employeeId);
        $this->assertSame('Mission Lyon', $dto->title);
        $this->assertSame('EUR', $dto->currency);
        $this->assertCount(2, $dto->items);
    }

    public function test_create_expense_dto_default_currency(): void
    {
        $dto = CreateExpenseDTO::fromArray([
            'employee_id' => 1,
            'title'       => 'Test',
        ]);

        $this->assertSame('EUR', $dto->currency);
        $this->assertSame([], $dto->items);
    }

    public function test_expense_not_draft_exception(): void
    {
        $this->expectException(ExpenseNotDraftException::class);
        throw new ExpenseNotDraftException();
    }

    public function test_expense_not_draft_exception_status_code(): void
    {
        $e = new ExpenseNotDraftException();
        $this->assertSame(422, $e->getCode());
    }
}
