<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;

class ExportPolicy
{
    public function export(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewHistory(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function download(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
