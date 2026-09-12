<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'un média de vitrine (BC-27 SHOWCASE, #6872).
 *
 * Exposé aux seuls gestionnaires du tenant (routes authentifiées + Policy).
 * Ne contient JAMAIS `company_id`, `disk` ni `path` (champs internes de
 * stockage — test de non-fuite) : le client ne manipule que l'id stable et
 * l'URL publique relative (`url`), résolue côté serveur.
 *
 * @mixin ShowcaseMedia
 */
final class ShowcaseMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShowcaseMedia $media */
        $media = $this->resource;

        $slug = $media->showcase?->slug;

        return [
            'id' => $media->id,
            'kind' => $media->kind->value,
            'showcase_id' => $media->showcase_id,
            'section_id' => $media->section_id,
            'original_name' => $media->original_name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'size' => $media->size,
            'url' => $slug !== null ? '/public/vitrine/'.$slug.'/media/'.$media->id : null,
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
