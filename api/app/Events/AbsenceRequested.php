<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbsenceRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Absence $absence) {}
}

