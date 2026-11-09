<?php

declare(strict_types=1);

namespace App\Modules\Library\Application\Queries;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryDocumentType;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Liste paginée et filtrée des ressources de la bibliothèque (BC-40 LIBRARY).
 *
 * Surface TENANT (back-office) : la requête part de `LibraryResource::query()`,
 * donc le scope global `BelongsToCompany` s'applique — un tenant ne voit
 * JAMAIS les ressources d'un autre, et le catalogue public Leopardo
 * (`company_id` NULL) n'apparaît pas non plus dans son back-office.
 *
 * Filtres allowlistés (aucun SQL libre, aucun tri fourni par le client) :
 *  - `status`        : draft | pending | published | archived ;
 *  - `access_model`  : free | read_only | purchase | subscription | private ;
 *  - `document_type` : book | ebook | guide | manual | report | article ;
 *  - `language`      : code ISO 639-1 (2 lettres) ;
 *  - `q`             : recherche titre / slug / éditeur / détenteur des droits.
 *
 * Tri fixe : `updated_at DESC, id DESC` — la ressource la plus récemment
 * touchée d'abord, ce qui correspond au travail éditorial en cours.
 */
final class ListLibraryResourcesQuery
{
    /** Taille de page par défaut. */
    private const DEFAULT_PER_PAGE = 20;

    /** Plafond de pagination (protège la base d'une demande `per_page=100000`). */
    private const MAX_PER_PAGE = 100;

    /**
     * @param  array{
     *     status?: string|null,
     *     access_model?: string|null,
     *     document_type?: string|null,
     *     language?: string|null,
     *     q?: string|null,
     *     per_page?: int|null,
     *     page?: int|null,
     * }  $input
     * @return LengthAwarePaginator<int, LibraryResource>
     */
    public function execute(array $input): LengthAwarePaginator
    {
        $query = LibraryResource::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyEnumFilter($query, 'status', $input['status'] ?? null, LibraryResourceStatus::cases());
        $this->applyEnumFilter($query, 'access_model', $input['access_model'] ?? null, LibraryAccessModel::cases());
        $this->applyEnumFilter($query, 'document_type', $input['document_type'] ?? null, LibraryDocumentType::cases());

        $language = $this->clean($input['language'] ?? null);
        if ($language !== null && preg_match('/^[a-z]{2}$/', $language) === 1) {
            $query->where('language', $language);
        }

        $needle = $this->clean($input['q'] ?? null);
        if ($needle !== null) {
            $this->applySearch($query, $needle);
        }

        $perPage = (int) ($input['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, (int) ($input['page'] ?? 1));

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Applique un filtre d'énumération en n'acceptant QUE les valeurs du
     * registre : une valeur inconnue est ignorée (jamais injectée en SQL).
     *
     * @param  Builder<LibraryResource>  $query
     * @param  array<int, \BackedEnum>  $cases
     */
    private function applyEnumFilter(Builder $query, string $column, ?string $value, array $cases): void
    {
        $clean = $this->clean($value);

        if ($clean === null) {
            return;
        }

        $allowed = array_map(
            static fn (\BackedEnum $case): string => (string) $case->value,
            $cases,
        );

        if (in_array($clean, $allowed, true)) {
            $query->where($column, $clean);
        }
    }

    /**
     * Recherche insensible à la casse sur les champs éditoriaux.
     *
     * @param  Builder<LibraryResource>  $query
     */
    private function applySearch(Builder $query, string $needle): void
    {
        $pattern = '%'.$this->escapeLike($needle).'%';
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $query->where(function (Builder $q) use ($pattern): void {
                $q->where('title', 'ILIKE', $pattern)
                    ->orWhere('slug', 'ILIKE', $pattern)
                    ->orWhere('publisher', 'ILIKE', $pattern)
                    ->orWhere('rights_holder', 'ILIKE', $pattern);
            });

            return;
        }

        // Repli portable (SQLite en test, MySQL) : LIKE insensible à la casse.
        $lower = '%'.mb_strtolower($this->escapeLike($needle)).'%';
        $query->where(function (Builder $q) use ($lower): void {
            $q->whereRaw('LOWER(title) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(publisher) LIKE ?', [$lower])
                ->orWhereRaw('LOWER(rights_holder) LIKE ?', [$lower]);
        });
    }

    /** Normalise une valeur de filtre : trim, chaîne vide → null. */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** Échappe les jokers LIKE fournis par l'utilisateur. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
