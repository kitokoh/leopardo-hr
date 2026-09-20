<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Modules\Retail\Domain\Support\RetailPricePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create-or-update des reglages de la boutique en ligne Leopardo Marche
 * (BC-17 RETAIL, #7807).
 *
 * `shop_name` requis (nom public de la vitrine), `enabled` = opt-in
 * marketplace (defaut false, fail-closed), devise d'affichage ISO 4217
 * bornee a la liste RetailPricePolicy (pattern produits #7672).
 */
class UpdateRetailOnlineSettingsRequest extends FormRequest
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
            'enabled' => ['nullable', 'boolean'],
            'shop_name' => ['required', 'string', 'max:160'],
            'shop_description' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::in(RetailPricePolicy::allowedCurrencies())],
        ];
    }
}
