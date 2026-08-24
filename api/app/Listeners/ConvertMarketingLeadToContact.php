<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MarketingLeadQualified;
use App\Modules\Accounting\Application\Actions\ConvertQualifiedLeadToContact;

/**
 * Réagit à la qualification d'un lead marketing en créant le contact de
 * facturation associé (issue #5231).
 *
 * Listener synchrone : il s'exécute dans la transaction HTTP du endpoint
 * `qualify` — le contact est créé de façon atomique avec la transition
 * `status → qualified`, et le contexte tenant (current_company) est
 * préservé pour le scope `BelongsToCompany`.
 */
class ConvertMarketingLeadToContact
{
    public function __construct(
        private readonly ConvertQualifiedLeadToContact $convertAction,
    ) {}

    public function handleMarketingLeadQualified(MarketingLeadQualified $event): void
    {
        $this->convertAction->execute($event->lead, $event->companyId);
    }
}
