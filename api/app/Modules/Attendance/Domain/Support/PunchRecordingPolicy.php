<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Support;

use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Enums\VerificationResult;
use App\Modules\Attendance\Domain\ValueObjects\AttendanceRecordingDecision;

/**
 * Règle applicative unifiée de création d'un événement de présence
 * (ATT-002, #6761).
 *
 * Tous les flux de pointage (mobile, kiosque biométrique, badge, PIN,
 * validation manager, saisie manuelle) passent par la même décision avant
 * d'enregistrer une présence :
 *
 *  1. la méthode doit faire partie de la matrice activée
 *     (tenant / site / kiosque — {@see self::$configuredMethods}) ;
 *  2. une méthode biométrique exige l'enrôlement/flags de l'employé
 *     ({@see self::$employeeEnabledMethods}) ;
 *  3. le résultat de la vérification doit permettre l'enregistrement
 *     (succès ou bascule déjà consommée).
 *
 * La règle est pure (aucune dépendance Laravel, Eloquent ou fournisseur) :
 * elle est testée unitairement sans base de données. L'empreinte existante
 * reste compatible : les valeurs historiques de `attendance_logs.method`
 * (`fingerprint`, `face`, `card`) se résolvent via
 * {@see VerificationMethod::fromAttendanceLogMethod()}.
 */
final class PunchRecordingPolicy
{
    /**
     * @param  list<string>  $configuredMethods  méthodes activées (matrice tenant/site/kiosque), valeurs VerificationMethod
     * @param  list<string>  $employeeEnabledMethods  méthodes biométriques enrôlées pour l'employé
     */
    public function __construct(
        private readonly array $configuredMethods,
        private readonly array $employeeEnabledMethods,
    ) {}

    public function decide(VerificationMethod $method, VerificationResult $result): AttendanceRecordingDecision
    {
        if (! in_array($method->value, $this->configuredMethods, true)) {
            return AttendanceRecordingDecision::deny('PUNCH_METHOD_NOT_CONFIGURED');
        }

        if ($method->isBiometric() && ! in_array($method->value, $this->employeeEnabledMethods, true)) {
            return AttendanceRecordingDecision::deny('BIOMETRIC_NOT_ENABLED');
        }

        if (! $result->allowsRecording()) {
            return AttendanceRecordingDecision::deny($result->reasonCode());
        }

        return AttendanceRecordingDecision::allow();
    }
}
