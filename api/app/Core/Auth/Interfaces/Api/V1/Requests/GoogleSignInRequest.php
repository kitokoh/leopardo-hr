<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connexion via Google (#9732).
 *
 * `id_token` est verifie cryptographiquement par le service d'authentification,
 * pas par la validation de forme : ici on ne controle que sa presence.
 */
final class GoogleSignInRequest extends FormRequest
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
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
