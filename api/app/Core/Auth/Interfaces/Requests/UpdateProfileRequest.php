<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Requests;

use App\Rules\GlobalEmailUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->user()?->id;

        return [
            'first_name'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'personal_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'recovery_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'personal_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            // Issue #5587 : l'email principal (identifiant de connexion) ne peut
            // être modifié que si un mot de passe valide est fourni.
            // Sans cette garde : token/session volé → changement d'email →
            // forgot-password → prise de contrôle complète.
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($employeeId),
                new GlobalEmailUnique((int) $employeeId),
            ],
            'current_password' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Issue #5587 : si l'email est modifié, le mot de passe actuel est obligatoire
     * et doit correspondre au hash stocké. Ceci empêche la chaîne
     * « token volé → reset email → forgot-password → prise de contrôle ».
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = $this->user();
                if ($employee === null) {
                    return;
                }

                $newEmail = $this->input('email');
                if (! is_string($newEmail) || $newEmail === '') {
                    return;
                }

                // L'email n'a pas changé — pas de vérification supplémentaire.
                if (strtolower($newEmail) === strtolower((string) $employee->email)) {
                    return;
                }

                $currentPassword = $this->input('current_password');
                if (! is_string($currentPassword) || $currentPassword === '') {
                    $validator->errors()->add(
                        'current_password',
                        __('validation.required_with', [
                            'attribute' => 'current_password',
                            'values' => 'email',
                        ]),
                    );

                    return;
                }

                $passwordHash = (string) ($employee->password_hash ?? '');
                if (! Hash::check($currentPassword, $passwordHash)) {
                    $validator->errors()->add(
                        'current_password',
                        __('auth.password'),
                    );
                }
            },
        ];
    }
}
