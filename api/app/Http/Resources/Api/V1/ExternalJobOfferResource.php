<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Recruitment\Domain\Models\ExternalJobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BC-10 RECRUITMENT — projection PUBLIQUE d'une offre agrégée.
 *
 * Liste blanche stricte : cette ressource est servie à des visiteurs anonymes.
 * Elle n'expose JAMAIS `raw_payload` (charge utile brute du fournisseur, qui
 * peut contenir des champs internes ou des données personnelles de recruteur),
 * ni `source_id`, ni les identifiants du connecteur.
 *
 * Un test verrouille cette liste : ajouter un champ interne ici doit faire
 * échouer la suite (voir PublicCareerPortalTest).
 *
 * @mixin ExternalJobOffer
 */
class ExternalJobOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'description' => $this->description,
            'company_name' => $this->company_name,
            'location' => $this->location,
            'country' => $this->country,
            'remote_policy' => $this->remote_policy,
            'contract_type' => $this->contract_type,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'currency' => $this->currency,
            'apply_url' => $this->apply_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            // Provenance affichée honnêtement : le visiteur doit savoir que
            // l'offre vient d'un partenaire et que la candidature se fait chez
            // lui, pas sur notre portail.
            'origin' => 'external',
            'source' => $this->whenLoaded('source', function (): ?array {
                $source = $this->source;

                return $source === null ? null : [
                    'provider' => $source->provider,
                    'label' => $source->label,
                ];
            }),
        ];
    }
}
