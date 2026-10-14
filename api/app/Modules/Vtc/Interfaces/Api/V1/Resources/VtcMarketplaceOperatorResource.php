<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Resources;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Opérateur VTC publié sur la marketplace publique (#8423, M-11) — DTO
 * STRICT : identité publique uniquement (slug déjà exposé par la vitrine,
 * nom commercial, ville/pays d'ancrage, devise d'affichage des devis).
 *
 * Jamais d'identifiant interne (company_id), ni coordonnées de contact,
 * ni metadata, ni configuration tarifaire interne : le prix d'une course
 * s'obtient via `POST /public/vtc/marketplace/estimate` (grille appliquée
 * côté serveur, pattern travel — les prix publics sont ceux des ressources
 * réservables, pas la configuration de l'agence).
 *
 * @mixin Company
 */
final class VtcMarketplaceOperatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
        ];
    }
}
