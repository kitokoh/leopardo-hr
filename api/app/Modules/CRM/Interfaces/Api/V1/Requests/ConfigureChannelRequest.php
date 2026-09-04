<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmChannelType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuration d'un canal CRM (issue #5725/#5727).
 *
 * Entrées strictes : type/provider whitelistés, settings en objet, quota
 * borné. Les secrets (tokens) sont explicitement REFUSÉS ici — ils ne
 * passent que par le secret manager/env.
 */
final class ConfigureChannelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', CrmChannelType::values())],
            'provider' => ['required', 'string', 'max:60'],
            'is_configured' => ['sometimes', 'boolean'],
            'monthly_quota' => ['sometimes', 'integer', 'nullable', 'min:0', 'max:1000000'],
            'settings' => ['sometimes', 'array'],
            'settings.phone_number_id' => ['sometimes', 'string', 'max:160'],
            'settings.language_code' => ['sometimes', 'string', 'size:2'],
            // Anti-fuite : interdiction formelle des champs secrets dans le payload.
            'settings.token' => ['prohibited'],
            'settings.api_key' => ['prohibited'],
            'settings.secret' => ['prohibited'],
        ];
    }
}
