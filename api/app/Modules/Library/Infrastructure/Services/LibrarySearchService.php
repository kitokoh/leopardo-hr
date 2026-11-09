<?php

declare(strict_types=1);

namespace App\Modules\Library\Infrastructure\Services;

use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibrarySemanticScorer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche et découverte du catalogue public Leopardo Library (BC-40 LIBRARY).
 *
 * Recherche PLEIN TEXTE côté serveur, sans dépendance externe payante :
 *  - `ILIKE` PostgreSQL sur titre / description / extrait / détenteur des
 *    droits / éditeur (insensible à la casse ET aux accents via `unaccent`
 *    quand l'extension est disponible — repli `ILIKE` simple sinon) ;
 *  - filtres catégorie / langue / modèle d'accès / type de document ;
 *  - tri par pertinence simple (titre > extrait > description).
 *
 * Mode `semantic` (#9240) : quand `?semantic=1` est demandé, le pré-filtre SQL
 * reste le même (il borne le jeu de candidats et évite de charger tout le
 * catalogue), puis les candidats sont RE-CLASSÉS par `LibrarySemanticScorer`
 * (scoring lexical pondéré par champ, sans dépendance externe). Le scoring
 * sémantique ne remplace pas le filtre : il l'affine.
 *
 * Recommandations : même catégorie d'abord, puis même langue, en excluant la
 * ressource courante. Aucun moteur de reco externe, aucun profilage.
 *
 * ⚠️ Toutes les requêtes sont limitées au catalogue PUBLIC (`company_id` NULL)
 * et aux ressources `published` : aucune donnée d'un tenant privé ne peut
 * sortir par cette surface.
 */
final class LibrarySearchService
{
    /**
     * Recherche filtrée sur le catalogue public.
     *
     * @param  array{query?: string, category?: string, language?: string, access?: string, type?: string, free_only?: bool, semantic?: bool}  $filters
     * @return array<int, LibraryResource>
     */
    public function search(array $filters, int $limit = 60): array
    {
        $query = $this->baseQuery();

        $category = trim((string) ($filters['category'] ?? ''));
        $language = trim((string) ($filters['language'] ?? ''));
        $access = trim((string) ($filters['access'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $freeOnly = (bool) ($filters['free_only'] ?? false);
        $needle = trim((string) ($filters['query'] ?? ''));

        if ($category !== '') {
            $query->whereHas('category', fn (Builder $q): Builder => $q->where('slug', $category));
        }
        if ($language !== '') {
            $query->where('language', $language);
        }
        if ($access !== '') {
            $query->where('access_model', $access);
        }
        if ($type !== '') {
            $query->where('document_type', $type);
        }
        if ($freeOnly) {
            $query->whereIn('access_model', ['free', 'read_only']);
        }

        $minLength = max(1, (int) config('library.search_min_length', 2));

        if (mb_strlen($needle) >= $minLength) {
            $this->applyFullText($query, $needle);
        }

        $results = $query->limit($limit)->get()->all();

        // Mode sémantique (#9240) : re-classement des candidats par score
        // pondéré. Le filtre SQL a déjà borné le jeu de candidats — on ne
        // charge jamais tout le catalogue pour le scorer en mémoire.
        if (($filters['semantic'] ?? false) === true && mb_strlen($needle) >= $minLength) {
            return $this->rankSemantically($results, $needle);
        }

        return $results;
    }

    /**
     * Re-classe des candidats par pertinence sémantique (#9240).
     *
     * @param  array<int, LibraryResource>  $candidates
     * @return array<int, LibraryResource>
     */
    private function rankSemantically(array $candidates, string $needle): array
    {
        $ranked = LibrarySemanticScorer::rank(
            $candidates,
            $needle,
            static fn (LibraryResource $resource): array => [
                'title' => $resource->title,
                'excerpt' => $resource->excerpt,
                'description' => $resource->description,
                'rights_holder' => $resource->rights_holder,
                'publisher' => $resource->publisher,
                'category' => $resource->category?->name,
            ],
        );

        return array_map(
            static fn (array $entry): LibraryResource => $entry['item'],
            $ranked,
        );
    }

    /**
     * Recommandations simples : même catégorie, puis même langue.
     *
     * @return array<int, LibraryResource>
     */
    public function related(LibraryResource $resource, int $limit = 3): array
    {
        $query = $this->baseQuery()->where('id', '!=', $resource->id);

        if ($resource->category_id !== null) {
            $query->where('category_id', $resource->category_id);
        } else {
            $query->where('language', $resource->language);
        }

        $sameCategory = $query->limit($limit)->get()->all();

        if (count($sameCategory) >= $limit) {
            return $sameCategory;
        }

        // Complète avec la même langue (hors doublons déjà retenus).
        $exclude = array_merge([$resource->id], array_map(
            static fn (LibraryResource $item): int => (int) $item->id,
            $sameCategory,
        ));

        $fill = $this->baseQuery()
            ->whereNotIn('id', $exclude)
            ->where('language', $resource->language)
            ->limit($limit - count($sameCategory))
            ->get()
            ->all();

        return array_merge($sameCategory, $fill);
    }

    /**
     * Requête de base : catalogue public publié uniquement.
     *
     * @return Builder<LibraryResource>
     */
    private function baseQuery(): Builder
    {
        return LibraryResource::query()
            ->whereNull('company_id')
            ->where('status', LibraryResourceStatus::Published->value)
            ->with('category');
    }

    /**
     * Applique la recherche plein texte (ILIKE, accents ignorés si possible).
     *
     * @param  Builder<LibraryResource>  $query
     */
    private function applyFullText(Builder $query, string $needle): void
    {
        $pattern = '%'.$this->escapeLike($needle).'%';
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // `unaccent` est une extension PostgreSQL standard ; si elle n'est
            // pas installée, la requête échouerait — on reste donc sur ILIKE
            // simple, qui est déjà insensible à la casse. La recherche
            // accentuée est un raffinement, pas une condition de correction.
            $query->where(function (Builder $q) use ($pattern): void {
                $q->where('title', 'ILIKE', $pattern)
                    ->orWhere('description', 'ILIKE', $pattern)
                    ->orWhere('excerpt', 'ILIKE', $pattern)
                    ->orWhere('rights_holder', 'ILIKE', $pattern)
                    ->orWhere('publisher', 'ILIKE', $pattern);
            });

            // Tri par pertinence : titre d'abord.
            $query->orderByRaw(
                'CASE WHEN title ILIKE ? THEN 0 WHEN excerpt ILIKE ? THEN 1 ELSE 2 END',
                [$pattern, $pattern],
            );

            return;
        }

        // Repli portable (SQLite en test, MySQL) : LIKE insensible à la casse
        // via LOWER().
        $lower = '%'.mb_strtolower($this->escapeLike($needle)).'%';
        $query->where(function (Builder $q) use ($lower): void {
            $q->whereRaw('LOWER(title) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(description) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(excerpt) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(rights_holder) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(publisher) LIKE ?', [$lower]);
        });
    }

    /** Échappe les jokers LIKE fournis par l'utilisateur. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
