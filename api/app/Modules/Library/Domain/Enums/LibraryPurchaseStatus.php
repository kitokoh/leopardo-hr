<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Enums;

/**
 * Cycle de vie d'une vente de la bibliothèque (BC-40 / BC-38, #9238).
 *
 * `Pending` est l'état d'une intention d'achat : elle ne donne AUCUN droit.
 * Seul `Paid` ouvre l'accès, et il n'est atteint que par
 * `LibraryPurchaseService::confirm()` après vérification du paiement côté
 * serveur. `Refunded` ferme l'accès (bien numérique remboursé).
 */
enum LibraryPurchaseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** Vrai si cette vente ouvre le droit de lecture/téléchargement. */
    public function grantsAccess(): bool
    {
        return $this === self::Paid;
    }

    /** Vrai si la vente est encore en attente de paiement. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
