<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Type de compteur suivi sur un materiel.
 *
 * Registre FERME et VERSIONNE. `None` est une valeur de plein droit : un outil
 * sans compteur n'est pas un outil « incomplet », et Farm OS n'affiche alors
 * aucun compteur plutot que d'en inventer un a zero.
 */
enum FarmEquipmentMeterType: string
{
    case Hours = 'hours';
    case Kilometers = 'kilometers';
    case Hectares = 'hectares';
    case None = 'none';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.meter_type.'.$this->value;
    }

    /**
     * Un compteur suivi implique une saisie manuelle ou un import : jamais une
     * lecture automatique (aucune telemetrie integree).
     */
    public function isTracked(): bool
    {
        return $this !== self::None;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
