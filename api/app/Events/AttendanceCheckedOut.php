<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceCheckedOut
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AttendanceLog $log) {}
}
