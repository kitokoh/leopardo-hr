<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Domain\Contracts;

use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentRepositoryInterface
{
    public function findById(int $id): ?CabinetDocument;

    /** @return LengthAwarePaginator<int, CabinetDocument> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(CabinetDocument $document): CabinetDocument;

    public function delete(CabinetDocument $document): void;
}
