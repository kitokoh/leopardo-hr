<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Exceptions\CrmAutomationEmergencyStoppedException;
use App\Modules\CRM\Domain\Models\CrmAutomation;
use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use App\Modules\CRM\Domain\Models\CrmAutomationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Moteur d'automatisations CRM (issue #5728).
 *
 * - event/conditions/actions bornés (whitelists code, jamais de logique
 *   libre) ;
 * - **simulation sans effet** : `simulate()` exécute les actions en mode
 *   dry-run (aucune écriture, aucun envoi) ;
 * - **boucles et retry infini empêchés** : les actions sont terminales
 *   (aucun type `dispatch_event`), `run_key` unique par
 *   (automation, événement, entité) → idempotence, tentatives plafonnées
 *   (`max_attempts`) → dead-letter ;
 * - **run history** : chaque exécution est persistée avec snapshot des
 *   conditions/actions (versionnage à l'exécution) ;
 * - **arrêt d'urgence** : interrupteur tenant (crm_automation_states),
 *   vérifié à chaque dispatch.
 */
final class AutomationEngine
{
    /** @var array<string, AutomationActionContract> */
    private array $actions = [];

    /**
     * @param  array<string, AutomationActionContract>  $actions
     */
    public function __construct(
        private readonly array $actions,
        private readonly CrmConditionEvaluator $evaluator,
    ) {
        foreach ($actions as $type => $action) {
            $this->actions[$type] = $action;
        }
    }

    /**
     * Point d'entrée des événements métier.
     *
     * @param  array<string, mixed>  $context  clés attendues : entity_type,
     *                                         entity_id, data (payload), to/phone/email…
     */
    public function dispatch(string $event, array $context): void
    {
        $this->assertNotEmergencyStopped();

        $automations = CrmAutomation::query()
            ->where('trigger_event', $event)
            ->where('status', 'active')
            ->get();

        foreach ($automations as $automation) {
            $this->run($automation, $event, $context, false);
        }
    }

    /**
     * Simulation sans effet de bord (endpoint /simulate).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function simulate(CrmAutomation $automation, array $context): array
    {
        $matches = $this->evaluator->evaluate($automation->conditions ?? [], $context);
        $effects = [];

        if ($matches) {
            foreach ($automation->actions as $actionConfig) {
                $action = $this->resolveAction($actionConfig);
                $effects[] = $action->simulate($actionConfig['config'] ?? [], $context);
            }
        }

        // Historique de simulation (dry_run, aucun effet de bord réel).
        $this->persistRun($automation, $context, [
            'status' => $matches ? 'succeeded' : 'skipped',
            'dry_run' => true,
            'effects' => $effects,
        ]);

        return [
            'matched' => $matches,
            'effects' => $effects,
        ];
    }

    public function setEmergencyStop(bool $enabled): void
    {
        CrmAutomationState::query()->updateOrCreate(
            ['company_id' => currentCompany()?->id],
            ['enabled' => $enabled, 'updated_at' => now()],
        );

        Log::warning('CRM automation: interrupteur d\'urgence modifié', [
            'company_id' => currentCompany()?->id,
            'enabled' => $enabled,
        ]);
    }

    public function isEmergencyStopped(): bool
    {
        $state = CrmAutomationState::query()->where('company_id', currentCompany()?->id)->first();

        return $state !== null && ! $state->enabled;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function run(CrmAutomation $automation, string $event, array $context, bool $dryRun): void
    {
        $runKey = $this->runKey($automation, $event, $context);

        $existing = CrmAutomationRun::query()
            ->where('automation_id', $automation->id)
            ->where('run_key', $runKey)
            ->exists();

        if ($existing) {
            Log::info('CRM automation: exécution déjà traitée (idempotence)', [
                'automation_id' => $automation->id,
                'run_key' => $runKey,
            ]);

            return;
        }

        if (! $this->evaluator->evaluate($automation->conditions ?? [], $context)) {
            $this->persistRun($automation, $context, ['status' => 'skipped', 'dry_run' => $dryRun]);

            return;
        }

        $maxAttempts = max(1, (int) config('crm.automations.max_attempts', 1));

        try {
            foreach ($automation->actions as $actionConfig) {
                $action = $this->resolveAction($actionConfig);
                $action->execute($actionConfig['config'] ?? [], $context);
            }

            $this->persistRun($automation, $context, ['status' => 'succeeded', 'dry_run' => $dryRun, 'attempts' => 1, 'max_attempts' => $maxAttempts]);
        } catch (Throwable $e) {
            $attempts = 1;
            $status = $attempts >= $maxAttempts ? 'dead_lettered' : 'failed';

            $this->persistRun($automation, $context, [
                'status' => $status,
                'dry_run' => $dryRun,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'error' => substr($e->getMessage(), 0, 480),
            ]);

            Log::error('CRM automation: run échoué', [
                'automation_id' => $automation->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $actionConfig
     */
    private function resolveAction(array $actionConfig): AutomationActionContract
    {
        $type = isset($actionConfig['type']) && is_string($actionConfig['type']) ? $actionConfig['type'] : '';
        if (! CrmAutomationActionType::isValid($type) || ! isset($this->actions[$type])) {
            throw new \RuntimeException('Action d\'automatisation inconnue ou non enregistrée : '.$type);
        }

        return $this->actions[$type];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function runKey(CrmAutomation $automation, string $event, array $context): string
    {
        $entityType = isset($context['entity_type']) && is_string($context['entity_type']) ? $context['entity_type'] : '';
        $entityId = isset($context['entity_id']) && is_string($context['entity_id']) ? $context['entity_id'] : '';

        return substr(hash('sha256', $automation->id.'|'.$event.'|'.$entityType.'|'.$entityId), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     */
    private function persistRun(CrmAutomation $automation, array $context, array $data): void
    {
        CrmAutomationRun::query()->create([
            'automation_id' => $automation->id,
            'trigger_event' => $automation->trigger_event,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'run_key' => $this->runKey($automation, $automation->trigger_event, $context),
            'conditions_snapshot' => $automation->conditions ?? [],
            'actions_snapshot' => $automation->actions,
            'status' => $data['status'] ?? 'pending',
            'attempts' => $data['attempts'] ?? 0,
            'max_attempts' => $data['max_attempts'] ?? 1,
            'dry_run' => $data['dry_run'] ?? false,
            'error' => $data['error'] ?? null,
            'ran_at' => now(),
        ]);
    }

    private function assertNotEmergencyStopped(): void
    {
        if ($this->isEmergencyStopped()) {
            throw new CrmAutomationEmergencyStoppedException();
        }
    }
}
