<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmAutomationOperator;
use App\Modules\CRM\Domain\Enums\CrmAutomationTrigger;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création/mise à jour d'une automatisation CRM (issue #5728).
 *
 * Entrées strictes : trigger/actions/opérateurs whitelistés, pas de champs
 * inconnus. Les actions sont bornées (max 5) et leur config est un objet
 * libre validé par l'action elle-même à l'exécution (schéma-gardé).
 */
final class StoreCrmAutomationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'trigger_event' => ['required', 'string', 'in:'.implode(',', CrmAutomationTrigger::values())],
            'conditions' => ['sometimes', 'array', 'max:10'],
            'conditions.*.field' => ['required', 'string', 'max:120'],
            'conditions.*.operator' => ['required', 'string', 'in:'.implode(',', CrmAutomationOperator::values())],
            'actions' => ['required', 'array', 'min:1', 'max:5'],
            'actions.*.type' => ['required', 'string', 'in:'.implode(',', CrmAutomationActionType::values())],
            'actions.*.config' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', 'in:draft,active,paused'],
        ];
    }
}
