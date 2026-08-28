<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5712 — Contrat de sortie d'un lead CRM client.
 *
 * Ne jamais exposer de données d'un autre tenant : `company_id` reste
 * présent pour l'audit, la Policy + le scope BelongsToCompany garantissent
 * que seules les lignes du tenant courant atteignent cette resource.
 */
class CrmLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'source' => $this->source,
            'status' => $this->status,
            'priority' => $this->priority,
            'owner_id' => $this->owner_id,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toISOString(),
            'converted_opportunity_id' => $this->converted_opportunity_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
