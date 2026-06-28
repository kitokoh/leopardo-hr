<?php

namespace App\Core\Auth\Domain\Models;

/**
 * Canonical auth model for employees.
 * The single source of truth lives in App\Models\Employee.
 * This class is a domain alias for use within Core\Auth — no duplication.
 *
 * @see \App\Models\Employee
 */
class Employee extends \App\Models\Employee
{
    // All logic lives in App\Models\Employee.
    // Do not add new fields or relations here.
}
