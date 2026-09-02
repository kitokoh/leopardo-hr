<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Échange d'un lien d'accès expirable (EDU-013).
 *
 * Le token est passé en body (jamais dans l'URL/logs) ; le hash sha256 est
 * comparé — le token brut n'est pas persistant.
 */
class RedeemGuardianAccessLinkRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]{64}$/'],
        ];
    }
}
