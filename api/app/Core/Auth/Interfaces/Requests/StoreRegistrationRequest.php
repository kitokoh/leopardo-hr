<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            // #3364 : l'inscription met à jour l'employé EXISTANT de
            // l'invitation (employee_id NOT NULL) — la règle unique bloquait
            // le flux (l'email est déjà en base). La garde réelle reste le
            // jeton d'invitation (issu de UserInvitationService::createAndSend).
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
            // Issue #2617 : inscription réservée aux invitations valides.
            'invitation_token' => ['required', 'string', 'max:64'],
        ];
    }
}
