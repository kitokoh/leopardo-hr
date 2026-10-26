<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Enums;

/**
 * GSI-2 (#8513) — gravité d'une anomalie SEO.
 *
 * L'ordre de l'énumération est l'ordre de priorité (du plus grave au plus
 * anodin) : `rank()` s'appuie dessus pour trier une liste d'anomalies sans
 * table de correspondance supplémentaire.
 */
enum SeoIssueSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    /** Rang de priorité : 0 = le plus grave. */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
            self::Info => 4,
        };
    }

    public function isBlocking(): bool
    {
        return $this === self::Critical;
    }
}
