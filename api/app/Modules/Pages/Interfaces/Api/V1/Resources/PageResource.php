<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Resources;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO PRIVÉ d'une Page (BC PAGES, #8728).
 *
 * ## Pourquoi un DTO privé distinct du DTO public
 *
 * `PagePublicResource` (#8723) est une liste blanche destinée à une surface
 * sans authentification : il n'expose ni `company_id`, ni `site_id`, ni l'état
 * de vérification. L'éditeur tenant, lui, a besoin de ces champs pour afficher
 * l'état réel de sa Page. Deux surfaces, deux contrats — un DTO unique obligé
 * de « tout exposer pour l'éditeur » finirait par fuiter sur le public.
 *
 * ## `blocks` n'est inclus que si la relation est chargée
 *
 * `whenLoaded` évite une requête N+1 sur la liste (`GET /pages`) où les blocs
 * ne sont pas nécessaires. Le détail les charge explicitement.
 *
 * @mixin Page
 */
final class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        return [
            'id' => $page->id,
            'site_id' => $page->site_id,
            'slug' => $page->slug,
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toIso8601String(),
            'verification_status' => $page->verification_status->value,
            'verified_at' => $page->verified_at?->toIso8601String(),
            'verification_method' => $page->verification_method,
            'suspended_at' => $page->suspended_at?->toIso8601String(),
            'suspension_reason' => $page->suspension_reason,
            'theme' => $page->theme,
            'settings' => $page->settings,
            'legal' => $page->legal,
            'seo' => $page->seo,
            'locales' => $page->locales,
            'default_locale' => $page->default_locale,
            'custom_domain' => $page->custom_domain,
            'schema_version' => $page->schema_version,
            'public_path' => '/p/'.$page->slug,
            'blocks' => $this->whenLoaded('blocks', fn (): array => $page->blocks
                ->map(static fn (PageBlockModel $block): array => [
                    'id' => $block->block_uid,
                    'type' => $block->type,
                    'schema_version' => $block->schema_version,
                    'position' => $block->position,
                    'content' => $block->content,
                    'locales' => $block->locales ?? [],
                ])
                ->values()
                ->all()),
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }
}
