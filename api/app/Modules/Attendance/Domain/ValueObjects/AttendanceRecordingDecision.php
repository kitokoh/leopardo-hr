<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\ValueObjects;

/**
 * Décision de la règle applicative unifiée de création d'un événement de
 * présence (ATT-002, #6761).
 *
 * Une décision est soit un laisser-passer ({@see self::allow()}), soit un
 * refus portant un code machine stable ({@see self::deny()}). Le code est
 * une valeur de l'API (jamais un libellé libre) : l'interface le traduit en
 * message localisé, le domaine ne connaît pas les catalogues i18n.
 */
final readonly class AttendanceRecordingDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason,
    ) {}

    public static function allow(): self
    {
        return new self(true, null);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
