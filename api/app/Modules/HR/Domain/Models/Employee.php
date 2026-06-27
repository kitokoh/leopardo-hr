<?php

namespace App\Modules\HR\Domain\Models;

/**
 * HR domain model for employees.
 * Delegates to the canonical App\Models\Employee.
 * The HR module adds HR-specific business logic here only.
 *
 * @see \App\Models\Employee
 */
class Employee extends \App\Models\Employee
{
    // HR-specific domain logic only.
    // Core attributes and relations live in App\Models\Employee.
}
