<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Discovery;

/**
 * SUPERAPP-P1 (#8760) — Contrat de RÉSOLUTION d'un profil public par slug.
 *
 * La découverte (`PublicDiscoverableProvider::search`) répond à « qu'est-ce qui
 * est cherchable ? ». Le contact répond à « ce slug précis est-il joignable
 * MAINTENANT, et par quel tenant ? » — deux questions distinctes qu'il ne faut
 * pas confondre : un slug peut exister dans une table tout en étant
 * dépublié/suspendu, et un slug inexistant ne doit jamais révéler son absence
 * côté tenant.
 *
 * L'implémentation vit dans la verticale (même inversion de dépendance que
 * `PublicDiscoverableProvider`, garde d'isolation Shared -> jamais Modules).
 *
 * Invariants :
 *  - **fail-closed** : un profil non publiable (dépublié, suspendu, société
 *    `suspended`/`expired`, feature verticale absente) renvoie `null` — jamais
 *    une erreur distincte qui permettrait d'énumérer les profils existants ;
 *  - **aucune donnée interne sortante** : le résultat ne sert QU'À router la
 *    demande vers le bon tenant ; il n'est jamais sérialisé vers le public ;
 *  - l'identifiant de tenant retourné est celui du PROPRIÉTAIRE du profil et
 *    n'est jamais accepté en entrée d'une requête publique.
 */
interface PublicProfileResolver
{
    /**
     * Type canonique résolu (même vocabulaire que `PublicDiscoverableProvider::type()`).
     */
    public function type(): string;

    /**
     * Tenant propriétaire d'un profil public, ou `null` s'il n'est pas joignable.
     *
     * @return array{company_id: string, display_name: string}|null
     */
    public function resolve(string $slug): ?array;
}
