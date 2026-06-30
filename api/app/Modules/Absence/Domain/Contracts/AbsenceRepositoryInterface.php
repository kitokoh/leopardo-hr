<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Contracts;

use App\Modules\Absence\Domain\Models\Absence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AbsenceRepositoryInterface
{
    public function findById(int $id): ?Absence;

    /** @return LengthAwarePaginator<int, Absence> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(Absence $absence): Absence;

    public function delete(Absence $absence): void;

    public function hasConflict(int $employeeId, string $startDate, string $endDate, ?int $excludeId = null): bool;
}
