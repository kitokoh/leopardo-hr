<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'une campagne marketing CRM — Issue #5724.
 */
class UpdateCampaignRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'segment_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'audience' => ['sometimes', 'array', 'min:1', 'max:10000'],
            'audience.*' => ['integer', 'min:1'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'status' => ['prohibited'],
            'audience_snapshot' => ['prohibited'],
        ];
    }
}
