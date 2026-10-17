<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS (BC-37 AGRICULTURE) — Provenance d'une superficie (ADR-FARM-0003).
 *
 * Farm OS ne prétend JAMAIS mesurer automatiquement une superficie sans
 * données ni mécanisme de mesure approprié. Chaque superficie porte donc sa
 * provenance, affichée telle quelle à l'utilisateur :
 *
 *   - `Declared`  : valeur SAISIE par l'exploitant (défaut de toute création) ;
 *   - `Imported`  : valeur issue d'un import de données existantes ;
 *   - `Measured`  : valeur DÉRIVÉE d'une géométrie cartographique réellement
 *                   présente (seul cas où `FarmAreaGuard` autorise ce label).
 *
 * Règle serveur : `measured` sans géométrie est REFUSÉ (422) — voir
 * \App\Modules\FarmOs\Domain\Services\FarmAreaGuard.
 */
enum AreaSource: string
{
    case Declared = 'declared';
    case Imported = 'imported';
    case Measured = 'measured';

    public function translationKey(): string
    {
        return 'farm.area_source.'.$this->value;
    }

    /**
     * Le label peut-il être affirmé sans géométrie ?
     */
    public function requiresGeometry(): bool
    {
        return $this === self::Measured;
    }
}
