<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Resources;

use App\Modules\Branding\Domain\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'une Brand pour le TENANT (BC-41, #9578).
 *
 * ## Pourquoi une ressource et pas `toArray()`
 *
 * Le modèle porte des champs internes (`company_id`, `suspension_reason`) et
 * des champs dérivés (`palette.on_primary` recalculé). Une ressource explicite
 * garantit que :
 *  - `on_primary` renvoyé est celui CALCULÉ, pas celui stocké ;
 *  - un champ ajouté au modèle demain ne fuit pas par défaut ;
 *  - le contrat d'API est lisible à un seul endroit.
 *
 * @mixin Brand
 */
final class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;
        $palette = $brand->palette();

        return [
            'id' => $brand->id,
            'slug' => $brand->slug,
            'name' => $brand->name,
            'vertical' => $brand->vertical->value,
            'vertical_label' => $brand->vertical->label(),
            'status' => $brand->status->value,
            'is_default' => $brand->is_default,
            'palette' => $palette->toArray(),
            'palette_contrast' => [
                'on_primary_ratio' => round($palette->primaryContrastRatio(), 2),
                'meets_aa' => $palette->meetsAaOnPrimary(),
            ],
            'identity' => $brand->identity,
            'surface' => $brand->surface,
            'host' => $brand->host,
            'locales' => $brand->publishedLocales(),
            'default_locale' => $brand->defaultLocale(),
            'published_at' => $brand->published_at?->toIso8601String(),
            'suspended_at' => $brand->suspended_at?->toIso8601String(),
            'suspension_reason' => $brand->suspension_reason,
            'created_at' => $brand->created_at?->toIso8601String(),
            'updated_at' => $brand->updated_at?->toIso8601String(),
        ];
    }
}
