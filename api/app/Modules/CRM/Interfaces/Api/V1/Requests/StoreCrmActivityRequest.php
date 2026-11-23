<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corps de `POST /v1/crm/activities` (création d'une activité CRM).
 *
 * Extrait de `CrmActivityController::store()` — la validation en ligne y
 * violait la décision « FormRequest partout » (#9699/#9706).
 */
class StoreCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission `crm.timeline` tranchée par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CrmActivity::TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'lead_id' => ['nullable', 'integer', 'min:1'],
            'opportunity_id' => ['nullable', 'integer', 'min:1'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
