<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * #5714 — Cycle de vie d'une session d'import CSV CRM.
 *
 * previewed   → l'upload a été parsé et validé structurellement, AUCUNE
 *               écriture cible n'a eu lieu (preview sans écriture).
 * committing  → le commit a été demandé (claim atomique anti double-commit).
 * committed   → toutes les lignes valides ont été persistées.
 * failed      → le commit a échoué (erreur fatale) ; un re-commit est possible.
 * cancelled   → l'utilisateur a annulé avant le commit.
 */
enum CrmImportStatus: string
{
    case Previewed = 'previewed';
    case Committing = 'committing';
    case Committed = 'committed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Statuts depuis lesquels un commit explicite est accepté (idempotence :
     * une session déjà committed/cancelled refuse tout nouveau commit).
     *
     * @return list<string>
     */
    public static function committableStatuses(): array
    {
        return [self::Previewed->value, self::Failed->value];
    }
}
