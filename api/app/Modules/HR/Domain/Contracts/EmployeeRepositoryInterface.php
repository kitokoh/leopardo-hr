<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use App\Modules\HR\Domain\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function findById(int $id): ?Employee;

    public function findByEmail(string $email): ?Employee;

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(Employee $employee): Employee;

    public function delete(Employee $employee): void;
}
