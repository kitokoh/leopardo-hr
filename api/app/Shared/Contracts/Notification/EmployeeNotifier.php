<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Contrat partagé de notification employé (BC-13 COMMS).
 *
 * Permet aux modules métier (ex. BC-24 TRAVEL) d'envoyer une notification
 * in-app/push via les canaux de la plateforme SANS import croisé
 * `Modules/X -> Modules/Notification` (règle d'isolation #5584) — ils ne
 * dépendent que de ce contrat, implémenté par
 * `Notification\Infrastructure\Services\CommunicationService`.
 *
 * Le canal de livraison respecte toujours les préférences du destinataire
 * et le consentement (jamais de contournement).
 */
interface EmployeeNotifier
{
    /**
     * Notifie un employé selon ses préférences (canaux app/push par défaut).
     *
     * @param  array<string, mixed>  $context  (title, body, category, metadata…)
     * @param  list<string>|null  $channels  canaux demandés (null = défaut config)
     * @return array<string, mixed> résultat de l'envoi par canal
     */
    public function notifyEmployee(Employee $employee, string $templateKey, array $context = [], ?array $channels = null): array;
}
