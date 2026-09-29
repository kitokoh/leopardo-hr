<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Application\Actions\SaveFollowUpRuleAction;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpStep;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Regles de relance automatique (BC-29 COMMUNICATION, R4 #7689, spec §3.4)
 * — CRUD des regles + sequences (max 3 etapes, delais configurables).
 *
 * La regle est PERSONNELLE (boite du proprietaire) :
 * - index borne aux boites de l'appelant ;
 * - creation : la boite visee doit appartenir a l'appelant (404 sinon —
 *   l'existence des boites des autres n'est pas revelee) ;
 * - activation : la boite doit avoir accorde `gmail.send` (422
 *   `GMAIL_SEND_SCOPE_REQUIRED`, « scope demande a l'activation ») ;
 * - update/delete : proprietaire uniquement (policy), cross-tenant = 404
 *   (garde `assertTenantScope`, bindings resolus avant le middleware tenant).
 */
class CommunicationFollowUpRuleController extends Controller
{
    use AssertsTenantScope;

    public function __construct(private readonly SaveFollowUpRuleAction $saveAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationFollowUpRule::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $rules = CommunicationFollowUpRule::query()
            ->whereHas('integration', fn ($query) => $query->where('employee_id', $employee->id))
            ->with(['steps', 'integration'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (CommunicationFollowUpRule $rule): array => $this->present($rule));

        return new JsonResponse(['data' => $rules->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CommunicationFollowUpRule::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{integration_id: string, name: string, active?: bool, steps: list<array{delay_days: int, template_key?: string|null}>} $validated */
        $validated = $request->validate($this->rules());

        // Délégation du cas d'usage (BOS-024e, #8216) : boîte de l'appelant
        // (404 MAILBOX_NOT_FOUND), scope gmail.send (422), création +
        // séquence en transaction — contrat inchangé.
        $rule = $this->saveAction->execute($employee, $validated);

        return new JsonResponse(['data' => $this->present($rule)], 201);
    }

    public function update(Request $request, CommunicationFollowUpRule $rule): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $rule);
        $this->authorize('update', $rule);

        /** @var array{name?: string, active?: bool, steps?: list<array{delay_days: int, template_key?: string|null}>} $validated */
        $validated = $request->validate($this->rules(partial: true));

        /** @var Employee $employee */
        $employee = $request->user();

        // Délégation du cas d'usage (BOS-024e, #8216) : scope gmail.send
        // (422), mise à jour + réécriture transactionnelle des étapes —
        // contrat inchangé.
        $rule = $this->saveAction->execute($employee, $validated, $rule);

        return new JsonResponse(['data' => $this->present($rule)]);
    }

    public function destroy(Request $request, CommunicationFollowUpRule $rule): JsonResponse
    {
        $this->assertTenantScope($request, $rule);
        $this->authorize('delete', $rule);

        // FK cascade : etapes + echeances de la regle purgees avec elle
        // (le journal d'audit, sans FK, survit).
        $rule->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'integration_id' => $partial ? ['prohibited'] : ['required', 'uuid'],
            'name' => [$required, 'string', 'max:128'],
            'active' => ['sometimes', 'boolean'],
            'steps' => [$required, 'array', 'min:1', 'max:'.CommunicationFollowUpRule::MAX_STEPS],
            'steps.*.delay_days' => ['required', 'integer', 'min:1', 'max:90'],
            'steps.*.template_key' => ['sometimes', 'nullable', 'string', 'in:communication_follow_up'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationFollowUpRule $rule): array
    {
        return [
            'id' => $rule->id,
            'integration_id' => $rule->integration_id,
            'name' => $rule->name,
            'active' => $rule->active,
            'steps' => $rule->steps->map(fn (CommunicationFollowUpStep $step): array => [
                'position' => $step->position,
                'delay_days' => $step->delay_days,
                'template_key' => $step->template_key,
            ])->all(),
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
