<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Verification OTP de l'essai (#9732).
 *
 * Extraite du `SelfServiceTrialController::verify()` : deux regles en ligne
 * dans une methode qui fait aussi du provisioning et de l'auto-connexion.
 */
class VerifyTrialRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
