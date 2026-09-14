<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Fleet\Domain\Models\VehicleAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerte flotte (excès de vitesse, sortie de geofence, SOS…).
 *
 * #7399 — la ressource exposait `severity` et `resolved_at`, deux attributs
 * absents de `vehicle_alerts` (colonnes réelles : `type`, `message`,
 * `latitude`, `longitude`, `speed`, `acknowledged`, `acknowledged_by`).
 * Conséquences : l'admin plateforme (`FleetView.vue` : colonne « Sévérité »,
 * `severityMap`, compteur d'alertes critiques) lisait toujours `null`, et la
 * position GPS de l'alerte n'était pas exposée — donc inutilisable sur une
 * carte.
 *
 * `severity` reste exposé mais est désormais **dérivé du `type`** par le
 * modèle (`VehicleAlert::getSeverityAttribute()`) : le schéma ne le stocke pas,
 * et l'information est portée par le type. `resolved_at` est retiré — il n'a
 * jamais existé et n'est consommé par aucune surface (l'acquittement est porté
 * par `acknowledged` / `acknowledged_by`).
 *
 * @mixin VehicleAlert
 */
class VehicleAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,

            // Localisation de l'événement (colonnes réelles).
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speed,

            // Acquittement (remplace le `resolved_at` fantôme).
            'acknowledged' => $this->acknowledged,
            'acknowledged_by' => $this->acknowledged_by,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
