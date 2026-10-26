<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Géocodage d'une adresse (GEO-08, issue #8466, BC-34 GEO).
 *
 * Contrat `POST /v1/geo/geocode` : `{address, country_code?}` →
 * `{data:{latitude, longitude, provider, cached}}`. L'adresse est bornée
 * (longueur) pour éviter d'envoyer une charge arbitraire au fournisseur.
 */
final class GeocodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
        ];
    }
}
