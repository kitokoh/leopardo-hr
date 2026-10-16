<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * #8540 (TRAVEL-OS) — provenance d'une position de véhicule.
 *
 * Distinction OPPOSABLE entre une position réellement mesurée et une position
 * déduite : une position `ESTIMATED` ne doit JAMAIS être présentée comme une
 * position GPS. Aucun fournisseur de télémétrie n'étant intégré, la donnée ne
 * peut provenir que d'un appareil autorisé (`GPS` ou `APP`).
 */
enum TravelVehiclePositionSource: string
{
    /** Équipement GPS embarqué (boîtier de télémétrie). */
    case GPS = 'gps';

    /** Application autorisée (conducteur / agent) transmettant sa position. */
    case APP = 'app';

    /** Position déduite du trajet — jamais une mesure. */
    case ESTIMATED = 'estimated';

    public function label(): string
    {
        return match ($this) {
            self::GPS => 'GPS embarqué',
            self::APP => 'Application autorisée',
            self::ESTIMATED => 'Position estimée (non GPS)',
        };
    }

    /**
     * `true` uniquement pour une position réellement mesurée par un dispositif.
     * Le tableau de bord s'appuie sur ce drapeau pour ne pas afficher une
     * estimation comme une position GPS.
     */
    public function isMeasured(): bool
    {
        return $this !== self::ESTIMATED;
    }
}
