<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Events\TaxRateSubmittedForValidation;
use Illuminate\Support\Facades\Log;

/**
 * Issue #1813 — journalise les événements du workflow de validation des taux
 * légaux. Le canal email/push vers le platform admin nécessite un canal de
 * notification plateforme dédié (CommunicationService est scoped employé) —
 * la traçabilité applicative est garantie ici + dans tax_rate_change_log.
 */
class TaxRateValidationLogger
{
    public function handle(TaxRateSubmittedForValidation|TaxRateApproved|TaxRateRejected $event): void
    {
        if ($event instanceof TaxRateSubmittedForValidation) {
            Log::info("tax_rate.submitted: {$event->tableName}#{$event->recordId} by employee #{$event->actorId}");

            return;
        }

        if ($event instanceof TaxRateApproved) {
            Log::info("tax_rate.approved: {$event->tableName}#{$event->recordId} by platform_admin #{$event->adminId}");

            return;
        }

        Log::info("tax_rate.rejected: {$event->tableName}#{$event->recordId} by platform_admin #{$event->adminId} — {$event->reason}");
    }
}
