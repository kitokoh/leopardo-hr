<?php

declare(strict_types=1);

namespace App\Contracts\Communication;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Contrat partagé d'envoi de notifications (BC-13 Notification).
 *
 * Permet aux modules consommateurs (ex. RestaurantManager, BC-25) d'utiliser
 * CommunicationService sans import croisé Modules/X → Modules/Notification
 * (règle d'isolation des modules, issue #5584) : le câblage concret est fait
 * par le service provider du module Notification.
 */
interface CommunicationServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>|null  $channels
     * @return array<string, mixed>
     */
    public function notifyEmployee(Employee $employee, string $templateKey, array $context = [], ?array $channels = null): array;
}
