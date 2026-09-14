<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Fleet\Domain\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'type' => $this->type,
            'vin' => $this->vin,
            'fuel_type' => $this->fuel_type,
            'status' => $this->status,
            'mileage' => $this->mileage,
            'insurance_expiry' => $this->insurance_expiry?->toDateString(),
            'technical_control_expiry' => $this->technical_control_expiry?->toDateString(),
            'assigned_driver_id' => $this->assigned_driver_id,
            // Correctif audit 2026-09-14 — le rattachement du traceur était
            // OMIS de la sérialisation : l'interface ne pouvait pas indiquer
            // si un véhicule est suivi (ni distinguer « pas de traceur » de
            // « traceur non synchronisé »), alors que `POST /tracking/
            // sync-devices` alimente bien `traccar_device_id`.
            'traccar_device_id' => $this->traccar_device_id,
            'traccar_unique_id' => $this->traccar_unique_id,
            'assigned_site_id' => $this->assigned_site_id,
            // `metadata` porte les qualificatifs d'exploitation saisis par le
            // client — dont `usage` (« service », « exploitation ») qui est
            // aujourd'hui le seul moyen de désigner un VÉHICULE DE SERVICE
            // (aucune colonne dédiée : cf. rapport d'audit).
            'metadata' => $this->metadata ?? new \stdClass,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
