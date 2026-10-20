<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Statut d'un travail d'impression thermique (REST-OS-1/REST-OS-2, Mission
 * Leopardo Restaurant OS).
 *
 * Cycle de vie volontairement conservateur : un travail n'est déclaré
 * `printed` QUE sur accusé explicite du bridge d'impression local (`ack`),
 * jamais parce que l'API a émis une requête. `failed` conserve le motif ;
 * `cancelled` est terminal (ticket remplacé par une révision supérieure).
 */
enum PrintJobStatus: string
{
    /** En file : le bridge local le récupérera (ou reste à réimprimer). */
    case PENDING = 'pending';

    /** Récupéré par le bridge local, en cours d'envoi à l'imprimante. */
    case CLAIMED = 'claimed';

    /** Acquitté : le bridge a confirmé la remise au matériel d'impression. */
    case PRINTED = 'printed';

    /** Échec d'impression (papier, déconnexion, driver) — réessayable. */
    case FAILED = 'failed';

    /** Annulé (obsolète : révision remplacée, commande annulée). */
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CLAIMED => 'En cours',
            self::PRINTED => 'Imprimé',
            self::FAILED => 'Échec',
            self::CANCELLED => 'Annulé',
        };
    }

    /** Un travail terminal ne peut plus être ré-acquitté. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PRINTED, self::CANCELLED], true);
    }

    /** Indique si le travail doit repartir dans la file du bridge local. */
    public function isClaimable(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED], true);
    }
}
