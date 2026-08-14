<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Payroll\Domain\Models\SocialContribution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADMIN-PAIE (#1813) — un platform_admin a approuvé une cotisation sociale.
 */
class SocialContributionApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SocialContribution $socialContribution,
        public readonly int $submittedBy,
        public readonly int $validatedBy,
    ) {}
}
