<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\PayoutStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;

/**
 * Workflow de traitement d'un reversement (verticale FUNDRAISING —
 * spec §3.3) : `requested → processing → paid | failed`, et
 * `requested → cancelled`. Toute autre transition ⇒
 * INVALID_STATUS_TRANSITION. v1 : traitement opéré par le responsable
 * (mobile money / virement exécuté hors plateforme) ; l'automatisation
 * provider arrive en v1.3 (spec §9).
 */
final class TransitionPayoutAction
{
    public function process(FundraisingPayout $payout, ?string $processedBy = null): FundraisingPayout
    {
        if ($payout->status !== PayoutStatus::REQUESTED) {
            throw FundraisingException::invalidStatusTransition($payout->status->value, PayoutStatus::PROCESSING->value);
        }

        $payout->status = PayoutStatus::PROCESSING;
        $payout->processed_by = $processedBy;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    public function markPaid(FundraisingPayout $payout, ?string $providerReference = null, ?string $processedBy = null): FundraisingPayout
    {
        if ($payout->status !== PayoutStatus::PROCESSING) {
            throw FundraisingException::invalidStatusTransition($payout->status->value, PayoutStatus::PAID->value);
        }

        $payout->status = PayoutStatus::PAID;
        $payout->provider_reference = $providerReference ?? $payout->provider_reference;
        $payout->processed_by = $processedBy ?? $payout->processed_by;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    public function fail(FundraisingPayout $payout, string $reason, ?string $processedBy = null): FundraisingPayout
    {
        if ($payout->status !== PayoutStatus::PROCESSING) {
            throw FundraisingException::invalidStatusTransition($payout->status->value, PayoutStatus::FAILED->value);
        }

        $payout->status = PayoutStatus::FAILED;
        $payout->failure_reason = mb_substr($reason, 0, 500);
        $payout->processed_by = $processedBy ?? $payout->processed_by;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    public function cancel(FundraisingPayout $payout): FundraisingPayout
    {
        if ($payout->status !== PayoutStatus::REQUESTED) {
            throw FundraisingException::invalidStatusTransition($payout->status->value, PayoutStatus::CANCELLED->value);
        }

        $payout->status = PayoutStatus::CANCELLED;
        $payout->save();

        return $payout;
    }
}
