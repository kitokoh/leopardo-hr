<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-8 (#8519) — état d'une intégration externe (GSC, GA4, Google Ads, MS Ads).
 *
 * `NotConnected` est l'état PAR DÉFAUT et un état de premier ordre : la vue
 * d'ensemble doit pouvoir afficher « non connecté » honnêtement plutôt que des
 * zéros qui se lisent comme de mauvais résultats. Seul `Connected` autorise
 * une synchronisation.
 */
enum IntegrationStatus: string
{
    case NotConnected = 'not_connected';
    case Connected = 'connected';
    case Error = 'error';

    /** Une synchronisation n'a de sens que sur une intégration connectée. */
    public function canSync(): bool
    {
        return $this === self::Connected;
    }

    /**
     * L'état est-il honnête à afficher comme « pas de données » plutôt que
     * comme « zéro performance » ?
     */
    public function isAbsent(): bool
    {
        return $this !== self::Connected;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Non connecté',
            self::Connected => 'Connectée',
            self::Error => 'Erreur de connexion',
        };
    }
}
