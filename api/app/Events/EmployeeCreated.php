<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Employee $employee) {}
}
