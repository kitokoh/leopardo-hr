<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Resources;

use App\Modules\Pages\Domain\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO public d'une Page (BC PAGES, #8723 / #8724).
 *
 * Liste **blanche** : seuls les champs explicitement publics sortent d'ici.
 * Renvoyer `$page->toArray()` exposerait `company_id`, `site_id`, le statut de
 * vérification et `suspension_reason` — c'est-à-dire l'état interne du tenant
 * sur une surface sans authentification. La liste blanche fait aussi qu'un
 * champ ajouté au modèle demain ne fuit pas par défaut.
 *
 * `seo` est filtré : seuls `title` et `description` sortent. Les autres clés
 * (canonical interne, images de travail, notes de rédaction) ne sont pas
 * destinées au visiteur et pourraient révéler une URL de préproduction.
 *
 * @mixin Page
 */
final class PagePublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        /** @var array<string, mixed> $seo */
        $seo = is_array($page->seo) ? $page->seo : [];

        return [
            'slug' => $page->slug,
            'locale' => $page->default_locale,
            'title' => $seo['title'] ?? null,
            'description' => $seo['description'] ?? null,
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }
}
