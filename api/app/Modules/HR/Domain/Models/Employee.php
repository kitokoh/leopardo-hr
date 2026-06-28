<?php

namespace App\Modules\HR\Domain\Models;

/**
 * HR domain model for employees.
 * Delegates to the canonical App\Core\Auth\Domain\Models\Employee.
 * The HR module adds HR-specific business logic here only.
 *
 * @see \App\Core\Auth\Domain\Models\Employee
 */
class Employee extends \App\Core\Auth\Domain\Models\Employee
{
    // HR-specific domain logic only.
    // Core attributes and relations live in App\Core\Auth\Domain\Models\Employee.
}
