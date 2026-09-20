<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Soumission d'un avis verifie (#7814) —
 * POST /public/market/account/reviews (auth buyer, throttle strict
 * anti-spam).
 *
 * La commande est referencee par sa `reference` publique (non enumerable,
 * prefixe WEB-) : l'appartenance au buyer, l'etat `delivered` et la
 * presence du produit dans la commande sont verifies par
 * RetailBuyerReviewService. Longueur max du commentaire bornee (anti-abus).
 */
class StoreMarketReviewRequest extends FormRequest
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
            'order_reference' => ['required', 'string', 'max:64'],
            'product_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
