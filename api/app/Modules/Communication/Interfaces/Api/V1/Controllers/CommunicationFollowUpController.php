<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\CancelFollowUpAction;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * File d'attente des relances (BC-29 COMMUNICATION, R4 #7689) —
 * consultation (statuts pending/sent/skipped/failed/cancelled + code
 * machine du garde-fou) et annulation manuelle d'une echeance `pending`.
 *
 * Boite PERSONNELLE : l'index est borne aux boites de l'appelant,
 * l'annulation est reservee au proprietaire (`CommunicationFollowUpPolicy`),
 * cross-tenant = 404 (garde `assertTenantScope`).
 */
class CommunicationFollowUpController extends Controller
{
    use AssertsTenantScope;

    public function __construct(private readonly CancelFollowUpAction $cancelAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationFollowUp::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{status?: string|null} $validated */
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', CommunicationFollowUp::STATUSES)],
        ]);

        $followUps = CommunicationFollowUp::query()
            ->whereHas('integration', fn ($query) => $query->where('employee_id', $employee->id))
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('scheduled_for')
            ->paginate(min((int) $request->query('per_page', '25'), 100));

        return new JsonResponse([
            'data' => collect($followUps->items())
                ->map(fn (CommunicationFollowUp $followUp): array => $this->present($followUp))
                ->all(),
            'meta' => [
                'current_page' => $followUps->currentPage(),
                'last_page' => $followUps->lastPage(),
                'total' => $followUps->total(),
            ],
        ]);
    }

    /**
     * Annulation manuelle d'une echeance encore `pending` (auditee).
     */
    public function cancel(Request $request, CommunicationFollowUp $followUp): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $followUp);
        $this->authorize('cancel', $followUp);

        // Délégation du cas d'usage (BOS-024e, #8216) : refus 409 hors
        // `pending`, annulation auditée — contrat inchangé.
        $followUp = $this->cancelAction->execute($followUp);

        return new JsonResponse(['data' => $this->present($followUp)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationFollowUp $followUp): array
    {
        return [
            'id' => $followUp->id,
            'rule_id' => $followUp->rule_id,
            'integration_id' => $followUp->integration_id,
            'thread_id' => $followUp->thread_id,
            'step_position' => $followUp->step_position,
            'contact_email' => $followUp->contact_email,
            'scheduled_for' => $followUp->scheduled_for->toIso8601String(),
            'status' => $followUp->status,
            'skip_reason' => $followUp->skip_reason,
            'sent_at' => $followUp->sent_at?->toIso8601String(),
        ];
    }
}
