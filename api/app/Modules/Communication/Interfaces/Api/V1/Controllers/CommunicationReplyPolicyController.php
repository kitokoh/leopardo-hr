<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\UpsertReplyPolicyAction;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Politiques de reponse assistee par boite × categorie (BC-29
 * COMMUNICATION, R5 #7690 — spec §3.5) : off / draft / confirm / auto.
 *
 * Boite PERSONNELLE : l'index est borne aux boites de l'appelant, l'upsert
 * est reserve au proprietaire (boite d'un autre employe = 404, existence
 * non revelee). Garde-fous a l'ecriture :
 * - categorie inconnue de la taxonomie du tenant -> 422 REPLY_CATEGORY_UNKNOWN ;
 * - `auto` sur une categorie finance/RH/juridique (liste bloquee EN DUR)
 *   -> 422 REPLY_AUTO_CATEGORY_BLOCKED — jamais contournable ;
 * - `confirm`/`auto` sans scope gmail.send -> 422 GMAIL_SEND_SCOPE_REQUIRED ;
 * - `draft` sans scope gmail.compose -> 422 GMAIL_COMPOSE_SCOPE_REQUIRED
 *   (reconnexion via POST /integrations/google `with_send=true`).
 */
class CommunicationReplyPolicyController extends Controller
{
    public function __construct(private readonly UpsertReplyPolicyAction $upsertAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationReplyPolicy::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $policies = CommunicationReplyPolicy::query()
            ->whereHas('integration', fn ($query) => $query->where('employee_id', $employee->id))
            ->orderBy('category_key')
            ->get();

        return new JsonResponse([
            'data' => $policies
                ->map(fn (CommunicationReplyPolicy $policy): array => $this->present($policy))
                ->all(),
        ]);
    }

    /**
     * Upsert de LA politique d'une boite pour une categorie (une seule
     * ligne par couple, `off` explicite conserve — auditable).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationReplyPolicy::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{integration_id: string, category_key: string, policy: string} $validated */
        $validated = $request->validate([
            'integration_id' => ['required', 'uuid'],
            'category_key' => ['required', 'string', 'max:64'],
            'policy' => ['required', 'string', 'in:'.implode(',', CommunicationReplyPolicy::POLICIES)],
        ]);

        // Délégation du cas d'usage (BOS-024e, #8216) : boîte de l'appelant
        // (404), taxonomie (422), liste bloquée EN DUR (422), scopes
        // gmail.send/compose (422), upsert — contrat inchangé.
        ['policy' => $policy, 'created' => $created] = $this->upsertAction->execute($employee, $validated);

        return new JsonResponse(['data' => $this->present($policy)], $created ? 201 : 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationReplyPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'integration_id' => $policy->integration_id,
            'category_key' => $policy->category_key,
            'policy' => $policy->policy,
            'auto_blocked' => CommunicationReplyPolicy::isAutoBlockedCategory($policy->category_key),
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
