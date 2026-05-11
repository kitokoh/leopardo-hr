<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Absence;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbsenceRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Absence $absence) {}
}
