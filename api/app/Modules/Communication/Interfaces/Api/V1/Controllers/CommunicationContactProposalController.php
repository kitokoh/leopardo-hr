<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\DecideContactProposalAction;
use App\Modules\Communication\Domain\Models\CommunicationContactProposal;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
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
    use AssertsTenantScope;

    public function __construct(private readonly DecideContactProposalAction $decideAction) {}

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
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $proposal);
        $this->authorize('decide', $proposal);

        /** @var array{suggested_name?: string|null} $validated */
        $validated = $request->validate([
            'suggested_name' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        // Délégation du cas d'usage (BOS-024e, #8216) : création du contact
        // CRM via le contrat partagé, liaison rétroactive des messages,
        // activité timeline — 409 PROPOSAL_ALREADY_DECIDED inchangé.
        $proposal = $this->decideAction->execute(
            $proposal,
            $employee,
            DecideContactProposalAction::DECISION_ACCEPT,
            $validated['suggested_name'] ?? null,
        );

        return new JsonResponse(['data' => $this->present($proposal->refresh())]);
    }

    public function dismiss(Request $request, CommunicationContactProposal $proposal): JsonResponse
    {
        $this->assertTenantScope($request, $proposal);
        $this->authorize('decide', $proposal);

        /** @var Employee $employee */
        $employee = $request->user();

        // Délégation du cas d'usage (BOS-024e, #8216) : clôture auditée —
        // 409 PROPOSAL_ALREADY_DECIDED inchangé.
        $proposal = $this->decideAction->execute($proposal, $employee, DecideContactProposalAction::DECISION_DISMISS);

        return new JsonResponse(['data' => $this->present($proposal)]);
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
