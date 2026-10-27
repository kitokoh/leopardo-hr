<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Restaurant OS — REST-OS-7 Strategie de resolution d'un conflit (#8609).
 *
 * Registre FERME et VERSIONNE. La strategie est CHOISIE par le service selon la
 * nature de l'operation, et elle est CONSERVEE avec le conflit : il n'y a jamais
 * d'ecrasement silencieux.
 */
enum RestaurantConflictStrategy: string
{
    /** L'operation locale est appliquee (additive, aucun risque d'ecrasement). */
    case LocalWins = 'local_wins';

    /** La valeur serveur est conservee (donnee financiere ou de stock divergente). */
    case ServerWins = 'server_wins';

    /**
     * Arbitrage HUMAIN requis. C'est la strategie imposee pour les encaissements
     * et les mouvements de stock : le systeme refuse de trancher a la place du
     * restaurateur sur une donnee qui fausserait une recette ou un stock.
     */
    case ManualReview = 'manual_review';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'restaurant.conflict_strategy.'.$this->value;
    }

    /**
     * La strategie exige-t-elle une intervention humaine avant application ?
     */
    public function requiresHumanArbitration(): bool
    {
        return $this === self::ManualReview;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
