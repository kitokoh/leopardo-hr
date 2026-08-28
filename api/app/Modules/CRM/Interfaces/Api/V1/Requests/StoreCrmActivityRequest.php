<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmActivity;
use Illuminate\Validation\Rule;

/**
 * Issue #5711 — Création d'une entrée de timeline CRM client.
 *
 * Timeline append-only : pas de requête de mise à jour. `type` est
 * strictement allowlisté (CHECK en base, rejet 422 ici).
 */
class StoreCrmActivityRequest extends BaseCrmRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CrmActivity::TYPES)],
            'subject' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
        ];
    }
}
