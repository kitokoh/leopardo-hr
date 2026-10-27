<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Schema;

/**
 * #7962 — cas d'usage : roster biométrique du kiosque.
 *
 * Extrait de `KioskController::doRoster()` (contrôleur de 980 lignes, cible de
 * l'audit structurel). Le contrôleur ne fait plus que l'orchestration HTTP
 * (résolution du kiosque, search_path, réponse) ; la requête et la
 * sérialisation vivent ici.
 *
 * Comportement inchangé, y compris la tolérance aux schémas historiques : les
 * colonnes biométriques peuvent manquer sur un tenant ancien, auquel cas le
 * roster n'est pas filtré et les drapeaux valent `false`.
 */
class BuildKioskRoster
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(Company $company): array
    {
        $hasFaceColumn = Schema::hasColumn('employees', 'biometric_face_enabled');
        $hasFingerprintColumn = Schema::hasColumn('employees', 'biometric_fingerprint_enabled');

        return Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->when($hasFaceColumn || $hasFingerprintColumn, function ($query) use ($hasFaceColumn, $hasFingerprintColumn): void {
                $query->where(function ($biometricQuery) use ($hasFaceColumn, $hasFingerprintColumn): void {
                    if ($hasFaceColumn) {
                        $biometricQuery->orWhere('biometric_face_enabled', true);
                    }

                    if ($hasFingerprintColumn) {
                        $biometricQuery->orWhere('biometric_fingerprint_enabled', true);
                    }
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => $this->serialize($employee, $hasFaceColumn, $hasFingerprintColumn))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Employee $employee, bool $hasFaceColumn, bool $hasFingerprintColumn): array
    {
        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'email' => $employee->email,
            'matricule' => $employee->matricule,
            'zkteco_id' => $employee->zkteco_id,
            // ATT-004 (#6769) : badge exposé au kiosque (badge/PIN/validation
            // manager) — l'identifiant de pointage par carte est déjà accepté
            // par punch (BIO-006). L'appareil est authentifié (X-Kiosk-Token).
            'badge_number' => $employee->badge_number,
            'face_enabled' => $hasFaceColumn ? (bool) ($employee->biometric_face_enabled ?? false) : false,
            'fingerprint_enabled' => $hasFingerprintColumn ? (bool) ($employee->biometric_fingerprint_enabled ?? false) : false,
        ];
    }
}
