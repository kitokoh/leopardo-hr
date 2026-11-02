<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Discovery;

/**
 * COULOIR 16 — M3 (#9105) · Contrat de LECTURE d'une activité publique.
 *
 * ## Pourquoi ce contrat existe
 *
 * `public_activity_index` (schéma `public`) ne contient que des RÉFÉRENCES :
 * `(user_id, company_id, vertical, activity_type, external_ref, status)`. Elle
 * dit *où* chercher, jamais *ce qu'il y a*. Pour afficher « votre commande
 * RST-123 est en préparation », il faut ouvrir le schéma du tenant et relire la
 * ligne réelle — c'est le seul moyen d'avoir un statut qui ne mente pas.
 *
 * Ce contrat est le point d'entrée de cette relecture. Chaque verticale qui
 * publie des activités fournit son implémentation et la tague
 * `public.activity_provider` depuis son propre ServiceProvider. Le service
 * applicatif du couloir 16 n'importe donc AUCUNE classe `App\Modules\*` —
 * même inversion de dépendance que `PublicDiscoverableProvider` et
 * `PublicProfileResolver` (garde d'isolation #5584).
 *
 * ## Invariants non négociables
 *
 *  - **fail-closed** : une activité qui n'appartient pas au client, ou dont le
 *    tenant n'est plus joignable, renvoie `null`. Jamais une exception qui
 *    distinguerait « inexistante » de « pas à vous » — ce serait un oracle
 *    d'énumération.
 *  - **aucun contenu métier** : le résultat ne transporte que ce qu'un client
 *    doit voir de SA propre activité (référence, statut, date, libellé du
 *    commerce). Aucun prix d'achat, aucune marge, aucun nom d'employé, aucun
 *    identifiant interne de tenant.
 *  - **le statut est relu ici**, pas recopié depuis l'index : l'index est une
 *    projection qui peut être en retard d'un événement.
 */
interface PublicActivityProvider
{
    /**
     * Verticale servie (`restaurant`, `retail`, …) — même vocabulaire que
     * `PublicDiscoverableProvider::type()`.
     */
    public function vertical(): string;

    /**
     * Types d'activité servis (`order`, `booking`).
     *
     * Un module qui ne déclare pas un type ne l'affiche pas : c'est ce qui
     * garantit « un module sans contrat n'apparaît pas dans l'onglet ».
     *
     * @return list<string>
     */
    public function activityTypes(): array;

    /**
     * Relit une activité DANS le contexte du tenant, pour ce client précis.
     *
     * @param  string  $activityType  `order` | `booking`
     * @param  string  $externalRef  référence dans le schéma du tenant
     * @param  int  $userId  `users.id` du client authentifié
     * @return array{reference: string, status: string, occurred_at: string, title: string}|null
     *                                                                                           `null` si l'activité n'existe pas, n'appartient pas à ce client, ou n'est plus joignable.
     */
    public function findForClient(string $activityType, string $externalRef, int $userId): ?array;
}
