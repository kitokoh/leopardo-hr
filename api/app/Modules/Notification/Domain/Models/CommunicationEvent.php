<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alias de compatibilité — la classe canonique est
 * {@see \App\Modules\Communication\Domain\Models\CommunicationEvent}
 * depuis l'ADR 0027 (BOS-025, #8219 : le journal des événements de livraison
 * relève du module Communication, frontière Notification/Communication).
 *
 * La table `communication_events` est inchangée (même nom par convention
 * Eloquent pour les deux FQCN) ; un `instanceof` sur l'ancien FQCN reste vrai
 * pour les instances canoniques.
 *
 * @deprecated Alias conservé 1 release — ne plus importer dans du code
 *             nouveau. Retrait planifié à la release suivante (issue de
 *             suivi référencée dans la PR du lot Z6).
 *
 * @mixin Builder<static>
 */
class CommunicationEvent extends \App\Modules\Communication\Domain\Models\CommunicationEvent
{
    /**
     * Relation historique vers la notification in-app. Jamais lue en
     * production (vérifié sur `main` au 2026-09-28) ; conservée ici — et pas
     * sur la classe canonique — pour préserver la compatibilité de l'alias
     * sans introduire de dépendance Communication → Notification (ADR 0027,
     * règle 3).
     *
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
