<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * Port de notification push (BC-24 TRAVEL).
 *
 * Contrat d'isolation inter-contextes (issue #5584) : le module TravelAgency
 * ne dépend JAMAIS de l'implémentation du module Notification (BC-13).
 * L'adaptateur est branché au composition root (provider du module) via le
 * service `PushNotificationService` du module Notification.
 */
interface CompanyPushNotifier
{
    /**
     * Envoie une notification push à un utilisateur ciblé.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int;
}
