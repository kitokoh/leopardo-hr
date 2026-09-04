<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Événement de présence versionné — AttendanceRecorded.v1 (ATT-003, #6768).
 *
 * Publié à CHAQUE événement de présence enregistré (check-in, check-out,
 * import externe/offline), quel que soit le mode (mobile, kiosque, QR,
 * géo, terminal ZKTeco). Version 1 : le payload est un CONTRAT — toute
 * modification de champ (nom, type, ajout/suppression) impose une nouvelle
 * version (v2) et une entrée au catalogue MAT-006.
 *
 * Découplage Payroll (ATT-003) : Payroll consomme la PROJECTION validée
 * `AttendanceLog` (lecture seule, garde CI check-attendance-boundary.sh) ou
 * cet événement — il ne dépend JAMAIS des adaptateurs biométriques
 * (KioskAttendanceService, Zkteco*, FaceVerification*, enrôlements).
 *
 * Idempotence : l'événement est un fait passé (event sourcing) ; le
 * producteur garantit l'unicité de la présence (verrous + external_event_id
 * sur le flux offline) et `correlation_id` permet aux consommateurs de
 * dédupliquer un rejeu.
 *
 * @property-read string $version Version du contrat (toujours '1' ici).
 */
class AttendanceRecorded
{
    use Dispatchable;

    public const VERSION = '1';

    public function __construct(
        /** Tenant (company_id, uuid). */
        public readonly string $companyId,
        /** Employé concerné. */
        public readonly int $employeeId,
        /** Site / lieu de travail, si connu (nullable). */
        public readonly ?int $workplaceId,
        /** Type d'événement de présence : check_in | check_out. */
        public readonly string $eventType,
        /** Date/heure UTC de l'événement. */
        public readonly string $occurredAtUtc,
        /** Méthode de vérification réellement utilisée (VerificationMethod / legacy). */
        public readonly string $verificationMethod,
        /** Résultat de vérification (success | fallback | ...) ou null. */
        public readonly ?string $verificationResult,
        /** Kiosque émetteur, si pointage kiosque (nullable). */
        public readonly ?int $kioskId,
        /** Corrélation (external_event_id du flux offline, sinon uuid). */
        public readonly string $correlationId,
        /** Id de l'événement de présence (attendance_logs.id). */
        public readonly int $attendanceLogId,
    ) {}
}
