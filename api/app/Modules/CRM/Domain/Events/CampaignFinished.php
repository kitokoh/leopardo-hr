<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fin (finish/cancel) d'une campagne CRM — Issue #5724.
 */
class CampaignFinished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $campaignId,
        public readonly string $status,
    ) {}
}
