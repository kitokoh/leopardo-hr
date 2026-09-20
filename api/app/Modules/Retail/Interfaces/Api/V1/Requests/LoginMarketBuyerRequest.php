<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connexion d'un compte acheteur marketplace (#7814) —
 * POST /public/market/account/login (public, throttle strict, 401 uniforme).
 */
class LoginMarketBuyerRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:100'],
        ];
    }
}
