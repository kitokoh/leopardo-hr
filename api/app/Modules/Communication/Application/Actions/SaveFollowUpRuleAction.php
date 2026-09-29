<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpStep;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Cas d'usage « enregistrer une règle de relance » (création ET mise à
 * jour — BC-29 COMMUNICATION, R4 #7689) : règle PERSONNELLE rattachée à
 * une boîte de l'appelant, séquence de ≤ 3 étapes réécrite
 * transactionnellement.
 *
 * Extrait de `CommunicationFollowUpRuleController::{store,update}`
 * (BOS-024e, #8216). Règles préservées à l'identique : boîte inconnue ou
 * d'un autre employé → 404 `MAILBOX_NOT_FOUND` (l'existence des boîtes
 * des autres n'est jamais révélée) ; activation sans `gmail.send` → 422
 * `GMAIL_SEND_SCOPE_REQUIRED` (« scope demandé à l'activation ») ;
 * `integration_id` interdit en mise à jour (rejeté `prohibited` côté
 * interface). Les Policies (`create`/`update`) et la validation du
 * payload restent au niveau interface ; la transaction est injectée via
 * `ConnectionInterface` (garde #6568, pattern Payroll).
 */
final class SaveFollowUpRuleAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (store :
     *                                           `integration_id`, `name`, `active?`, `steps` — update :
     *                                           `name?`, `active?`, `steps?`).
     * @param  CommunicationFollowUpRule|null  $rule  null = création.
     *
     * @throws HttpResponseException 404 MAILBOX_NOT_FOUND (création) · 422
     *                               GMAIL_SEND_SCOPE_REQUIRED
     */
    public function execute(Employee $employee, array $validated, ?CommunicationFollowUpRule $rule = null): CommunicationFollowUpRule
    {
        if ($rule === null) {
            return $this->createRule($employee, $validated);
        }

        return $this->updateRule($rule, $validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createRule(Employee $employee, array $validated): CommunicationFollowUpRule
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = CommunicationIntegration::query()
            ->where('employee_id', $employee->id)
            ->find($validated['integration_id']);

        if ($integration === null) {
            // Boîte inconnue OU appartenant à quelqu'un d'autre : 404 (ne
            // pas révéler l'existence des boîtes des autres).
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.follow_up_mailbox_not_found'),
                'code' => 'MAILBOX_NOT_FOUND',
            ], 404));
        }

        $active = (bool) ($validated['active'] ?? true);

        if ($active && ! $integration->hasSendScope()) {
            throw self::sendScopeRequired();
        }

        /** @var list<array{delay_days: int, template_key?: string|null}> $steps */
        $steps = $validated['steps'];

        /** @var CommunicationFollowUpRule $rule */
        $rule = $this->db->transaction(function () use ($employee, $integration, $validated, $active, $steps): CommunicationFollowUpRule {
            $rule = new CommunicationFollowUpRule;
            $rule->forceFill([
                'company_id' => (string) $employee->company_id,
                'integration_id' => $integration->id,
                'name' => $validated['name'],
                'active' => $active,
            ]);
            $rule->save();

            $this->syncSteps($rule, $steps);

            return $rule;
        });

        return $rule->load(['steps', 'integration']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateRule(CommunicationFollowUpRule $rule, array $validated): CommunicationFollowUpRule
    {
        $active = array_key_exists('active', $validated) ? (bool) $validated['active'] : $rule->active;

        if ($active && ! $rule->integration?->hasSendScope()) {
            throw self::sendScopeRequired();
        }

        $this->db->transaction(function () use ($rule, $validated, $active): void {
            if (($validated['name'] ?? null) !== null) {
                $rule->forceFill(['name' => $validated['name']]);
            }
            $rule->forceFill(['active' => $active]);
            $rule->save();

            if (array_key_exists('steps', $validated)) {
                CommunicationFollowUpStep::query()
                    ->where('rule_id', $rule->id)
                    ->delete();

                /** @var list<array{delay_days: int, template_key?: string|null}> $steps */
                $steps = $validated['steps'];
                $this->syncSteps($rule, $steps);
            }
        });

        return $rule->refresh()->load(['steps', 'integration']);
    }

    /**
     * @param  list<array{delay_days: int, template_key?: string|null}>  $steps
     */
    private function syncSteps(CommunicationFollowUpRule $rule, array $steps): void
    {
        foreach ($steps as $index => $payload) {
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

    private static function sendScopeRequired(): HttpResponseException
    {
        return new HttpResponseException(new JsonResponse([
            'message' => __('communication.follow_up_send_scope_required'),
            'code' => 'GMAIL_SEND_SCOPE_REQUIRED',
        ], 422));
    }
}
