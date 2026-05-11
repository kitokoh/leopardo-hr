<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbsenceApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Absence $absence,
        public readonly Employee $approver,
    ) {}
}
