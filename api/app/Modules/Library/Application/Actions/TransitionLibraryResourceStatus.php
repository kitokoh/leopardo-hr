<?php

declare(strict_types=1);

namespace App\Modules\Library\Application\Actions;

use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Infrastructure\Services\LibraryPublicCache;

/**
 * Action de workflow éditorial de la bibliothèque (BC-40 LIBRARY).
 *
 * Applique une transition de statut à une ressource : brouillon → en attente →
 * publié → archivé. Aucune publication automatique : la transition est toujours
 * déclenchée explicitement par un acteur autorisé (policy `LibraryResourcePolicy`).
 *
 * La logique de transition vit ici (couche Application) et non dans le
 * contrôleur, conformément à la convention du dépôt (1 action = 1 execute()).
 *
 * Le catalogue public Leopardo (`company_id` NULL) est mis en cache : toute
 * transition d'une ressource GLOBALE invalide le snapshot public. Les ressources
 * d'un tenant privé n'y figurent pas et n'invalident donc rien.
 */
final class TransitionLibraryResourceStatus
{
    public function execute(
        LibraryResource $resource,
        LibraryResourceStatus $status,
        ?int $actorId = null,
    ): LibraryResource {
        $attributes = ['status' => $status->value];

        if ($status === LibraryResourceStatus::Published) {
            $attributes['published_by'] = $actorId;
            $attributes['published_at'] = now();
        }

        $resource->update($attributes);

        if ($resource->company_id === null) {
            LibraryPublicCache::forget();
        }

        return $resource->refresh();
    }
}
