<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Privacy\Domain\Enums\PiiSensitivity;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\PrivacyRequest;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MAT-011 (#5869) — Cycle de vie des données personnelles (BC-01 PLATFORM).
 *
 * Regroupe les droits RGPD / Loi 18-07 de façon testable et réutilisable :
 *  - classification (via {@see PiiRegistry}) ;
 *  - export (« portabilité ») du bundle personnel de l'employé ;
 *  - anonymisation (droit à l'effacement, historique de paie conservé) ;
 *  - demande de suppression tracée ;
 *  - calendrier de rétention par contexte.
 */
final class PiiLifecycleService
{
    public function __construct(
        private readonly PiiRegistry $registry,
    ) {
    }

    public function classify(string $key): ?PiiSensitivity
    {
        return $this->registry->sensitivity($key);
    }

    /**
     * Bundle d'export des données personnelles de l'employé (portabilité).
     *
     * @return array<string, mixed>
     */
    public function exportBundle(Employee $employee): array
    {
        return [
            'employee' => $this->employeePayload($employee),
            'activity_summary' => [
                'attendance_logs_count' => $this->countEmployeeRows(AttendanceLog::class, $employee),
                'absence_requests_count' => $this->countEmployeeRows(Absence::class, $employee),
                'pay_slips_count' => $this->countEmployeeRows(PaySlip::class, $employee),
                'expense_claims_count' => $this->countEmployeeRows(ExpenseClaim::class, $employee),
            ],
            'privacy' => [
                'exported_at' => now()->toIso8601String(),
                'scope' => 'authenticated_employee_self_service',
                'format_version' => '2026-05-14',
                'catalog_version' => $this->registry->version(),
            ],
        ];
    }

    /**
     * Anonymise les données personnelles d'un employé (RGPD art. 17 /
     * Loi 18-07) en conservant l'historique de paie (obligation légale).
     *
     * Idempotent : un employé déjà anonymisé ne re-traité pas. Le dry-run
     * retourne le résumé sans écrire.
     *
     * @return array{employee_id: int, anonymized: bool, fields_changed: int, photo_deleted: bool, audit_action: string|null}
     */
    public function anonymize(Employee $employee, bool $dryRun = false): array
    {
        if ($employee->status === 'archived' && $employee->first_name === 'Anonymisé') {
            return [
                'employee_id' => $employee->id,
                'anonymized' => false,
                'fields_changed' => 0,
                'photo_deleted' => false,
                'audit_action' => null,
            ];
        }

        $photoPath = $employee->photo_path;
        $oldStatus = $employee->status;

        $changes = [
            'first_name' => 'Anonymisé',
            'middle_name' => null,
            'last_name' => 'Employé '.$employee->id,
            'preferred_name' => null,
            'email' => 'anonyme-'.$employee->id.'@anonyme.local',
            'personal_email' => null,
            'recovery_email' => null,
            'personal_phone' => null,
            'phone' => null,
            'address_line' => null,
            'postal_code' => null,
            // `password_hash` est NOT NULL dans le vrai schéma (migrations tenant) :
            // on invalide l'ancien mot de passe avec un hash aléatoire au lieu de NULL.
            'password_hash' => Hash::make(Str::random(64)),
            'date_of_birth' => null,
            'place_of_birth' => null,
            'gender' => null,
            'nationality' => null,
            'marital_status' => null,
            'national_id' => null,
            'iban' => null,
            'bank_account' => null,
            'zkteco_id' => null,
            'photo_path' => null,
            'biometric_face_enabled' => false,
            'biometric_fingerprint_enabled' => false,
            'biometric_face_reference_path' => null,
            'biometric_fingerprint_reference_path' => null,
            'biometric_consent_at' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'emergency_contact_relation' => null,
            'extra_data' => null,
            'status' => 'archived',
        ];

        if ($dryRun) {
            return [
                'employee_id' => $employee->id,
                'anonymized' => false,
                'fields_changed' => count($changes),
                'photo_deleted' => false,
                'audit_action' => null,
            ];
        }

        $photoDeleted = false;

        DB::transaction(function () use ($employee, $changes, $oldStatus, $photoPath, &$photoDeleted): void {
            // Issue #4496 : password_hash + chemins biométriques ne sont plus
            // mass-assignables — forceFill explicite (opération d'anonymisation).
            $employee->forceFill($changes)->save();

            // Purge des demandes d'enrôlement biométrique (chemins de référence + notes).
            BiometricEnrollmentRequest::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->update([
                    'requested_face_reference_path' => null,
                    'requested_fingerprint_reference_path' => null,
                    'employee_note' => null,
                    'manager_note' => null,
                ]);

            AuditLog::create([
                'company_id' => $employee->company_id,
                'user_id' => $employee->id,
                'action' => 'gdpr_employee_anonymized',
                'auditable_type' => $employee->getMorphClass(),
                'auditable_id' => $employee->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'archived', 'pii' => 'anonymized'],
                'metadata' => ['legal_basis' => 'RGPD art. 17 / Loi 18-07', 'payroll_history_kept' => true],
            ]);

            // Suppression physique du fichier photo si présent.
            if ($photoPath !== null && $photoPath !== '') {
                Storage::delete($photoPath);
                $photoDeleted = true;
            }
        });

        return [
            'employee_id' => $employee->id,
            'anonymized' => true,
            'fields_changed' => count($changes),
            'photo_deleted' => $photoDeleted,
            'audit_action' => 'gdpr_employee_anonymized',
        ];
    }

    /**
     * Dépose une demande de suppression tracée (droit à l'effacement).
     *
     * @param  array<string, mixed>  $requestContext  (ip, user_agent, …)
     */
    public function requestDeletion(Employee $employee, ?string $reason, array $requestContext = []): PrivacyRequest
    {
        /** @var PrivacyRequest $privacyRequest */
        $privacyRequest = PrivacyRequest::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'type' => 'deletion',
            'status' => 'received',
            'requested_payload' => [
                'reason' => $reason,
                'ip' => $requestContext['ip'] ?? null,
                'user_agent' => $requestContext['user_agent'] ?? null,
                'requested_at' => now()->toIso8601String(),
                'destructive_action' => false,
            ],
        ]);

        return $privacyRequest;
    }

    /**
     * Calendrier de rétention par contexte (mois), dérivé du catalogue.
     *
     * @return array<string, int|null>
     */
    public function retentionSchedule(): array
    {
        $schedule = [];

        foreach ($this->registry->entries() as $category) {
            $entries = is_array($category) ? ($category['entries'] ?? []) : [];

            foreach ($entries as $key => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $context = (string) ($entry['context'] ?? 'unknown');
                $months = $entry['retention_months'] ?? null;

                if (! array_key_exists($context, $schedule)) {
                    $schedule[$context] = is_int($months) ? $months : null;
                } elseif (is_int($months) && ($schedule[$context] === null || $months < $schedule[$context])) {
                    $schedule[$context] = $months;
                }
            }
        }

        return $schedule;
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'company_id' => $employee->company_id,
            'matricule' => $employee->matricule,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'preferred_name' => $employee->preferred_name,
            'email' => $employee->email,
            'personal_email' => $employee->personal_email,
            'phone' => $employee->phone,
            'role' => $employee->role,
            'manager_role' => $employee->manager_role,
            'status' => $employee->status,
            'preferred_language' => $employee->preferred_language,
            'biometric_face_enabled' => $employee->biometric_face_enabled,
            'biometric_fingerprint_enabled' => $employee->biometric_fingerprint_enabled,
            'biometric_consent_at' => optional($employee->biometric_consent_at)->toIso8601String(),
            'created_at' => optional($employee->created_at)->toIso8601String(),
            'updated_at' => optional($employee->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function countEmployeeRows(string $modelClass, Employee $employee): int
    {
        return $modelClass::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->count();
    }
}
