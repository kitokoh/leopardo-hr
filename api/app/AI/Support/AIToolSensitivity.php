<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * Sensibilité d'un outil d'assistant — issue #6850 (BC-23, EPIC #6846).
 *
 * Déclarée par le BC propriétaire de l'outil (jamais décidée par l'hôte) :
 * - read  : lecture/information → exécution directe après vérification RBAC ;
 * - write : modification de données métier → confirmation humaine requise ;
 * - send  : effet externe (notification, email…) → confirmation humaine requise.
 */
enum AIToolSensitivity: string
{
    case Read = 'read';
    case Write = 'write';
    case Send = 'send';
}
