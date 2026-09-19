<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationContactProposal;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Shared\Contracts\Crm\EmailContactDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Propositions de creation de contact CRM (BC-29 COMMUNICATION, R3 #7688,
 * spec §3.3) — un expediteur inconnu du CRM n'est JAMAIS cree
 * silencieusement : la classification propose, le PROPRIETAIRE de la boite
 * decide (`CommunicationContactProposalPolicy`).
 *
 * Acceptation : creation du `crm_contacts` via le contrat partage
 * `EmailContactDirectory` (BC-11, isolation #5584), liaison retroactive des
 * messages de cet expediteur et activite `email` dans la timeline CRM.
 */
class CommunicationContactProposalController extends Controller
{
    public function __construct(private readonly EmailContactDirectory $contacts) {}

    /**
     * Propositions des boites de l'appelant, en attente d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationContactProposal::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $proposals = CommunicationContactProposal::query()
            ->whereHas('integration', function ($query) use ($employee): void {
                $query->where('employee_id', $employee->id);
            })
            ->when(
                is_string($request->query('status')),
                fn ($query) => $query->where('status', (string) $request->query('status'))
            )
            ->orderByRaw("CASE WHEN status = 'proposed' THEN 0 ELSE 1 END")
            ->orderByDesc('message_count')
            ->paginate(min((int) $request->query('per_page', '50'), 100));

        return new JsonResponse([
            'data' => collect($proposals->items())
                ->map(fn (CommunicationContactProposal $proposal): array => $this->present($proposal))
                ->all(),
            'meta' => [
                'current_page' => $proposals->currentPage(),
                'last_page' => $proposals->lastPage(),
                'per_page' => $proposals->perPage(),
                'total' => $proposals->total(),
            ],
        ]);
    }

    /**
     * Acceptation EXPLICITE : cree le contact CRM (contrat partage), lie
     * retroactivement les messages de l'expediteur, journalise l'activite.
     */
    public function accept(Request $request, CommunicationContactProposal $proposal): JsonResponse
    {
        $this->authorize('decide', $proposal);

        if (! $proposal->isPending()) {
            return new JsonResponse([
                'message' => __('communication.proposal_already_decided'),
                'code' => 'PROPOSAL_ALREADY_DECIDED',
            ], 409);
        }

        /** @var array{suggested_name?: string|null} $validated */
        $validated = $request->validate([
            'suggested_name' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        $contactId = $this->contacts->createContact(
            (string) $proposal->company_id,
            $proposal->email,
            $validated['suggested_name'] ?? $proposal->suggested_name,
        );

        $proposal->forceFill([
            'status' => CommunicationContactProposal::STATUS_ACCEPTED,
            'crm_contact_id' => $contactId,
            'decided_at' => now(),
            'decided_by' => (int) $employee->id,
        ])->save();

        $this->linkMessages($proposal, $contactId);

        return new JsonResponse(['data' => $this->present($proposal->refresh())]);
    }

    public function dismiss(Request $request, CommunicationContactProposal $proposal): JsonResponse
    {
        $this->authorize('decide', $proposal);

        if (! $proposal->isPending()) {
            return new JsonResponse([
                'message' => __('communication.proposal_already_decided'),
                'code' => 'PROPOSAL_ALREADY_DECIDED',
            ], 409);
        }

        /** @var Employee $employee */
        $employee = $request->user();

        $proposal->forceFill([
            'status' => CommunicationContactProposal::STATUS_DISMISSED,
            'decided_at' => now(),
            'decided_by' => (int) $employee->id,
        ])->save();

        return new JsonResponse(['data' => $this->present($proposal)]);
    }

    /**
     * Liaison retroactive des messages de l'expediteur + UNE activite
     * timeline sur le message le plus recent (pas une par message).
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

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationContactProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'integration_id' => $proposal->integration_id,
            'email' => $proposal->email,
            'suggested_name' => $proposal->suggested_name,
            'status' => $proposal->status,
            'message_count' => $proposal->message_count,
            'crm_contact_id' => $proposal->crm_contact_id,
            'decided_at' => $proposal->decided_at?->toIso8601String(),
        ];
    }
}
