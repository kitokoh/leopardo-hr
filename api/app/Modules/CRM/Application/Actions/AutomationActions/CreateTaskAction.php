<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions\AutomationActions;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Action : créer une tâche CRM (table V0 `crm_tasks`, issue #5728).
 *
 * Schéma-gardé : tant que le socle V0 (#5710) n'est pas mergé, l'action
 * échoue proprement (run failed avec message explicite).
 */
final class CreateTaskAction implements AutomationActionContract
{
    public function type(): string
    {
        return CrmAutomationActionType::CREATE_TASK;
    }

    public function execute(array $config, array $context): void
    {
        if (! Schema::hasTable('crm_tasks')) {
            throw new \RuntimeException('Table crm_tasks indisponible (socle V0 #5710 non merge).');
        }

        $title = isset($config['title']) && is_string($config['title']) ? $config['title'] : 'Tache CRM';
        $dueAt = isset($config['due_at']) && is_string($config['due_at']) ? $config['due_at'] : now()->addDay();

        DB::table('crm_tasks')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => currentCompany()->id,
            'title' => $title,
            'due_at' => $dueAt,
            'done' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function simulate(array $config, array $context): array
    {
        return [
            'action' => $this->type(),
            'title' => $config['title'] ?? 'Tache CRM',
            'effect' => 'tache creee (simulation — aucune ecriture)',
        ];
    }
}
