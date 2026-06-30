<?php

declare(strict_types=1);

namespace App\Modules\Expense\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return LengthAwarePaginator<int, object> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(object $expense): object;

    public function delete(int $id): void;
}
