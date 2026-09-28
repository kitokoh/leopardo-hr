<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpLog;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Cas d'usage « annuler une relance » (BC-29 COMMUNICATION, R4 #7689) :
 * annulation manuelle, auditée, d'une échéance encore `pending`.
 *
 * Extrait de `CommunicationFollowUpController::cancel` (BOS-024e, #8216).
 * La Policy `cancel` (propriétaire de la boîte) et la garde tenant
 * restent au niveau interface ; une échéance non `pending` est refusée
 * 409 `FOLLOW_UP_NOT_CANCELLABLE` (contrat inchangé).
 */
final class CancelFollowUpAction
{
    /**
     * @throws HttpResponseException 409 FOLLOW_UP_NOT_CANCELLABLE
     */
    public function execute(CommunicationFollowUp $followUp): CommunicationFollowUp
    {
        if ($followUp->status !== CommunicationFollowUp::STATUS_PENDING) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.follow_up_not_cancellable'),
                'code' => 'FOLLOW_UP_NOT_CANCELLABLE',
            ], 409));
        }

        $followUp->forceFill([
            'status' => CommunicationFollowUp::STATUS_CANCELLED,
            'skip_reason' => 'cancelled_by_owner',
        ])->save();

        CommunicationFollowUpLog::record($followUp, CommunicationFollowUpLog::ACTION_CANCELLED, 'cancelled_by_owner');

        return $followUp;
    }
}
