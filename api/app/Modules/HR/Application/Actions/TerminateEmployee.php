<?php

namespace App\Modules\HR\Application\Actions;

use App\Modules\HR\Domain\Exceptions\EmployeeNotFoundException;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\EmployeeService;

class TerminateEmployee
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    /**
     * Archive (terminate) an employee.
     *
     * @throws EmployeeNotFoundException
     */
    public function execute(string $employeeId): Employee
    {
        $employee = Employee::query()->find($employeeId);

        if (! $employee instanceof Employee) {
            throw new EmployeeNotFoundException($employeeId);
        }

        return $this->employeeService->archive($employee);
    }
}
