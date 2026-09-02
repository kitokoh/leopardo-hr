<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RESTO-409 (#6196) — Clôture d'une session de table (aucun corps requis ;
 * l'action calcule tout côté serveur). L'autorisation est tranchée par
 * `RestaurantTableSessionPolicy::close()`.
 */
class CloseRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantTableSessionPolicy::close() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
