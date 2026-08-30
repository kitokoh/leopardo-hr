<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * TRAVEL-505 (#6075) — Représentation API d'un asset d'export.
 * `signed_url` régénéré à chaque lecture (éphémère, TTL 60 min — jamais
 * stocké en base).
 *
 * @mixin TravelExportAsset
 */
class TravelExportAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'report_type' => $this->report_type,
            'status' => $this->status,
            'from_at' => $this->from_at,
            'to_at' => $this->to_at,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'error' => $this->error_redacted,
        ];

        if ($this->status === TravelExportAsset::STATUS_GENERATED && $this->file_path !== null) {
            $data['signed_url'] = Storage::disk('local')->temporaryUrl(
                $this->file_path,
                now()->addMinutes(TravelExportAsset::SIGNED_URL_TTL_MINUTES),
            );
        }

        return $data;
    }
}
