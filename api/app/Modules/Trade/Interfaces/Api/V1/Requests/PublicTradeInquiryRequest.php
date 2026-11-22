<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de devis d'un visiteur PUBLIC — #9779.
 *
 * Surface non authentifiee : la validation est la seule barriere avant
 * l'ecriture d'un lead dans le tenant du vendeur. Elle est donc stricte sur
 * les bornes (longueurs) et sur le format de l'email, et n'accepte AUCUN
 * champ de perimetre (`company_id`, `status`, `source`) : ces valeurs sont
 * fixees par le serveur, jamais par le visiteur.
 */
final class PublicTradeInquiryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // Nom du produit vise, tel qu'affiche sur le catalogue public. Ce
            // n'est PAS un identifiant : le catalogue public n'expose aucun id
            // de produit, et en accepter un permettrait de sonder le catalogue
            // d'un tenant non publie.
            'product_name' => ['nullable', 'string', 'max:180'],
        ];
    }
}
