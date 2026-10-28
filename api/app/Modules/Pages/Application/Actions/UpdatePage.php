<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Exceptions\PageSlugException;
use App\Modules\Pages\Domain\Models\Page;

/**
 * Met à jour le contenu éditable d'une Page (BC PAGES, #8728).
 *
 * ## Ce qui est éditable ici, et ce qui ne l'est pas
 *
 * Éditables : `theme`, `settings`, `legal`, `seo`, `locales`, `default_locale`.
 * **Non éditables** : `status` (passe par `PublishPage`/`unpublish`),
 * `verification_status` (appartient à la plateforme, #8725/#8729),
 * `suspended_at`/`suspension_reason` (modération, #8729), `site_id` (une Page
 * ne change pas d'établissement : ce serait une autre Page).
 *
 * Cette séparation n'est pas de la prudence : si un PUT pouvait écrire
 * `verification_status`, un tenant pourrait se déclarer vérifié lui-même, ce
 * qui annulerait toute la valeur de la vérification.
 *
 * ## Le slug peut changer, mais reste globalement unique
 *
 * Un changement de slug casse les liens existants. On l'autorise (un
 * établissement peut se renommer) mais on refuse un slug déjà pris, réservé ou
 * inutilisable — avec un code d'erreur distinct pour chaque cas.
 */
final class UpdatePage
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PageSlugException
     */
    public function execute(Page $page, array $payload): Page
    {
        if (array_key_exists('slug', $payload)) {
            $slug = Page::normalizeSlug((string) $payload['slug']);

            if ($slug === null) {
                throw PageSlugException::unusable();
            }

            if (Page::isReservedSlug($slug)) {
                throw PageSlugException::reserved($slug);
            }

            if ($slug !== $page->slug) {
                $taken = Page::query()
                    ->where('slug', $slug)
                    ->whereKeyNot($page->getKey())
                    ->exists();

                if ($taken) {
                    throw PageSlugException::alreadyTaken($slug);
                }

                $page->slug = $slug;
            }
        }

        foreach (['theme', 'settings', 'legal', 'seo', 'locales', 'default_locale'] as $field) {
            if (array_key_exists($field, $payload)) {
                $page->{$field} = $payload[$field];
            }
        }

        $page->save();

        return $page;
    }
}
