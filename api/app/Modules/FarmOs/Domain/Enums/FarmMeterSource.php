<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Provenance d'une valeur de compteur.
 *
 * Registre FERME et VERSIONNE, volontairement MINIMAL : `manual` (saisie par un
 * utilisateur) et `imported` (fichier ou systeme tiers fourni par l'exploitant).
 *
 * Il n'existe AUCUNE case `sensor` / `telemetry` : Farm OS n'a aucune
 * integration de telemetrie agricole, et ne doit donc jamais presenter une
 * valeur comme automatiquement mesuree. Ajouter une telle case exigerait une
 * integration reelle, testee et autorisee (Phase 7 de la mission).
 */
enum FarmMeterSource: string
{
    case Manual = 'manual';
    case Imported = 'imported';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.meter_source.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
