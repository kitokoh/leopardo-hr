<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

use App\Modules\Trade\Domain\Models\TradeCounterparty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrepartie Trade — BC-36 TRADE, #9779.
 *
 * ## Ce que la ressource expose, et ce qu'elle cache
 *
 * `verification_status` est renvoye TEL QUEL, y compris `verified` : c'est un
 * etat interne que l'espace vendeur doit afficher. L'interface a la charge de
 * le presenter comme « verifie par un membre de l'equipe » — jamais comme le
 * resultat d'un controle automatise, puisqu'aucun service de verification
 * d'entreprise n'est souscrit (cf. docblock de `TradeVerificationStatus`).
 *
 * `deleted_at` n'est jamais expose : le perimetre de suppression est une
 * affaire serveur.
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
            // Le cast du modele garantit l'enum : le test `instanceof` etait
            // toujours vrai et ne protegeait rien.
            'verification_status' => $this->verification_status->value,
            // Uniquement la DATE de verification, jamais l'identifiant de
            // l'employe qui l'a faite : l'audit interne n'a pas a circuler
            // dans une charge utile de liste.
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_note' => $this->verification_note,
            'capabilities' => $this->capabilities ?? [],
            'notes' => $this->notes,
            'crm_account_id' => $this->crm_account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
