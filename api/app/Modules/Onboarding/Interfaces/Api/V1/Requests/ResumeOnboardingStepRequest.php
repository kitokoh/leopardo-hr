<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BOS-O-06 / #8627 — validation de la reprise d'étape d'onboarding.
 *
 * La clé d'étape est validée en FORME ici (identifiant technique borné) ; son
 * existence réelle est vérifiée côté contrôleur contre les étapes du tenant —
 * on ne peut donc pas sonder les étapes d'une autre société par cette route.
 */
final class ResumeOnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation métier (rôle gestionnaire) est appliquée par le
        // contrôleur, qui renvoie un code d'erreur i18n stable ; ici on
        // n'ouvre rien de plus que le middleware d'authentification.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'step_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
