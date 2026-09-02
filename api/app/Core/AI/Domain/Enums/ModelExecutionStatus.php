<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Enums;

/**
 * Statut d'exécution d'un appel d'inférence (AI-001, #6770).
 *
 * Neutre vis-à-vis des fournisseurs : un adaptateur traduit la réponse
 * propriétaire (ou l'absence de réponse) vers ces statuts ; le domaine ne
 * connaît ni le fournisseur, ni ses exceptions, ni ses codes d'erreur.
 */
enum ModelExecutionStatus: string
{
    /** Inférence terminée et exploitable (payload validé). */
    case Succeeded = 'succeeded';

    /** Inférence terminée : rejet métier (visage inconnu, lecture refusée...). */
    case Rejected = 'rejected';

    /** Fournisseur non configuré ou injoignable (panne, quota, réseau). */
    case Unavailable = 'unavailable';

    /** Délai dépassé (timeout) — l'appelant peut réessayer ou basculer. */
    case Timeout = 'timeout';

    /** Entrée invalide (image illisible, type de fichier refusé, taille...). */
    case InvalidInput = 'invalid_input';

    public function isUsable(): bool
    {
        return $this === self::Succeeded;
    }
}
