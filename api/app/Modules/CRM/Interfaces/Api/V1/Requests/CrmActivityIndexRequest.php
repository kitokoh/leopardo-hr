<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de `GET /v1/crm/activities` (timeline CRM).
 *
 * Extrait de `CrmActivityController::index()` — la validation en ligne y
 * violait la décision « FormRequest partout » (#9699/#9706).
 */
class CrmActivityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission `crm.timeline` tranchée par le contrôleur
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(CrmActivity::TYPES)],
            'account_id' => ['sometimes', 'integer', 'min:1'],
            'contact_id' => ['sometimes', 'integer', 'min:1'],
            'lead_id' => ['sometimes', 'integer', 'min:1'],
            'opportunity_id' => ['sometimes', 'integer', 'min:1'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
