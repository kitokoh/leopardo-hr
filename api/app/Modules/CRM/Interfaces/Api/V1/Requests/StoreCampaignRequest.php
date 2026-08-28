<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une campagne marketing CRM — Issue #5724.
 *
 * L'audience est un segment (segment_id) OU une liste explicite
 * (audience) — jamais les deux (contrainte métier, service).
 */
class StoreCampaignRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms,whatsapp'],
            'segment_id' => ['nullable', 'integer', 'min:1'],
            'audience' => ['nullable', 'array', 'min:1', 'max:10000'],
            'audience.*' => ['integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
            'id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'status' => ['prohibited'],
            'audience_snapshot' => ['prohibited'],
        ];
    }
}
