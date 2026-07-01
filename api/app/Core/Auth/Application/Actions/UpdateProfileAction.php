<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Modules\HR\Infrastructure\Services\EmployeeService;

/**
 * Use Case : Mise à jour du profil de l'employé connecté.
 */
final class UpdateProfileAction
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function execute(Employee $employee, UpdateEmployeeDTO $dto): Employee
    {
        $updated = $this->employeeService->update($employee, $employee, $dto);

        /** @var Employee $fresh */
        $fresh = $updated->fresh();

        return $fresh;
    }
}
