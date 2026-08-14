<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADMIN-PAIE (#1813) — un RH/comptable a soumis une modification de
 * cotisation sociale pour validation.
 */
class SocialContributionSubmittedForValidation
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SocialContribution $socialContribution,
        public readonly int $submittedBy,
    ) {}
}
