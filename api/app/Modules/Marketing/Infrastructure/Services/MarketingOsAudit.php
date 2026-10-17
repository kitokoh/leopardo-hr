<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Marketing\Domain\Models\ContentIdea;
use App\Modules\Marketing\Domain\Models\MarketingAsset;
use App\Modules\Marketing\Domain\Models\MarketingPublication;
use Illuminate\Database\Eloquent\Model;

/**
 * Marketing OS — piste d'audit des opérations sensibles (brief §J).
 *
 * Le module Marketing n'écrivait jusqu'ici AUCUNE ligne d'audit : une
 * modification de statut, une confirmation de mise en ligne ou un échec de
 * publication ne laissaient aucune trace exploitable en support. Le brief
 * exige pourtant que « les opérations sensibles soient journalisées ».
 *
 * Ce service réutilise l'infrastructure EXISTANTE (`audit_logs` +
 * `AuditLog::record()`, #5439) plutôt que d'introduire une table parallèle :
 * une seule piste d'audit, interrogeable avec les mêmes filtres que le reste
 * de la plateforme (`scopeForCompany`, `scopeForModule`, `scopeForModel`).
 *
 * ## Ce qui est journalisé
 *
 * Seules les opérations qui ENGAGENT quelque chose sont tracées — pas les
 * lectures :
 *
 *   - `marketing.publication.published`  : passage à `published` (preuve URL) ;
 *   - `marketing.publication.failed`     : passage à `failed` (motif conservé) ;
 *   - `marketing.publication.status_changed` : toute autre transition réelle ;
 *   - `marketing.idea.promoted`          : idée transformée en tâche ;
 *   - `marketing.asset.created`          : ajout à la médiathèque.
 *
 * ## Ce qui n'est PAS journalisé, volontairement
 *
 * Une transition **idempotente** (le statut demandé est déjà le statut courant)
 * n'écrit RIEN : rejouer une requête ne doit pas polluer le journal ni
 * fabriquer un historique d'événements qui n'ont pas eu lieu.
 */
class MarketingOsAudit
{
    /** Module inscrit dans la colonne `audit_logs.module`. */
    public const MODULE = 'marketing';

    /**
     * Transition de statut d'une publication.
     *
     * @param  string  $action  Action d'audit (`marketing.publication.*`).
     * @param  string  $previousStatus  Statut avant la transition.
     * @param  array<string, mixed>  $metadata  Contexte additionnel.
     */
    public function publicationTransition(
        MarketingPublication $publication,
        string $action,
        string $previousStatus,
        ?string $reason = null,
        array $metadata = [],
    ): AuditLog {
        $newValues = [
            'status' => $publication->status->value,
            'mode' => $publication->mode->value,
            'platform' => $publication->platform,
            'published_url' => $publication->published_url,
            'scheduled_at' => $publication->scheduled_at?->toIso8601String(),
        ];

        if ($reason !== null) {
            $newValues['error_message'] = $reason;
        }

        return AuditLog::record(
            module: self::MODULE,
            action: $action,
            subject: $publication,
            oldValues: ['status' => $previousStatus],
            newValues: $newValues,
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function ideaPromoted(ContentIdea $idea, array $metadata = []): AuditLog
    {
        return AuditLog::record(
            module: self::MODULE,
            action: 'marketing.idea.promoted',
            subject: $idea,
            newValues: [
                'status' => $idea->status->value,
                'production_task_id' => $idea->production_task_id,
            ],
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function assetCreated(MarketingAsset $asset, array $metadata = []): AuditLog
    {
        return AuditLog::record(
            module: self::MODULE,
            action: 'marketing.asset.created',
            subject: $asset,
            newValues: [
                'name' => $asset->name,
                'kind' => $asset->kind->value,
                'version' => $asset->version,
                'validation_status' => $asset->validation_status->value,
                'has_file' => $asset->hasFile(),
            ],
            metadata: $metadata,
        );
    }

    /**
     * Écrit une entrée d'audit « brute » pour un sujet quelconque du module.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): AuditLog {
        return AuditLog::record(
            module: self::MODULE,
            action: $action,
            subject: $subject,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        );
    }
}
