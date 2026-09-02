<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Enums;

/**
 * Statuts d'un enrôlement biométrique versionné (BIO-002, #6763 / BIO-003, #6764).
 *
 * Cycle de vie : `pending` (capture faite, gabarit stocké, en attente
 * d'activation) → `active` (utilisable pour pointer) → `revoked`.
 *
 * Un enrôlement incomplet (`pending`) ne peut JAMAIS être utilisé pour
 * pointer : seuls les enrôlements `active` sont résolus par la vérification
 * (BIO-004).
 */
enum BiometricEnrollmentStatus: string
{
    /** Capture fournie, gabarit stocké, activation manager/RH requise. */
    case Pending = 'pending';

    /** Gabarit actif — utilisable pour la vérification au pointage. */
    case Active = 'active';

    /** Révoqué (remplacement, départ, demande RGPD, appareil compromis...). */
    case Revoked = 'revoked';

    /** Seul un enrôlement actif peut être utilisé pour vérifier une identité. */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
