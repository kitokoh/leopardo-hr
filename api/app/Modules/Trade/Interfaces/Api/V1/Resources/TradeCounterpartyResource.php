<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeCounterparty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrepartie Trade (client, fournisseur, transporteur…) — BC-36 TRADE, #9780.
 *
 * ## Ce que cette ressource ne fait pas
 *
 * Elle n'expose **aucune donnée interne du CRM**. `crm_account_id` est rendu
 * comme un simple identifiant de liaison (ou `null` quand le module CRM n'est
 * pas actif) : la timeline, les consentements et les contacts CRM restent la
 * propriété du module CRM, qui a ses propres policies.
 *
 * ## `verification_status` : une vérification HUMAINE
 *
 * `verified` signifie « vérifié par un membre de l'équipe », jamais « vérifié
 * automatiquement ». La ressource expose donc `verified_by_employee_id` et
 * `verified_at` : l'interface peut dire QUI a vérifié et QUAND, ce qui est la
 * seule formulation honnête tant qu'aucun service de vérification externe
 * n'est souscrit.
 *
 * @mixin TradeCounterparty
 */
final class TradeCounterpartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'city' => $this->city,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'roles' => $this->roles ?? [],
            'verification_status' => $this->verification_status->value,
            'verified_by_employee_id' => $this->verified_by_employee_id,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_note' => $this->verification_note,
            'capabilities' => $this->capabilities ?? [],
            'notes' => $this->notes,
            // Liaison CRM optionnelle : un identifiant, jamais le contenu du compte.
            'crm_account_id' => $this->crm_account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
