<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-504 (#6203) — Validation stricte de saisie d'une ligne d'inventaire.
 *
 * `counted_qty` (quantité comptée, ≥ 0) ; `reason_code` obligatoire si
 * l'écart est non nul (bloqué à l'approbation, 422). La variance est
 * calculée serveur (comptée − attendue).
 */
class UpdateRestaurantInventoryCountItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantInventoryCountPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'numeric', 'min:0', 'max:999999'],
            'reason_code' => ['nullable', 'string', Rule::in(['damage', 'loss', 'theft', 'miscount', 'expiry', 'other'])],
        ];
    }
}
