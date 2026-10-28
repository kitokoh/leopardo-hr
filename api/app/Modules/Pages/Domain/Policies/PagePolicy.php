<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pages\Domain\Models\Page;

/**
 * Autorisation d'édition d'une Page (BC PAGES, #8723).
 *
 * ## Ce que cette policy décide — et ce qu'elle ne décide PAS
 *
 * Elle décide qui peut **éditer / publier / dépublier** une Page : un employé
 * du tenant propriétaire, avec un rôle de gestion. Elle ne décide jamais de la
 * **vérification** : celle-ci appartient à la plateforme
 * (`PageVerificationPolicy`, #8725/#8729) parce qu'elle constate un fait
 * extérieur au tenant (le lieu existe et il lui appartient). C'est exactement
 * la séparation « compte tenant ≠ droit d'édition ≠ preuve d'exploitation » de
 * §5 du design.
 *
 * Calquée sur `CatalogCategoryPolicy` / `CameraAlertPolicy` : acteur `Employee`,
 * `hasManagerRole(...)` pour les sous-rôles, `company_id` comparé en chaîne
 * (l'identifiant company est un uuid, la comparaison stricte sur types
 * hétérogènes est un piège classique), deny-by-default.
 *
 * Enregistrement unique : `App\Providers\AuthServiceProvider` (garde
 * `check-policies-single-point.sh`).
 */
final class PagePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    /**
     * Créer la Page d'un établissement (#8728).
     *
     * Réservé aux rôles de gestion : créer une Page, c'est ouvrir une prise de
     * parole publique de la société. Un collaborateur qui n'a pas le droit
     * d'éditer ne doit pas pouvoir créer la surface qu'il ne pourra pas
     * corriger.
     */
    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function view(Employee $actor, Page $page): bool
    {
        return $this->sameTenant($actor, $page);
    }

    /**
     * Éditer le contenu (blocs, thème, SEO, locales).
     *
     * Réservé aux rôles de gestion d'établissement : le contenu d'une Page est
     * une prise de parole publique de la société.
     */
    public function update(Employee $actor, Page $page): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $this->sameTenant($actor, $page);
    }

    /**
     * Publier / dépublier : geste visible publiquement.
     *
     * Un `manager` d'établissement peut le faire (il pilote l'ouverture de son
     * lieu) ; la vérification, elle, reste hors de sa portée.
     */
    public function publish(Employee $actor, Page $page): bool
    {
        return $this->sameTenant($actor, $page)
            && ($actor->isManager() || $actor->hasManagerRole('principal', 'rh'));
    }

    /**
     * Demander une vérification de propriété (le dossier est instruit par la
     * plateforme — #8725).
     */
    public function requestVerification(Employee $actor, Page $page): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $this->sameTenant($actor, $page);
    }

    private function sameTenant(Employee $actor, Page $page): bool
    {
        return (string) $page->company_id === (string) $actor->company_id;
    }
}
