<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface JobPostingRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return LengthAwarePaginator<int, object> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(object $posting): object;

    public function delete(int $id): void;

    public function findByStatus(int $companyId, string $status): \Illuminate\Support\Collection;
}
