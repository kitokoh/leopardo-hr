<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains\Dns;

/**
 * Lecture DNS, isolée derrière une interface pour être testable.
 *
 * ## Pourquoi ne pas appeler `dns_get_record` directement dans le service
 *
 * Deux raisons concrètes :
 *
 * 1. **Les tests** — une suite qui interroge le DNS réel est lente,
 *    intermittente et dépend du réseau de la CI. C'est le meilleur moyen
 *    d'obtenir une CI rouge sans qu'aucun code n'ait changé.
 * 2. **Le temps** — `dns_get_record` n'a pas de timeout de résolution par
 *    requête (il délègue à `res_query`, dont le timeout dépasse largement les
 *    10 s d'un budget HTTP). Une interface permet de borner et de journaliser.
 *
 * L'implémentation réelle reste volontairement simple : elle lit, elle ne
 * décide pas. Toute la logique de décision (TXT attendu, CNAME, apex…) vit
 * dans le service, donc elle est testable sans DNS.
 */
interface DnsResolver
{
    /**
     * Valeurs TXT du host, aplaties (un record TXT peut être découpé en
     * plusieurs chaînes de 255 octets : on les concatène).
     *
     * @return list<string>
     */
    public function txt(string $host): array;

    /**
     * Cibles CNAME du host, sans point final.
     *
     * @return list<string>
     */
    public function cname(string $host): array;

    /**
     * Le host résout-il vers au moins une adresse (A ou AAAA) ?
     */
    public function resolves(string $host): bool;
}
