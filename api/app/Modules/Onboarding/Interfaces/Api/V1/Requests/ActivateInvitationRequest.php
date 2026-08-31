<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Activation d'invitation onboarding (mot de passe).
 */
class ActivateInvitationRequest extends FormRequest
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
        return ['password' => ['required', 'string', 'min:8', 'confirmed']];
    }
}
