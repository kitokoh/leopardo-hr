<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un lead marketing vient d'être qualifié par un tenant (marketing/principal).
 *
 * Émis par `MarketingLeadConversionController::qualify()` après la
 * transition `status → qualified`. Le listener
 * `ConvertMarketingLeadToContact` crée l'`AccountingContact` associé
 * (source=marketing_lead) — issue #5231.
 */
class MarketingLeadQualified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly MarketingLead $lead,
        public readonly string $companyId,
    ) {}
}
