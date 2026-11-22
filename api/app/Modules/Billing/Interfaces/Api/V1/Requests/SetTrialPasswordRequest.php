<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Requests;

use App\Shared\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Choix du mot de passe d'essai (#9732).
 *
 * Le token n'est PAS une regle de forme ici : il vit dans l'en-tete `X-Token`
 * (convention #4931) et sa validite metier (usage unique, expiration 72 h) est
 * portee par l'Action `SetTrialPassword`, pas par la validation HTTP.
 */
class SetTrialPasswordRequest extends FormRequest
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
            'password' => PasswordPolicy::required(),
        ];
    }
}
