<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Restaurant OS — REST-OS-7 Etat d'une operation de synchronisation (#8609).
 *
 * Registre FERME et VERSIONNE. `Failed` et `Rejected` sont des etats de plein
 * droit : une operation en echec reste VISIBLE dans l'historique des erreurs,
 * elle n'est jamais silencieusement perdue.
 */
enum RestaurantSyncStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'restaurant.sync_status.'.$this->value;
    }

    /**
     * L'operation est-elle terminee (plus rien a rejouer) ?
     */
    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * L'operation doit-elle apparaitre dans l'historique des erreurs ?
     */
    public function isError(): bool
    {
        return in_array($this, [self::Rejected, self::Failed], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
