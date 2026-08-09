<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Programme FOCUS — F-18 (#1548) : droit à l'effacement (RGPD).
 *
 * Anonymise les données personnelles d'un employé (Loi 18-07 / RGPD) tout en
 * CONSERVANT l'historique de paie (obligation légale de conservation : DZ 10
 * ans) : les identifiants personnels (nom, email, téléphones, adresse, dates,
 * biométrie, photo, contact d'urgence, données bancaires, zkteco_id) sont
 * effacés/remplacés, les données économiques (bulletins, runs, salaires
 * agrégés) restent exploitables sans permettre la ré-identification.
 *
 * Usage :
 *   php artisan gdpr:anonymize-employee {employee_id} [--company={id}] [--dry-run] [--force]
 */
class GdprAnonymizeEmployeeCommand extends Command
{
    protected $signature = 'gdpr:anonymize-employee {employee : ID de l\'employé à anonymiser}
        {--company= : ID de la société (tenant) cible — recommandé en production multi-tenant}
        {--dry-run : Affiche les actions sans rien écrire}
        {--force : Exécute sans confirmation interactive}';

    protected $description = 'Anonymise les données personnelles d\'un employé (RGPD/Loi 18-07) en conservant l\'historique de paie';

    public function handle(TenantManager $tenantManager): int
    {
        $employeeId = (int) $this->argument('employee');
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');

        $employee = Employee::query()->find($employeeId);

        if (! $employee instanceof Employee) {
            $this->error('Employé introuvable.');

            return self::FAILURE;
        }

        // Garde tenant explicite : en production multi-tenant, le process artisan
        // doit viser la bonne société. On vérifie l'appartenance au lieu de
        // changer le search_path (les schémas tenant diffèrent selon l'env).
        if ($companyId !== null && $companyId !== '') {
            $company = Company::query()->find($companyId);
            if (! $company instanceof Company) {
                $this->error('Société introuvable (--company).');

                return self::FAILURE;
            }
            if ((string) $employee->company_id !== (string) $company->id) {
                $this->error("L'employé #{$employeeId} n'appartient pas à la société #{$companyId}.");

                return self::FAILURE;
            }
        }

        return $this->anonymize($employee, $dryRun);
    }

    private function anonymize(Employee $employee, bool $dryRun): int
    {
        // Idempotence : un employé déjà anonymisé ne doit pas être re-traité
        // (évite les doublons d'audit et la confusion avec un vrai « Anonymisé »).
        if ($employee->status === 'archived' && $employee->first_name === 'Anonymisé') {
            $this->warn("Employé #{$employee->id} déjà anonymisé — aucune action.");

            return self::SUCCESS;
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

        $this->warn(sprintf(
            'Anonymisation de l\'employé #%d (%s) — société #%s%s',
            $employee->id,
            $employee->email,
            $employee->company_id,
            $dryRun ? ' [DRY-RUN]' : ''
        ));
        $this->line('Champs PII effacés : '.count($changes).' — biométrie (références + consentement), photo, banque, zkteco_id inclus.');

        if ($dryRun) {
            $this->info('DRY-RUN : aucune écriture effectuée.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm('Cette opération est irréversible. Confirmer l\'anonymisation ?')) {
            $this->warn('Annulé.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employee, $changes, $oldStatus, $photoPath): void {
            $employee->update($changes);

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
            }
        });

        $this->info('Données personnelles anonymisées ; historique de paie conservé (obligation légale). Audit tracé.');

        return self::SUCCESS;
    }
}
