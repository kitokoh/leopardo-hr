<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADMIN-PAIE (#1813) — un platform_admin a approuvé un barème fiscal.
 * Le listener notifie le soumissionnaire.
 */
class TaxSlabApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly TaxSlab $taxSlab,
        public readonly int $submittedBy,
        public readonly int $validatedBy,
    ) {}
}
