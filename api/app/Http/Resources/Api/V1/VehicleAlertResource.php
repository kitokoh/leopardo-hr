<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Fleet\Domain\Models\VehicleAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #7399 — CONTRAT STRICT : uniquement des colonnes réelles de `vehicle_alerts`.
 * Les champs fantômes historiques (`severity`, `resolved_at`) n'existent pas
 * dans le schéma et sortaient toujours à `null` ; la position GPS
 * (`latitude`/`longitude`/`speed`) et l'acquittement (`acknowledged`,
 * `acknowledged_by`) n'étaient jamais exposés — les alertes étaient
 * inexploitables sur une carte.
 *
 * Toute évolution doit être couverte par VehicleResourceContractTest.
 *
 * @mixin VehicleAlert
 */
class VehicleAlertResource extends JsonResource
{
    /** @return array<int, string> */
    public static function exposedKeys(): array
    {
        return [
            'id',
            'vehicle_id',
            'type',
            'message',
            'latitude',
            'longitude',
            'speed',
            'acknowledged',
            'acknowledged_by',
            'created_at',
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type,
            'message' => $this->message,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speed,
            'acknowledged' => $this->acknowledged,
            'acknowledged_by' => $this->acknowledged_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
