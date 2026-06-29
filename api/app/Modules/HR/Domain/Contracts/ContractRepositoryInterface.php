<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Database\Eloquent\Collection;

interface ContractRepositoryInterface
{
    public function findById(int $id): ?Contract;

    /**
     * @return Collection<int, Contract>
     */
    public function activeByEmployee(int $employeeId): Collection;

    public function save(Contract $contract): Contract;

    public function terminate(Contract $contract, string $reason): Contract;
}
