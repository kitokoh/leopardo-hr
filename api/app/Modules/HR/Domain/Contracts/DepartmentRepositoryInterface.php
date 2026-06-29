<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use App\Modules\HR\Domain\Models\Department;
use Illuminate\Database\Eloquent\Collection;

interface DepartmentRepositoryInterface
{
    public function findById(int $id): ?Department;

    /**
     * @return Collection<int, Department>
     */
    public function allByCompany(int $companyId): Collection;

    public function save(Department $department): Department;

    public function delete(Department $department): void;
}
