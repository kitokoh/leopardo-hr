<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pages\Domain\Models\PageVerificationRequest;

/**
 * Autorisation TENANT sur les dossiers de vérification (BC PAGES, #8725).
 *
 * ## Séparation stricte tenant / plateforme
 *
 * Cette policy ne couvre que la moitié **tenant** :
 *
 * - ouvrir un dossier, déposer une pièce, saisir un code SMS, consulter ses
 *   propres pièces — réservés aux rôles de gestion du tenant propriétaire.
 *
 * Elle n'expose **aucune** méthode de décision (`approve`, `reject`,
 * `dispute`, `revoke`). C'est délibéré et non un oubli : un tenant ne peut
 * jamais vérifier sa propre Page, quel que soit son rôle. Ce serait une
 * auto-vérification, donc aucune vérification.
 *
 * Les décisions vivent côté plateforme, où l'acteur n'est pas un `Employee`
 * mais un `SuperAdmin` authentifié par le middleware plateforme (même
 * convention que `PlatformTeamController`). Les mélanger dans une policy unique
 * obligerait à un `instanceof` sur l'acteur — exactement le genre de raccourci
 * qui finit par laisser passer un `Employee` dans une route plateforme.
 *
 * La lecture d'une pièce est autorisée au tenant éditeur (métadonnées), mais
 * l'URL du fichier n'est jamais publique : elle est signée à la demande.
 */
final class PageVerificationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, PageVerificationRequest $request): bool
    {
        return $this->sameTenant($actor, $request);
    }

    /**
     * Ouvrir un dossier : rôle de gestion du tenant propriétaire.
     */
    public function create(Employee $actor, PageVerificationRequest $request): bool
    {
        return $this->sameTenant($actor, $request) && $this->isTenantEditor($actor);
    }

    /**
     * Déposer une pièce ou saisir un code : même périmètre que l'ouverture.
     */
    public function submit(Employee $actor, PageVerificationRequest $request): bool
    {
        return $this->sameTenant($actor, $request) && $this->isTenantEditor($actor);
    }

    /**
     * Lire une pièce justificative de son propre tenant.
     */
    public function viewEvidence(Employee $actor, PageVerificationRequest $request): bool
    {
        return $this->sameTenant($actor, $request) && $this->isTenantEditor($actor);
    }

    /**
     * Décisions : toujours refusées au tenant.
     *
     * Méthodes présentes pour que `Gate::allows('approve', $request)` depuis une
     * route tenant renvoie **false** au lieu de lever une « policy method not
     * defined » (qui, mal gérée, devient un 500 et masque l'intention).
     */
    public function approve(Employee $actor, PageVerificationRequest $request): bool
    {
        return false;
    }

    public function reject(Employee $actor, PageVerificationRequest $request): bool
    {
        return false;
    }

    public function dispute(Employee $actor, PageVerificationRequest $request): bool
    {
        return false;
    }

    public function revoke(Employee $actor, PageVerificationRequest $request): bool
    {
        return false;
    }

    private function sameTenant(Employee $actor, PageVerificationRequest $request): bool
    {
        return (string) $request->company_id === (string) $actor->company_id;
    }

    private function isTenantEditor(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
