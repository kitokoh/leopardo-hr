<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Planning\Domain\Models\Absence;
use App\Core\Auth\Domain\Models\Employee;
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

