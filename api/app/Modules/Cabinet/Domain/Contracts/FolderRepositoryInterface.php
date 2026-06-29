<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Domain\Contracts;

use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use Illuminate\Support\Collection;

interface FolderRepositoryInterface
{
    public function findById(int $id): ?CabinetFolder;

    /** @return Collection<int, CabinetFolder> */
    public function findByCompany(int $companyId): Collection;

    public function save(CabinetFolder $folder): CabinetFolder;

    public function delete(CabinetFolder $folder): void;
}
