<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Exceptions;

use App\Exceptions\DomainException;

class ExpenseNotDraftException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only draft expense claims can be submitted.', 422);
    }
}
