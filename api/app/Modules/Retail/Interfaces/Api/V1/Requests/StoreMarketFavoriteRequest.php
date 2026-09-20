<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout d'un favori acheteur (#7814) —
 * POST /public/market/account/favorites (auth buyer).
 *
 * Le produit est identifie par son id public numerique (le meme que
 * GET /public/market/products/{id}) — la reference interne
 * (company_id vendeur) est resolue cote serveur, jamais fournie par le
 * client.
 */
class StoreMarketFavoriteRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
