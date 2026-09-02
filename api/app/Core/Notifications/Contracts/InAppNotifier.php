<?php

declare(strict_types=1);

namespace App\Core\Notifications\Contracts;

/**
 * Contrat transversal de notification in-app (tenant).
 *
 * Implémenté par le module Notification (BC-13 COMMS) ; consommé par les
 * modules métier SANS import cross-module (garde #5584) : un module qui
 * notifie type-hinte ce contrat Core et laisse le conteneur injecter
 * l'implémentation enregistrée.
 *
 * Les notifications sont best-effort : un échec d'envoi ne doit jamais
 * faire échouer le flux métier appelant.
 */
interface InAppNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
    ): void;
}
