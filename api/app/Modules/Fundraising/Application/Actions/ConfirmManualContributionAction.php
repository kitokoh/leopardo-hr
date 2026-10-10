<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Domain\Models\FundraisingPaymentEvent;
use App\Modules\Fundraising\Infrastructure\Services\ContributionSettlement;

/**
 * Confirmation d'une contribution MANUELLE (espèces / virement constaté)
 * par le responsable du tenant — verticale FUNDRAISING, spec §5.2.
 *
 * Réservée aux contributions `provider = manual` encore `pending` ;
 * comptabilise via `ContributionSettlement` (même invariant transactionnel
 * que les webhooks) et journalise un événement d'audit
 * `manual-confirm-{reference}`.
 */
final class ConfirmManualContributionAction
{
    public function __construct(
        private readonly ContributionSettlement $settlement,
    ) {}

    public function execute(FundraisingContribution $contribution): FundraisingContribution
    {
        if ($contribution->provider !== 'manual') {
            throw FundraisingException::invalidStatusTransition($contribution->provider, 'manual_confirm');
        }

        if ($contribution->status !== ContributionStatus::PENDING) {
            throw FundraisingException::invalidStatusTransition($contribution->status->value, 'completed');
        }

        $this->settlement->settle($contribution);

        FundraisingPaymentEvent::query()->firstOrCreate(
            ['provider' => 'manual', 'event_id' => 'manual-confirm-'.$contribution->reference],
            [
                'company_id' => $contribution->company_id,
                'contribution_id' => $contribution->id,
                'payload' => null,
                'processed_at' => now(),
            ],
        );

        return $contribution->refresh();
    }
}
