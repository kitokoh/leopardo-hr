<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Enums;

/**
 * BC-10 RECRUITMENT — état d'un connecteur d'agrégateur d'offres.
 *
 * `NotConnected` est l'état par défaut : l'écran super admin peut donc
 * afficher « non connecté » honnêtement, au lieu d'un zéro qui se lirait
 * comme un mauvais résultat. Un connecteur ne passe à `Connected` qu'après
 * une synchronisation RÉELLE réussie — jamais parce qu'un test local a
 * validé la forme des identifiants.
 */
enum JobSourceStatus: string
{
    case NotConnected = 'not_connected';
    case Connected = 'connected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Non connecté',
            self::Connected => 'Connecté',
            self::Error => 'En erreur',
        };
    }

    /** Un connecteur dans cet état peut-il être synchronisé ? */
    public function canSync(): bool
    {
        return $this === self::Connected;
    }
}
