<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un produit du catalogue B2B — BC-36 TRADE, #9767.
 *
 * ## `hs_code` : nullable, jamais deviné
 *
 * Le code douanier harmonisé est **facultatif**. Le système ne le déduit
 * jamais d'une catégorie ou d'un nom de produit : une valeur inventée ferait
 * croire à une conformité douanière qui n'existe pas. S'il est fourni, il est
 * validé sur 6 à 10 chiffres (format HS réel) — un code à 3 chiffres est un
 * chapitre, pas un classement.
 *
 * ## Montants
 *
 * `indicative_unit_price_minor` est un **entier** en minor units. Aucun
 * flottant ne traverse l'API pour représenter de l'argent.
 */
final class StoreTradeProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            // 6 à 10 chiffres : un code HS réel. Jamais déduit, seulement saisi.
            'hs_code' => ['nullable', 'string', 'regex:/^[0-9]{6,10}$/'],
            'origin_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'unit' => ['nullable', 'string', 'max:20'],
            'moq_quantity' => ['nullable', 'numeric', 'gt:0'],
            'indicative_unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'specs' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,archived'],
        ];
    }
}
