<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payroll;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollValidated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payroll $payroll) {}
}
