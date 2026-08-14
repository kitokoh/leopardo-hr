<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADMIN-PAIE (#1813) — un RH/comptable a soumis une modification de barème
 * fiscal pour validation. Le listener notifie les platform_admins.
 */
class TaxSlabSubmittedForValidation
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TaxSlab $taxSlab,
        public readonly int $submittedBy,
    ) {}
}
