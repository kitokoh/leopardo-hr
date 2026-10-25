<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmLeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8195 / ADR-CRM-005 — Validation stricte de la création d'un prospect (lead).
 */
class StoreCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            // #8930 — `first_name` n'est requis qu'à la CRÉATION. `PUT
            // /crm/leads/{id}` réutilise cette FormRequest pour une mise à jour
            // partielle : exiger `first_name` renvoyait 422 sur toute mise à
            // jour de statut, alors que le contrat documenté est un 404
            // cross-tenant (la garde tenant du contrôleur ne s'exécutait même
            // pas, la validation passant avant).
            'first_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(array_map(fn (CrmLeadStatus $s): string => $s->value, CrmLeadStatus::cases()))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
