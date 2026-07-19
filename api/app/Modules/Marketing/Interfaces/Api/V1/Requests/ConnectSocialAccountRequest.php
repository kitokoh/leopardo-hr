<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectSocialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:120'],
            // Un seul fournisseur supporte pour l'instant (Ayrshare, Phase 1-2).
            'provider' => ['sometimes', 'string', 'in:ayrshare'],
        ];
    }
}
