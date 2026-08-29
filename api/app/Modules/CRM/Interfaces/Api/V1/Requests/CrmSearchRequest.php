<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #5719 — Validation stricte de la recherche CRM.
 *
 * Tous les filtres sont allowlistés : `type`/`status` en enums fermés,
 * `owner_id` entier positif, `q` borné (2..120), pagination bornée (50 max).
 * Aucun tri ni SQL libre n'est accepté.
 */
class CrmSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['sometimes', 'string', 'in:account,contact'],
            'status' => ['sometimes', 'string', 'in:active,inactive,archived'],
            'owner_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
