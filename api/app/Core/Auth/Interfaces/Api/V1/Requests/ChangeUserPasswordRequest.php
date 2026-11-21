<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use App\Shared\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement de mot de passe (#9732).
 */
final class ChangeUserPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'new_password' => PasswordPolicy::required(),
        ];
    }
}
