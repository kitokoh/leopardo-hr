<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Actions;

use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\EmployeeService;

class UpdateEmployee
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function execute(Employee $actor, Employee $employee, UpdateEmployeeDTO $dto): Employee
    {
        return $this->employeeService->update($actor, $employee, $dto);
    }
}
