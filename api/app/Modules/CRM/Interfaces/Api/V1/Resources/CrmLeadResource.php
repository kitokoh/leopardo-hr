<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #5717 — Sérialisation d'un lead CRM.
 *
 * PII protégée : email/téléphone exposés uniquement dans le périmètre
 * autorisé (les Resources masquent par défaut, cf. #5713).
 *
 * @property CrmLead $resource
 */
class CrmLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lead = $this->resource;

        return [
            'id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'source' => $lead->source,
            'status' => $lead->status,
            'score' => $lead->score,
            'notes' => $lead->notes,
            'converted_at' => $lead->converted_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
