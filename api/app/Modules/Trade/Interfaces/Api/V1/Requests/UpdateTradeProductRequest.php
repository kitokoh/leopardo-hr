<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un produit du catalogue B2B — BC-36 TRADE, #9767.
 *
 * `PUT` = remplacement complet des champs éditables : tous sont `sometimes`
 * pour qu'un client puisse n'envoyer que ce qu'il change sans effacer le
 * reste, mais un champ envoyé `null` l'efface réellement (sauf `name`, qui
 * reste obligatoire s'il est présent).
 *
 * `status` n'est **pas** modifiable ici : l'archivage est un acte distinct
 * (`POST /trade/products/{product}/archive`), comme la publication l'est du
 * profil commercial. Un `PUT` ne doit pas pouvoir archiver un produit par
 * inadvertance.
 */
final class UpdateTradeProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'hs_code' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{6,10}$/'],
            'origin_country' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'moq_quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'indicative_unit_price_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'specs' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
