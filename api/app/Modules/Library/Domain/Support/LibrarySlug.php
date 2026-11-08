<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Règle de domaine « slug unique par tenant » pour la bibliothèque.
 *
 * Une collision de slug est résolue par un suffixe numérique (`-2`, `-3`, …).
 * L'id en cours d'édition est ignoré (une mise à jour sans changement de slug
 * ne doit pas entrer en collision avec elle-même).
 */
final class LibrarySlug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function uniqueFor(string $model, ?string $companyId, string $slug, int $ignoreId = 0): string
    {
        $candidate = $slug;
        $suffix = 2;

        while ($model::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->where('id', '!=', $ignoreId)
            ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
