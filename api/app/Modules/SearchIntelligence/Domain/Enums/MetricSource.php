<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-10 (#8520) — provenance d'un relevé de performance.
 *
 * `Manual` = chiffre saisi par un humain, typiquement recopié du back-office
 * d'une régie. `Integration` = chiffre lu via un connecteur réellement
 * connecté. Ce qui n'entre dans aucune des deux catégories n'a pas de
 * provenance et n'est donc pas stockable — c'est ce qui interdit les
 * « métriques de démonstration » dans les tableaux de bord.
 */
enum MetricSource: string
{
    case Manual = 'manual';
    case Integration = 'integration';

    /** Un relevé d'intégration doit nommer son intégration (contrainte CHECK). */
    public function requiresIntegration(): bool
    {
        return $this === self::Integration;
    }
}
