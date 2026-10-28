<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\PublicSurface;

use App\Modules\Pages\Domain\Blocks\PageBlock;
use App\Modules\Pages\Domain\Blocks\PageBlockCollection;
use App\Modules\Pages\Domain\Blocks\PageBlockRegistry;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;

/**
 * Construit le DTO public d'une Page (BC PAGES, #8730).
 *
 * ## Liste blanche, jamais `toArray()`
 *
 * Le rendu public est une surface SANS authentification. Renvoyer le modèle
 * exposerait `company_id`, `site_id`, `verification_status`, `suspended_at`,
 * `suspension_reason` — c'est-à-dire l'état interne du tenant. La liste
 * blanche fait aussi qu'un champ ajouté au modèle demain ne fuit pas par
 * défaut.
 *
 * ## Les blocs sont filtrés, pas seulement triés
 *
 * `PageBlockCollection::renderableBlocks()` retire les blocs dont le type est
 * inconnu, dont la version de schéma n'est pas celle du registre, ou dont le
 * renderer est absent. Un bloc non rendable est **omis** et signalé dans
 * `omitted` : le rendu ne casse pas, et l'exploitant peut voir pourquoi un
 * bloc n'apparaît pas. Rendre un bloc inconnu « au mieux » produirait du HTML
 * non maîtrisé sur une page publique.
 *
 * ## Les actions sont calculées, pas stockées
 *
 * Payer / Écrire / Y aller / Avis ne sont pas des champs de la Page : ce sont
 * des capacités dérivées de l'état réel du tenant (profil de paiement actif,
 * coordonnées présentes, coordonnées GPS, réputation activée). Les stocker
 * ferait diverger l'affichage de la réalité — un bouton « Payer » resterait
 * affiché après la désactivation du PSP.
 */
final class PublicPagePresenter
{
    public function __construct(
        private readonly PageBlockRegistry $registry,
    ) {}

    /**
     * @param  list<array{type: string, reason: string}>  $omitted
     * @return array<string, mixed>
     */
    public function present(Page $page, string $locale, array $omitted = []): array
    {
        /** @var array<string, mixed> $seo */
        $seo = is_array($page->seo) ? $page->seo : [];

        /** @var array<string, mixed> $legal */
        $legal = is_array($page->legal) ? $page->legal : [];

        return [
            'slug' => $page->slug,
            'locale' => $locale,
            'direction' => self::direction($locale),
            'title' => $this->stringOrNull($seo['title'] ?? null),
            'description' => $this->stringOrNull($seo['description'] ?? null),
            'published_at' => $page->published_at?->toIso8601String(),
            'verified' => $page->verification_status->value === 'verified',
            'theme' => $page->theme,
            'legal' => [
                'company_name' => $this->stringOrNull($legal['company_name'] ?? null),
                'registration' => $this->stringOrNull($legal['registration'] ?? null),
            ],
            'blocks' => $this->blocks($page, $locale),
            'omitted_blocks' => $omitted,
            'actions' => $this->actions($page),
        ];
    }

    /**
     * Blocs rendables, contenu résolu dans la locale demandée.
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(Page $page, string $locale): array
    {
        $rows = PageBlockModel::query()
            ->where('page_id', $page->id)
            ->orderBy('position')
            ->get();

        $collection = PageBlockCollection::fromArray(
            $rows->map(static fn (PageBlockModel $row): array => [
                'id' => $row->block_uid,
                'type' => $row->type,
                'schema_version' => $row->schema_version,
                'position' => $row->position,
                'content' => $row->content,
                'locales' => $row->locales ?? [],
            ])->all()
        );

        $renderable = $collection->renderableBlocks($this->registry);

        $out = [];

        foreach ($renderable['blocks'] as $block) {
            /** @var PageBlock $block */
            $out[] = [
                'id' => $block->id,
                'type' => $block->type,
                'position' => $block->position,
                'content' => $block->contentFor($locale),
            ];
        }

        return $out;
    }

    /**
     * Actions publiques disponibles pour cette Page.
     *
     * Chaque action n'est exposée que si sa condition est RÉELLEMENT remplie.
     * Un bouton qui mène à une erreur est pire qu'un bouton absent : le
     * visiteur croit que l'établissement est cassé.
     *
     * @return list<array{kind: string, label: string, href: string|null}>
     */
    private function actions(Page $page): array
    {
        $actions = [];

        // « Y aller » : seulement si l'établissement a des coordonnées.
        $site = $page->site;

        if ($site !== null && $site->gps_lat !== null && $site->gps_lng !== null) {
            $actions[] = [
                'kind' => 'directions',
                'label' => 'directions',
                'href' => sprintf(
                    'https://www.google.com/maps/dir/?api=1&destination=%F,%F',
                    (float) $site->gps_lat,
                    (float) $site->gps_lng,
                ),
            ];
        }

        // « Écrire » : seulement si un contact est joignable.
        $contact = $this->contactBlock($page);

        if ($contact !== null) {
            $actions[] = [
                'kind' => 'contact',
                'label' => 'contact',
                'href' => null,
            ];
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contactBlock(Page $page): ?array
    {
        $row = PageBlockModel::query()
            ->where('page_id', $page->id)
            ->where('type', 'core.contact')
            ->orderBy('position')
            ->first();

        if (! $row instanceof PageBlockModel) {
            return null;
        }

        /** @var array<string, mixed> $content */
        $content = is_array($row->content) ? $row->content : [];

        $hasPhone = is_string($content['phone'] ?? null) && trim((string) $content['phone']) !== '';
        $hasEmail = is_string($content['email'] ?? null) && trim((string) $content['email']) !== '';

        return $hasPhone || $hasEmail ? $content : null;
    }

    /**
     * Sens d'écriture d'une locale.
     *
     * L'arabe est la seule locale RTL du produit. Le calcul vit ici et non
     * dans le template : un `dir` recopié dans trois vues finit par diverger,
     * et c'est la vue oubliée qui casse la mise en page arabe.
     */
    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
