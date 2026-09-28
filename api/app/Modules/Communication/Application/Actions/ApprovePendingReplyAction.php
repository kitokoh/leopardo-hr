<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyLog;
use App\Modules\Communication\Infrastructure\Services\CommunicationReplyGuard;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailReplySender;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Cas d'usage « approuver une réponse assistée » (BC-29 COMMUNICATION,
 * R5 #7690) — l'unique chemin d'envoi du mode `confirm`.
 *
 * Extrait de `CommunicationPendingReplyController::approve` (BOS-024e,
 * #8216) : l'approbation humaine déclenche l'envoi réel depuis le Gmail du
 * propriétaire, après ré-évaluation des garde-fous TERMINAUX (opt-out,
 * consentement CRM, scope, boîte active) — une validation humaine
 * n'outrepasse jamais un refus de consentement. La Policy `decide` et la
 * garde tenant restent au niveau interface ; les réponses d'erreur sont
 * reproduites à l'identique (409/422/429/502 + codes stables).
 */
final class ApprovePendingReplyAction
{
    public function __construct(
        private readonly CommunicationReplyGuard $guard,
        private readonly GoogleGmailReplySender $sender,
    ) {}

    /**
     * @throws HttpResponseException 409 PENDING_REPLY_NOT_PENDING · 422
     *                               GMAIL_SEND_SCOPE_REQUIRED / REPLY_BLOCKED / GMAIL_AUTH_FAILED ·
     *                               429 GMAIL_RATE_LIMITED · 502 REPLY_SEND_FAILED
     */
    public function execute(CommunicationPendingReply $pendingReply, Employee $employee): CommunicationPendingReply
    {
        if (! $pendingReply->isPending()) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.pending_reply_not_pending'),
                'code' => 'PENDING_REPLY_NOT_PENDING',
            ], 409));
        }

        ['verdict' => $verdict, 'reason' => $reason] = $this->guard->evaluate($pendingReply, manual: true);

        if ($verdict !== CommunicationReplyGuard::VERDICT_SEND) {
            if ($reason === 'missing_send_scope') {
                throw new HttpResponseException(new JsonResponse([
                    'message' => __('communication.reply_send_scope_required'),
                    'code' => 'GMAIL_SEND_SCOPE_REQUIRED',
                ], 422));
            }

            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_blocked'),
                'code' => 'REPLY_BLOCKED',
                'reason' => $reason,
            ], 422));
        }

        try {
            $sentId = $this->sender->send($pendingReply);
        } catch (GmailRateLimitedException) {
            // Quota Gmail : la proposition reste pending, ré-essayable.
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_rate_limited'),
                'code' => 'GMAIL_RATE_LIMITED',
            ], 429));
        } catch (GmailSyncAuthException) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_auth_failed'),
                'code' => 'GMAIL_AUTH_FAILED',
            ], 422));
        }

        if ($sentId === null) {
            $pendingReply->forceFill([
                'status' => CommunicationPendingReply::STATUS_FAILED,
                'skip_reason' => 'gmail_send_failed',
                'decided_by' => (int) $employee->id,
                'decided_at' => now(),
            ])->save();
            CommunicationReplyLog::record(
                $pendingReply,
                CommunicationReplyLog::ACTION_FAILED,
                'gmail_send_failed',
                (int) $employee->id,
            );

            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_send_failed'),
                'code' => 'REPLY_SEND_FAILED',
            ], 502));
        }

        $pendingReply->forceFill([
            'status' => CommunicationPendingReply::STATUS_SENT,
            'sent_gmail_message_id' => $sentId,
            'sent_at' => now(),
            'decided_by' => (int) $employee->id,
            'decided_at' => now(),
        ])->save();

        CommunicationReplyLog::record(
            $pendingReply,
            CommunicationReplyLog::ACTION_APPROVED,
            'approved_by_owner',
            (int) $employee->id,
        );
        CommunicationReplyLog::record($pendingReply, CommunicationReplyLog::ACTION_SENT, null, (int) $employee->id);

        return $pendingReply;
    }
}
