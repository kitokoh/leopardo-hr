<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpStep;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns\AssertsTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        /** @var CommunicationIntegration|null $integration */
        $integration = CommunicationIntegration::query()
            ->where('employee_id', $employee->id)
            ->find($validated['integration_id']);

        if ($integration === null) {
            // Boite inconnue OU appartenant a quelqu'un d'autre : 404 (ne
            // pas reveler l'existence des boites des autres).
            return new JsonResponse([
                'message' => __('communication.follow_up_mailbox_not_found'),
                'code' => 'MAILBOX_NOT_FOUND',
            ], 404);
        }

        $active = (bool) ($validated['active'] ?? true);

        if ($active && ! $integration->hasSendScope()) {
            return $this->sendScopeRequired();
        }

        /** @var CommunicationFollowUpRule $rule */
        $rule = DB::transaction(function () use ($employee, $integration, $validated, $active): CommunicationFollowUpRule {
            $rule = new CommunicationFollowUpRule;
            $rule->forceFill([
                'company_id' => (string) $employee->company_id,
                'integration_id' => $integration->id,
                'name' => $validated['name'],
                'active' => $active,
            ]);
            $rule->save();

            $this->syncSteps($rule, $validated['steps']);

            return $rule;
        });

        return new JsonResponse(['data' => $this->present($rule->load(['steps', 'integration']))], 201);
    }

    public function update(Request $request, CommunicationFollowUpRule $rule): JsonResponse
    {
        // Binding implicite resolu avant le middleware tenant : garde 404
        // explicite (cross-tenant n'existe pas pour l'appelant).
        $this->assertTenantScope($request, $rule);
        $this->authorize('update', $rule);

        /** @var array{name?: string, active?: bool, steps?: list<array{delay_days: int, template_key?: string|null}>} $validated */
        $validated = $request->validate($this->rules(partial: true));

        $active = array_key_exists('active', $validated) ? (bool) $validated['active'] : $rule->active;

        if ($active && ! $rule->integration?->hasSendScope()) {
            return $this->sendScopeRequired();
        }

        DB::transaction(function () use ($rule, $validated, $active): void {
            $rule->forceFill(array_filter([
                'name' => $validated['name'] ?? null,
            ], static fn (?string $value): bool => $value !== null));
            $rule->forceFill(['active' => $active]);
            $rule->save();

            if (array_key_exists('steps', $validated) && is_array($validated['steps'])) {
                CommunicationFollowUpStep::query()
                    ->where('rule_id', $rule->id)
                    ->delete();

                $this->syncSteps($rule, $validated['steps']);
            }
        });

        return new JsonResponse(['data' => $this->present($rule->refresh()->load(['steps', 'integration']))]);
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
     * @param  list<array{delay_days: int, template_key?: string|null}>  $steps
     */
    private function syncSteps(CommunicationFollowUpRule $rule, array $steps): void
    {
        foreach (array_values($steps) as $index => $payload) {
            $step = new CommunicationFollowUpStep;
            $step->forceFill([
                'company_id' => $rule->company_id,
                'rule_id' => $rule->id,
                'position' => $index + 1,
                'delay_days' => (int) $payload['delay_days'],
                'template_key' => $payload['template_key'] ?? 'communication_follow_up',
            ]);
            $step->save();
        }
    }

    private function sendScopeRequired(): JsonResponse
    {
        return new JsonResponse([
            'message' => __('communication.follow_up_send_scope_required'),
            'code' => 'GMAIL_SEND_SCOPE_REQUIRED',
        ], 422);
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
