<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Règles de validation pour le changement de mot de passe.
 *
 * #5620 — renforcement : Password::min(8)->numbers() exige au moins un
 * chiffre, en cohérence avec l'indicateur de force du frontend.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            // Issue #5620 : min 8 caractères + au moins 1 chiffre.
            'new_password' => ['required', 'string', Password::min(8)->numbers(), 'max:255', 'confirmed'],
        ];
    }
}
