<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use App\Shared\Rules\NotCommonPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Règles de validation pour le changement de mot de passe.
 *
 * QA onboarding 2026-09-14 — longueur minimale portée à 12 (la politique
 * vivait à 8 côté API alors qu'un helper front, jamais appelé, annonçait
 * 8 + majuscule + chiffre + spécial : `abc12345` était accepté en production).
 * Toute modification ici doit être répercutée dans `front/web/src/lib/
 * password-policy.ts` (source unique côté client).
 *
 * #5620 — renforcement : Password::min(12)->numbers() exige au moins un
 * chiffre, en cohérence avec l'indicateur de force du frontend.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            // Issue #5620 : min 8 caractères + au moins 1 chiffre.
            'new_password' => ['required', 'string', Password::min(12)->numbers(), new NotCommonPassword, 'max:255', 'confirmed'],
        ];
    }
}
