<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Enums;

/**
 * Résultats d'une tentative de vérification biométrique / d'identité
 * (ATT-002, #6761).
 *
 * Couvre l'ensemble des issues définies par l'issue : succès, rejet,
 * qualité insuffisante, liveness échoué, appareil non fiable,
 * indisponibilité et bascule (fallback) vers une autre méthode.
 *
 * Ces résultats sont neutres vis-à-vis des fournisseurs : un adaptateur
 * (BIO-001) traduit la réponse propriétaire d'un moteur vers ces valeurs de
 * domaine ; le domaine ne connaît ni le fournisseur ni ses exceptions.
 */
enum VerificationResult: string
{
    /** L'identité a été vérifiée avec succès — le pointage peut être enregistré. */
    case Success = 'success';

    /** Rejet franc (visage inconnu, empreinte non reconnue, ...). */
    case Rejected = 'rejected';

    /** Qualité de capture insuffisante (image floue, sous-exposée, ...). */
    case QualityFailed = 'quality_failed';

    /** Échec de détection de vivacité (liveness) — tentative suspecte. */
    case LivenessFailed = 'liveness_failed';

    /** Appareil non fiable (état incohérent, capteur défaillant, ...). */
    case DeviceUnreliable = 'device_unreliable';

    /** Fournisseur / moteur indisponible (timeout, panne, non configuré). */
    case Unavailable = 'unavailable';

    /**
     * Bascule acceptée : la méthode initiale a échoué mais une méthode de
     * secours a été utilisée avec succès (BIO-006).
     */
    case Fallback = 'fallback';

    /**
     * Le pointage peut être enregistré : succès direct, ou bascule
     * (fallback) déjà consommée par le flux appelant.
     */
    public function allowsRecording(): bool
    {
        return $this === self::Success || $this === self::Fallback;
    }

    /** Échec rejouable : une autre méthode (ou une nouvelle tentative) est possible. */
    public function isRetryable(): bool
    {
        return ! $this->allowsRecording();
    }

    /**
     * Code machine stable à exposer à l'interface (jamais de libellé libre).
     */
    public function reasonCode(): string
    {
        return match ($this) {
            self::Success => 'VERIFICATION_SUCCESS',
            self::Rejected => 'VERIFICATION_REJECTED',
            self::QualityFailed => 'VERIFICATION_QUALITY_FAILED',
            self::LivenessFailed => 'VERIFICATION_LIVENESS_FAILED',
            self::DeviceUnreliable => 'VERIFICATION_DEVICE_UNRELIABLE',
            self::Unavailable => 'VERIFICATION_UNAVAILABLE',
            self::Fallback => 'VERIFICATION_FALLBACK_USED',
        };
    }
}
