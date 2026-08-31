<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * TRAVEL-416 (#6068) — résolution d'un contact client (contrat, spec §8.5).
 *
 * La verticale ne connaît que `customer_contact_id` (référence de contrat).
 * Ce resolver — implémenté par le BC CRM client (jamais d'import direct) —
 * retourne les coordonnées de notification du voyageur. Aucune écriture dans
 * les tables CRM depuis la verticale ; les données retournées ne sont pas
 * persistées côté TravelAgency (usage éphémère pour la notification).
 */
interface TravelCustomerContactResolver
{
    /**
     * @return array{email: string|null, phone: string|null, full_name: string|null}|null
     */
    public function resolve(string $companyId, string $contactReference): ?array;
}
