<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Discovery;

/**
 * SUPERAPP-P0 — Contrat partagé de la découverte publique cross-verticale.
 *
 * Patron `App\Shared\Contracts\Catalog\PublishedProductsProvider` : chaque
 * verticale expose SES entités DÉJÀ publiques via ce contrat, et l'agrégateur
 * `App\Shared\Discovery\PublicDiscoveryService` compose une surface unique
 * `GET /api/v1/public/discover` — sans que le service ne connaisse aucune
 * verticale (garde d'isolation #5584 : aucune dépendance Shared -> Modules).
 *
 * Invariants (non négociables) :
 *  - fail-closed : l'implémentation n'expose QUE des entités explicitement
 *    publiées (`is_public=true`/`status=published`), d'une société non
 *    `suspended`/`expired`, dont la feature verticale est active ;
 *  - DTO public STRICT : jamais d'`id` interne, de `company_id`, de stock, de
 *    marge ni de `meta` — le `slug` EST l'identifiant public ;
 *  - aucune écriture, aucune nouvelle table : lecture seule des données
 *    publiques existantes.
 *
 * L'implémentation est fournie par le module de la verticale et taguée
 * `public.discoverable` dans son ServiceProvider (inversion de dépendance) ;
 * ce contrat ne référence jamais `App\Modules\*`.
 */
interface PublicDiscoverableProvider
{
    /**
     * Type canonique des entités retournées par ce provider (ex. `restaurant`,
     * `hospitality_property`, `showcase`). Sert de valeur à `?type=` et de
     * discriminant `type` dans chaque item public.
     */
    public function type(): string;

    /**
     * Actions RÉELLEMENT prises en charge par cette verticale sur un profil
     * public (#8761).
     *
     * C'est la source de vérité unique exposée au frontend via `meta.actions` :
     * la passerelle publique n'a donc AUCUNE liste de capacités codée en dur
     * (un `BOOKING_TYPES` frontend dériverait silencieusement du backend et
     * afficherait un bouton inopérant sur une verticale non prête).
     *
     * Valeurs reconnues aujourd'hui :
     *   - `contact`   : la verticale accepte une demande de contact publique ;
     *   - `booking`   : réservation prise en charge par un service métier réel ;
     *   - `order`     : commande publique prise en charge (restauration, retail).
     *
     * Un provider qui n'implémente pas ce comportement expose une liste VIDE :
     * fail-closed, aucune action n'est annoncée par défaut.
     *
     * @return list<string>
     */
    public function supportedActions(): array;

    /**
     * Destination NAVIGATEUR de chaque action déclarée (#8761, complément).
     *
     * `supportedActions()` dit qu'un service métier existe ; il ne dit pas que
     * le client peut L'ATTEINDRE. Les deux sont nécessaires : Retail sert
     * réellement `POST /public/market/orders`, mais ce checkout vit dans une
     * application séparée (`front/marketplace`) absente du dépôt de la
     * passerelle. Un CTA `order` pointant vers `/boutiques/{slug}` — une fiche
     * profil sans panier — est donc exactement le bouton inopérant que #8761
     * interdit, malgré une action légitime.
     *
     * Contrat : une clé n'est présente QUE si l'action est effectivement
     * atteignable depuis la passerelle publique. Absence de clé = action non
     * exposée au frontend, qui affiche un état « indisponible » explicite.
     * `null` en valeur = action servie sans destination propre (ex. `contact`,
     * qui ouvre le formulaire de la passerelle elle-même).
     *
     * Les URL sont RELATIVES à l'origine publique (`/restaurants/{slug}#…`),
     * jamais absolues : une origine en dur casserait les environnements.
     *
     * Doit rester un SOUS-ENSEMBLE de `supportedActions()` — la garde
     * `PublicDiscoverableProviderContractTest` le vérifie.
     *
     * @return array<string, string|null> action => URL relative, ou null
     */
    public function supportedActionUrls(): array;

    /**
     * Entités PUBLIQUES de la verticale correspondant aux filtres.
     *
     * Clés de filtre reconnues (toutes optionnelles) :
     *   - `q`          : recherche texte (nom / description publique) ;
     *   - `city`       : filtre ville (insensible à la casse en PostgreSQL) ;
     *   - `limit`      : borne dure du nombre de lignes remontées par ce
     *                    provider (l'agrégateur applique ensuite page/taille).
     *
     * Chaque item est un tableau public minimal :
     *   string  type, string slug, string name,
     *   ?string category, ?string city, ?string country,
     *   ?string description, ?string image_url,
     *   ?float  latitude, ?float longitude, string url.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>> liste vide si la verticale est absente/inactive (dépendance optionnelle)
     */
    public function search(array $filters): array;
}
