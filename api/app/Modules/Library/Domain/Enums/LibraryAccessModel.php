<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Enums;

/**
 * Modèle d'accès d'une ressource de la bibliothèque.
 *
 * - `free`         : lecture ET téléchargement libres ;
 * - `read_only`    : lecture en ligne gratuite, téléchargement désactivé ;
 * - `purchase`     : achat à l'unité (réutilise le paiement Leopardo) ;
 * - `subscription` : inclus dans un abonnement ;
 * - `private`      : réservé à un tenant/organisation (accès vérifié serveur).
 */
enum LibraryAccessModel: string
{
    case Free = 'free';
    case ReadOnly = 'read_only';
    case Purchase = 'purchase';
    case Subscription = 'subscription';
    case Private = 'private';

    /** Vrai si la ressource est lisible sans compte. */
    public function isPubliclyReadable(): bool
    {
        return $this === self::Free || $this === self::ReadOnly;
    }
}
