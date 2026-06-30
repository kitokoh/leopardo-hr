<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PlanningRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return Collection<int, object> */
    public function findByCompanyAndDateRange(int $companyId, string $from, string $to): Collection;

    /** @return LengthAwarePaginator<int, object> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(object $plan): object;

    public function delete(int $id): void;
}
