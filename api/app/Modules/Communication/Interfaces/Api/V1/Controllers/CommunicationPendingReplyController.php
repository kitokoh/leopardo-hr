<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\ApprovePendingReplyAction;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyLog;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * File Pending des reponses assistees (BC-29 COMMUNICATION, R5 #7690 —
 * spec §3.5, mode `confirm`) : consultation, EDITION du brouillon,
 * APPROBATION (= envoi reel depuis le Gmail du proprietaire) et REJET.
 *
 * Invariant central (exigence issue) : en mode confirm, AUCUN envoi sans
 * action humaine explicite — le seul chemin d'envoi de cette file est
 * POST /pending-replies/{id}/approve, reserve au PROPRIETAIRE de la boite
 * (`CommunicationPendingReplyPolicy::decide`), cross-tenant = 404.
 *
 * L'approbation re-evalue les garde-fous TERMINAUX (opt-out, consentement
 * CRM, scope, boite active) juste avant l'envoi : une validation humaine
 * n'outrepasse jamais un refus de consentement.
 */
class CommunicationPendingReplyController extends Controller
{
    use AssertsTenantScope;

    public function __construct(
        private readonly ApprovePendingReplyAction $approveAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationPendingReply::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{status?: string|null} $validated */
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', CommunicationPendingReply::STATUSES)],
        ]);

        $replies = CommunicationPendingReply::query()
            ->whereHas('integration', fn ($query) => $query->where('employee_id', $employee->id))
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', '25'), 100));

        return new JsonResponse([
            'data' => collect($replies->items())
                ->map(fn (CommunicationPendingReply $reply): array => $this->present($reply))
                ->all(),
            'meta' => [
                'current_page' => $replies->currentPage(),
                'last_page' => $replies->lastPage(),
                'total' => $replies->total(),
            ],
        ]);
    }

    /**
     * Edition du brouillon (sujet/corps) AVANT approbation — le texte
     * envoye est toujours celui que l'humain a valide.
     */
    public function update(Request $request, CommunicationPendingReply $pendingReply): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $pendingReply);
        $this->authorize('update', $pendingReply);

        if (! $pendingReply->isPending()) {
            return $this->notPending();
        }

        /** @var array{subject?: string, body?: string} $validated */
        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'min:1', 'max:255'],
            'body' => ['sometimes', 'string', 'min:1', 'max:10000'],
        ]);

        if ($validated === []) {
            return new JsonResponse(['data' => $this->present($pendingReply)]);
        }

        /** @var Employee $employee */
        $employee = $request->user();

        $pendingReply->forceFill(array_merge($validated, ['edited_at' => now()]))->save();

        CommunicationReplyLog::record(
            $pendingReply,
            CommunicationReplyLog::ACTION_EDITED,
            'edited_by_owner',
            (int) $employee->id,
        );

        return new JsonResponse(['data' => $this->present($pendingReply)]);
    }

    /**
     * APPROBATION humaine = envoi immediat depuis le Gmail du proprietaire
     * (threading RFC 5322, tokens R1) — l'unique chemin d'envoi du mode
     * confirm. Les garde-fous terminaux sont re-evalues (fail-closed).
     */
    public function approve(Request $request, CommunicationPendingReply $pendingReply): JsonResponse
    {
        $this->assertTenantScope($request, $pendingReply);
        $this->authorize('decide', $pendingReply);

        /** @var Employee $employee */
        $employee = $request->user();

        // Délégation du cas d'usage (BOS-024e, #8216) : ré-évaluation des
        // garde-fous terminaux, envoi Gmail et journalisation — réponses
        // d'erreur 409/422/429/502 strictement inchangées.
        $pendingReply = $this->approveAction->execute($pendingReply, $employee);

        return new JsonResponse(['data' => $this->present($pendingReply)]);
    }

    /**
     * REJET humain : la proposition est fermee, rien ne part (audite).
     */
    public function reject(Request $request, CommunicationPendingReply $pendingReply): JsonResponse
    {
        $this->assertTenantScope($request, $pendingReply);
        $this->authorize('decide', $pendingReply);

        if (! $pendingReply->isPending()) {
            return $this->notPending();
        }

        /** @var Employee $employee */
        $employee = $request->user();

        $pendingReply->forceFill([
            'status' => CommunicationPendingReply::STATUS_REJECTED,
            'skip_reason' => 'rejected_by_owner',
            'decided_by' => (int) $employee->id,
            'decided_at' => now(),
        ])->save();

        CommunicationReplyLog::record(
            $pendingReply,
            CommunicationReplyLog::ACTION_REJECTED,
            'rejected_by_owner',
            (int) $employee->id,
        );

        return new JsonResponse(['data' => $this->present($pendingReply)]);
    }

    private function notPending(): JsonResponse
    {
        return new JsonResponse([
            'message' => __('communication.pending_reply_not_pending'),
            'code' => 'PENDING_REPLY_NOT_PENDING',
        ], 409);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationPendingReply $reply): array
    {
        return [
            'id' => $reply->id,
            'integration_id' => $reply->integration_id,
            'thread_id' => $reply->thread_id,
            'message_id' => $reply->message_id,
            'category_key' => $reply->category_key,
            'mode' => $reply->mode,
            'to_email' => $reply->to_email,
            'subject' => $reply->subject,
            'body' => $reply->body,
            'ai_language' => $reply->ai_language,
            'ai_confidence' => $reply->ai_confidence,
            'status' => $reply->status,
            'skip_reason' => $reply->skip_reason,
            'edited_at' => $reply->edited_at?->toIso8601String(),
            'decided_at' => $reply->decided_at?->toIso8601String(),
            'sent_at' => $reply->sent_at?->toIso8601String(),
            'created_at' => $reply->created_at?->toIso8601String(),
        ];
    }
}
