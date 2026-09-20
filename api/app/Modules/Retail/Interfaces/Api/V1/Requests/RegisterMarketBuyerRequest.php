<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Shared\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription legere d'un compte acheteur marketplace (#7814) —
 * POST /public/market/account/register (public, throttle strict).
 */
class RegisterMarketBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:marketplace_buyers,email'],
            // #7995 — surface PUBLIQUE : même norme #5620 que le back-office
            // (pas de 'confirmed' : le client public n'envoie pas de confirmation).
            'password' => PasswordPolicy::required(confirmed: false, max: 100),
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }
}
