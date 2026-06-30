<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Contracts;

use Illuminate\Support\Collection;

interface CameraRepositoryInterface
{
    public function findById(int $id): ?object;

    /** @return Collection<int, object> */
    public function findByCompany(int $companyId): Collection;

    public function save(object $camera): object;

    public function delete(int $id): void;
}
