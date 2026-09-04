<?php

declare(strict_types=1);

namespace App\Core\AI\Domain\Enums;

/**
 * Issues d'une tentative de vérification faciale (BIO-001, #6762).
 *
 * Scénarios couverts par l'issue : `verified`, `rejected`, `liveness_failed`,
 * `quality_failed` et `provider_unavailable`.
 */
enum FaceVerificationStatus: string
{
    /** L'identité est confirmée (comparaison 1:1 positive). */
    case Verified = 'verified';

    /** Rejet franc : le visage capturé ne correspond pas au gabarit. */
    case Rejected = 'rejected';

    /** Qualité de capture insuffisante (flou, occultation, exposition...). */
    case QualityFailed = 'quality_failed';

    /** Échec de vivacité — tentative suspecte (photo, masque, écran). */
    case LivenessFailed = 'liveness_failed';

    /** Fournisseur non configuré ou indisponible (fail-closed par défaut). */
    case ProviderUnavailable = 'provider_unavailable';

    /** Délai dépassé — traité comme indisponibilité par l'appelant. */
    case Timeout = 'timeout';

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
