<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Contracts;

use App\Modules\Fleet\Domain\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VehicleRepositoryInterface
{
    public function findById(int $id): ?Vehicle;

    /** @return LengthAwarePaginator<int, Vehicle> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(Vehicle $vehicle): Vehicle;

    public function delete(Vehicle $vehicle): void;

    public function findByCompanyAndStatus(int $companyId, string $status): \Illuminate\Support\Collection;
}
