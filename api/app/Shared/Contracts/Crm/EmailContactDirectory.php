<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Crm;

use DateTimeInterface;

/**
 * Contrat partagé de liaison email ↔ contacts CRM (BC-11 CRM).
 *
 * Permet au module Communication (BC-29, R3 #7688) de rattacher les
 * expéditeurs de messages aux `crm_contacts` et d'alimenter la timeline CRM
 * SANS import croisé `Modules/Communication -> Modules/CRM` (règle
 * d'isolation #5584) : le consommateur ne dépend que de ce contrat,
 * implémenté par `CRM\Infrastructure\Services\CrmEmailContactDirectory` et
 * bindé par `CrmServiceProvider` (pattern `Shared\Contracts\Catalog`).
 * L'arête BC-29 → BC-11 est déclarée au registre des bounded contexts.
 *
 * Surface volontairement MINIMALE : identifiant du contact, création
 * explicite (jamais silencieuse — appelée uniquement à l'ACCEPTATION d'une
 * proposition par l'utilisateur, §3.3 de la spec) et écriture d'activité
 * timeline. Aucune donnée interne CRM ne transite.
 */
interface EmailContactDirectory
{
    /**
     * Id du contact CRM (non archivé) du tenant dont l'email correspond
     * (insensible à la casse), ou null si l'expéditeur est inconnu.
     */
    public function findContactIdByEmail(string $companyId, string $email): ?int;

    /**
     * Crée un contact CRM pour le tenant — UNIQUEMENT sur action explicite
     * de l'utilisateur (acceptation d'une proposition, jamais silencieux).
     *
     * @return int id du contact créé
     */
    public function createContact(string $companyId, string $email, ?string $suggestedName): int;

    /**
     * Journalise une activité `email` dans la timeline CRM du contact
     * (append-only, #5710).
     */
    public function recordEmailActivity(
        string $companyId,
        int $contactId,
        ?string $subject,
        DateTimeInterface $occurredAt,
    ): void;
}
