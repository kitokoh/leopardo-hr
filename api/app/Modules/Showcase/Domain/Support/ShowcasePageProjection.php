<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Enums\PageProjectionStatus;
use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;

/**
 * Projection décorative `company_showcases` → `pages` + `page_blocks` (#8727).
 *
 * ## Rôle
 *
 * Ce n'est PAS un modèle ni une commande : c'est la seule description de la
 * façon dont une vitrine se traduit en Page. Le backfill (`showcase:pages-backfill`)
 * et le contrôle de parité l'appellent tous les deux, ce qui interdit
 * structurellement que le second valide autre chose que ce que le premier écrit.
 *
 * ## Sans perte — c'est une contrainte, pas une intention
 *
 * `CorePageBlocks` (BC PAGES, #8724) porte les sept types de sections Showcase
 * avec **les mêmes noms de propriétés** (`heading`, `subheading`, `image_url`,
 * `cta_label`, `cta_url`, `items`, …). La migration n'a donc aucune
 * transformation de contenu à effectuer : le `content` d'une section est
 * recopié tel quel, seul l'identifiant de type est préfixé `core.`
 * (`hero` → `core.hero`). C'est ce qui rend la migration vérifiable :
 * le contrôle de parité compare le contenu octet pour octet.
 *
 * ## Ce que la projection refuse de faire
 *
 * Une vitrine sans établissement, ou sur un tenant à plusieurs établissements,
 * n'est **pas** projetable : voir `PagesInventoryBuilder`. `pages.site_id` est
 * `UNIQUE` (une Page par établissement en V1), donc choisir un Site reviendrait
 * à décider quel établissement porte la vitrine — décision produit, pas
 * heuristique. Ces vitrines sont laissées intactes et remontées comme
 * bloquantes.
 */
final class ShowcasePageProjection
{
    /** Préfixe d'espace de noms des blocs du socle (#8724). */
    public const BLOCK_NS = 'core.';

    /**
     * Traduit un statut de vitrine en statut de Page.
     *
     * Mapping volontairement direct : `published` → `published`, tout le reste
     * → `draft`. Une vitrine `suspended` (ou tout statut futur) ne doit jamais
     * devenir publiquement visible par accident — en cas de doute, on ferme.
     */
    public static function pageStatus(CompanyShowcaseStatus $status): PageProjectionStatus
    {
        return match ($status) {
            CompanyShowcaseStatus::Published => PageProjectionStatus::Published,
            default => PageProjectionStatus::Draft,
        };
    }

    /**
     * Traduit un type de section Showcase en type de bloc Pages.
     *
     * Renvoie `null` si le type n'a pas d'équivalent : la projection ne doit
     * pas inventer un bloc « inconnu » qui serait stocké sans jamais pouvoir
     * être rendu. L'appelant compte ces sections comme non migrées, ce qui
     * fait échouer le contrôle de parité — volontairement bruyant.
     */
    public static function blockType(ShowcaseSectionType $type): ?string
    {
        $id = self::BLOCK_NS.$type->value;

        return in_array($id, self::coreBlockIds(), true) ? $id : null;
    }

    /**
     * Les sept identifiants de blocs du socle, dans l'ordre canonique.
     *
     * Dérivé de l'enum Showcase plutôt que recopié : si un type de section est
     * ajouté côté Showcase, ce test le rend visible immédiatement au lieu de
     * le laisser disparaître silencieusement du backfill.
     *
     * @return list<string>
     */
    public static function coreBlockIds(): array
    {
        return array_map(
            static fn (ShowcaseSectionType $type): string => self::BLOCK_NS.$type->value,
            ShowcaseSectionType::cases(),
        );
    }

    /**
     * Contenu d'un bloc : recopié à l'identique.
     *
     * Méthode explicite plutôt qu'un simple passage de variable : elle est le
     * point unique où une éventuelle transformation future devra être écrite,
     * et elle documente que l'absence de transformation est un choix.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function blockContent(array $content): array
    {
        return $content;
    }

    /**
     * Horodatage de publication d'une Page issue d'une vitrine.
     *
     * `pages` porte la contrainte `pages_published_requires_date` : une Page
     * publiée SANS `published_at` est rejetée par la base. La vitrine a déjà
     * son `published_at` — le recopier préserve l'ancienneté réelle (le
     * référencement et le tri par date ne doivent pas voir une Page créée
     * « aujourd'hui » alors qu'elle est en ligne depuis un an). Si la vitrine
     * est publiée sans date (donnée historique), on retombe sur l'instant du
     * backfill : refuser la ligne entière serait pire que corriger la date.
     */
    public static function publishedAt(?\DateTimeInterface $showcasePublishedAt): ?\DateTimeInterface
    {
        return $showcasePublishedAt;
    }

    /**
     * Date de publication effective à écrire pour une Page.
     *
     * Une vitrine publiée sans `published_at` existe en données historiques.
     * Laissé tel quel, le backfill échouerait sur la contrainte
     * `pages_published_requires_date` **pour toute la ligne** — donc perdrait
     * aussi le contenu. On retombe explicitement sur l'instant du backfill :
     * la date est alors déclarée « au moment de la reprise », ce qui est
     * vérifiable dans le rapport, plutôt que de refuser la migration.
     */
    public static function effectivePublishedAt(
        PageProjectionStatus $status,
        ?\DateTimeInterface $showcasePublishedAt,
    ): ?\DateTimeInterface {
        if ($status !== PageProjectionStatus::Published) {
            return null;
        }

        return $showcasePublishedAt ?? new \DateTimeImmutable;
    }

    /**
     * Slug de la Page issue d'une vitrine.
     *
     * On conserve le slug de la vitrine : il est déjà unique globalement et
     * déjà indexé publiquement, donc le changer casserait les URL en circulation.
     */
    public static function slug(string $showcaseSlug): string
    {
        return $showcaseSlug;
    }
}
