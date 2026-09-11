<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

/**
 * Contrat partagé d'envoi de notification push (BC-13 COMMS).
 *
 * Permet aux modules métier (ex. BC-24 TRAVEL) d'envoyer un push mobile à un
 * utilisateur du tenant SANS import croisé `Modules/X -> Modules/Notification`
 * (règle d'isolation #5584) — ils ne dépendent que de ce contrat, implémenté
 * par `Notification\Infrastructure\Services\PushNotificationService`.
 *
 * Best-effort côté appelant : un échec d'envoi ne doit jamais casser le
 * traitement métier (l'appelant décide du try/catch, comme documenté dans les
 * consommateurs d'outbox).
 */
interface PushNotifier
{
    /**
     * Envoie une notification push aux appareils d'un employé du tenant.
     *
     * @param  array<string, mixed>  $data  données additionnelles (module, référence…)
     * @return int nombre d'appareils notifiés
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int;
}
