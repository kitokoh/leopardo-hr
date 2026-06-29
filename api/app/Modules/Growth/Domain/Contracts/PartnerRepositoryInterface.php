<?php

declare(strict_types=1);

namespace App\Modules\Growth\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PartnerRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return LengthAwarePaginator<int, object> */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function save(object $partner): object;

    public function delete(int $id): void;
}
