<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AttendanceRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return LengthAwarePaginator<int, object> */
    public function paginateByEmployee(int $employeeId, int $perPage = 15): LengthAwarePaginator;

    /** @return Collection<int, object> */
    public function getByCompanyAndDate(int $companyId, string $date): Collection;

    public function findOpenSession(int $employeeId): ?object;
}
