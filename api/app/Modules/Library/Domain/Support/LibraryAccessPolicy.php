<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Support;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Models\LibraryResource;

/**
 * Politique d'accès de la bibliothèque — VÉRIFICATION CÔTÉ SERVEUR.
 *
 * C'est ICI que le droit de lecture/téléchargement est décidé, jamais dans
 * l'interface. Le contexte d'accès (tenant, achat, abonnement) est fourni par
 * l'appelant, qui l'a établi à partir de la session authentifiée.
 *
 * Règles :
 * - `free`         : lecture + téléchargement (si la licence l'autorise) ;
 * - `read_only`    : lecture seule, téléchargement désactivé ;
 * - `purchase`     : lecture/téléchargement après achat ;
 * - `subscription` : lecture/téléchargement si abonnement actif ;
 * - `private`      : visible et lisible uniquement par le tenant propriétaire.
 */
final class LibraryAccessPolicy
{
    /**
     * @param  string|null  $viewerTenantId  Tenant du visiteur (null = anonyme).
     * @param  bool  $purchased  La ressource a-t-elle été achetée ?
     * @param  bool  $subscribed  Le visiteur a-t-il un abonnement actif ?
     */
    public function __construct(
        private readonly ?string $viewerTenantId = null,
        private readonly bool $purchased = false,
        private readonly bool $subscribed = false,
    ) {}

    public function canRead(LibraryResource $resource): bool
    {
        return match ($resource->access_model) {
            LibraryAccessModel::Free, LibraryAccessModel::ReadOnly => true,
            LibraryAccessModel::Purchase => $this->purchased,
            LibraryAccessModel::Subscription => $this->subscribed,
            LibraryAccessModel::Private => $this->ownsTenant($resource),
        };
    }

    public function canDownload(LibraryResource $resource): bool
    {
        if (! $resource->allow_download) {
            return false;
        }

        return $this->canRead($resource);
    }

    public function isVisible(LibraryResource $resource): bool
    {
        if (! $resource->isPubliclyVisible()) {
            return false;
        }

        if ($resource->access_model === LibraryAccessModel::Private) {
            return $this->ownsTenant($resource);
        }

        return true;
    }

    private function ownsTenant(LibraryResource $resource): bool
    {
        return $this->viewerTenantId !== null
            && $resource->company_id !== null
            && $this->viewerTenantId === (string) $resource->company_id;
    }
}
