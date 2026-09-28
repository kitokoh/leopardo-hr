<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationContactProposal;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Shared\Contracts\Crm\EmailContactDirectory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Cas d'usage « décider d'une proposition de contact CRM » (BC-29
 * COMMUNICATION, R3 #7688) — un expéditeur inconnu du CRM n'est JAMAIS
 * créé silencieusement : la classification propose, le propriétaire décide.
 *
 * Extrait de `CommunicationContactProposalController::{accept,dismiss}`
 * (BOS-024e, #8216). Acceptation : création du `crm_contacts` via le
 * contrat partagé `EmailContactDirectory` (BC-11, isolation #5584),
 * liaison rétroactive des messages de l'expéditeur et UNE activité
 * `email` en timeline CRM (sur le message le plus récent). Rejet : simple
 * clôture auditée. La Policy `decide` et la garde tenant restent au
 * niveau interface.
 */
final class DecideContactProposalAction
{
    public const DECISION_ACCEPT = 'accept';

    public const DECISION_DISMISS = 'dismiss';

    public function __construct(private readonly EmailContactDirectory $contacts) {}

    /**
     * @param  string  $decision  self::DECISION_ACCEPT | self::DECISION_DISMISS
     *
     * @throws HttpResponseException 409 PROPOSAL_ALREADY_DECIDED
     */
    public function execute(
        CommunicationContactProposal $proposal,
        Employee $employee,
        string $decision,
        ?string $suggestedName = null,
    ): CommunicationContactProposal {
        if (! $proposal->isPending()) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.proposal_already_decided'),
                'code' => 'PROPOSAL_ALREADY_DECIDED',
            ], 409));
        }

        if ($decision === self::DECISION_DISMISS) {
            $proposal->forceFill([
                'status' => CommunicationContactProposal::STATUS_DISMISSED,
                'decided_at' => now(),
                'decided_by' => (int) $employee->id,
            ])->save();

            return $proposal;
        }

        $contactId = $this->contacts->createContact(
            (string) $proposal->company_id,
            $proposal->email,
            $suggestedName ?? $proposal->suggested_name,
        );

        $proposal->forceFill([
            'status' => CommunicationContactProposal::STATUS_ACCEPTED,
            'crm_contact_id' => $contactId,
            'decided_at' => now(),
            'decided_by' => (int) $employee->id,
        ])->save();

        $this->linkMessages($proposal, $contactId);

        return $proposal;
    }

    /**
     * Liaison rétroactive des messages de l'expéditeur + UNE activité
     * timeline sur le message le plus récent (pas une par message).
     */
    private function linkMessages(CommunicationContactProposal $proposal, int $contactId): void
    {
        /** @var CommunicationMessage|null $latest */
        $latest = CommunicationMessage::query()
            ->where('integration_id', $proposal->integration_id)
            ->whereRaw('LOWER(from_email) = ?', [mb_strtolower($proposal->email)])
            ->orderByDesc('sent_at')
            ->first();

        CommunicationMessage::query()
            ->where('integration_id', $proposal->integration_id)
            ->whereRaw('LOWER(from_email) = ?', [mb_strtolower($proposal->email)])
            ->update([
                'crm_contact_id' => $contactId,
                'contact_link_status' => CommunicationMessage::CONTACT_LINK_LINKED,
            ]);

        if ($latest !== null) {
            $this->contacts->recordEmailActivity(
                (string) $proposal->company_id,
                $contactId,
                $latest->subject,
                $latest->sent_at ?? now(),
            );
        }
    }
}
