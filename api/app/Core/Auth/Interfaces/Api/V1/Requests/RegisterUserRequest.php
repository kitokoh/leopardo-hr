<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use App\Shared\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription d'un utilisateur (#9732).
 *
 * #8021 — politique de mot de passe unique du depot (#5620), sans confirmation
 * a l'inscription.
 */
final class RegisterUserRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => PasswordPolicy::required(confirmed: false),
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
