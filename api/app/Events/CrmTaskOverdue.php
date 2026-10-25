<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Issue #5720 — Tâche CRM en retard (relance interne).
 *
 * Événement à payload scalaire (aucun modèle CRM dans l'événement) pour
 * permettre au module Notification de créer la notification sans import
 * inter-module (règle ARCHITECTURE.md : modules ↔ événements/contrats).
 *
 * @property string $companyId
 * @property int $taskId
 * @property int|null $assigneeId
 * @property string $title
 * @property string|null $dueAtIso
 */
class CrmTaskOverdue
{
    use Dispatchable;

    public function __construct(
        // #8930 — `companies.id` est un UUID : le cast `(int)` du côté
        // émetteur ramenait l'identifiant à 0 et la notification partait vers
        // un tenant inexistant. Le payload reste scalaire (règle
        // ARCHITECTURE.md) mais typé correctement.
        public readonly string $companyId,
        public readonly int $taskId,
        public readonly ?int $assigneeId,
        public readonly string $title,
        public readonly ?string $dueAtIso = null,
    ) {}
}
