<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Enums;

/**
 * Méthodes de vérification d'identité du pointage multi-méthodes (ATT-002, #6761).
 *
 * Le pointage kiosque / mobile peut prouver l'identité du salarié par plusieurs
 * méthodes. Chaque méthode est une valeur du domaine, indépendante des
 * fournisseurs biométriques : le domaine ne connaît ni SDK, ni format de
 * template, ni terminal.
 *
 * Méthodes initiales (issue #6761) : `fingerprint`, `face`, `badge`, `pin`,
 * `manager`, `manual`.
 *
 * Persistance : `attendance_logs.method` accepte historiquement
 * `mobile|qr|biometric|manual|geo_auto|zkteco|fingerprint|face|card`
 * (migrations #5121, #1867...). La valeur de domaine `badge` correspond à la
 * valeur persistée `card` (lecture/carte de pointage) — le mapping est porté
 * par {@see self::attendanceLogMethod()} / {@see self::fromAttendanceLogMethod()}.
 * Les valeurs `pin` / `manager` sont de nouvelles valeurs de domaine ; leur
 * persistance dans `attendance_logs.method` sera ajoutée (extension de la
 * contrainte) quand les flux correspondants écriront réellement (BIO-006).
 */
enum VerificationMethod: string
{
    case Fingerprint = 'fingerprint';

    case Face = 'face';

    case Badge = 'badge';

    case Pin = 'pin';

    /** Pointage validé par un manager (cas exceptionnels, BIO-006). */
    case Manager = 'manager';

    /** Saisie manuelle / correction administrative (flux existant). */
    case Manual = 'manual';

    /**
     * Valeur canonique persistée dans `attendance_logs.method`.
     *
     * `badge` (domaine) est persisté `card` (valeur historique du schéma).
     */
    public function attendanceLogMethod(): string
    {
        return $this === self::Badge ? 'card' : $this->value;
    }

    /**
     * Résout une valeur persistée de `attendance_logs.method` vers la méthode
     * du domaine.
     *
     * Retourne `null` pour toute valeur inconnue ou ambiguë (`mobile`, `qr`,
     * `biometric`, `geo_auto`, `zkteco` ne sont pas des méthodes de
     * vérification d'identité) : une méthode inconnue est rejetée — le
     * domaine ne s'élargit jamais silencieusement.
     */
    public static function fromAttendanceLogMethod(string $method): ?self
    {
        return match ($method) {
            'fingerprint' => self::Fingerprint,
            'face' => self::Face,
            'card', 'badge' => self::Badge,
            'pin' => self::Pin,
            'manager' => self::Manager,
            'manual' => self::Manual,
            default => null,
        };
    }

    /** Méthode biométrique (nécessite un template / un enrôlement). */
    public function isBiometric(): bool
    {
        return $this === self::Fingerprint || $this === self::Face;
    }
}
