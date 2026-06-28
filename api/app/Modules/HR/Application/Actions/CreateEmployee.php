<?php

namespace App\Modules\HR\Application\Actions;

use App\Modules\HR\Application\DTOs\CreateEmployeeDTO;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\EmployeeService;

class CreateEmployee
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function execute(CreateEmployeeDTO $dto, ?Employee $actor = null): Employee
    {
        return $this->employeeService->create($dto, $actor);
    }
}
