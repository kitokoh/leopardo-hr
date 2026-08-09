<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Programme FOCUS — F-18 (#1548) : droit à l'effacement (RGPD).
 *
 * Anonymise les données personnelles d'un employé (Loi 18-07 / RGPD) tout en
 * CONSERVANT l'historique de paie (obligation légale de conservation : DZ 10
 * ans) : les identifiants personnels (nom, email, téléphones, adresse, dates,
 * biométrie, photo, contact d'urgence) sont effacés/remplacés, les données
 * économiques (bulletins, runs, salaires agrégés) restent exploitables sans
 * permettre la ré-identification.
 *
 * Usage : php artisan gdpr:anonymize-employee {employee_id}
 */
class GdprAnonymizeEmployeeCommand extends Command
{
    protected $signature = 'gdpr:anonymize-employee {employee : ID de l\'employé à anonymiser}';

    protected $description = 'Anonymise les données personnelles d\'un employé (RGPD/Loi 18-07) en conservant l\'historique de paie';

    public function handle(): int
    {
        $employee = Employee::query()->find((int) $this->argument('employee'));

        if (! $employee instanceof Employee) {
            $this->error('Employé introuvable.');

            return self::FAILURE;
        }

        $this->warn("Anonymisation de l'employé #{$employee->id} ({$employee->email})…");

        DB::transaction(function () use ($employee): void {
            $employee->update([
                'first_name' => 'Anonymisé',
                'last_name' => 'Employé '.$employee->id,
                'email' => 'anonyme-'.$employee->id.'@anonyme.local',
                'personal_phone' => null,
                'phone' => null,
                'address_line' => null,
                'postal_code' => null,
                'password_hash' => null,
                'date_of_birth' => null,
                'place_of_birth' => null,
                'nationality' => null,
                'marital_status' => null,
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
            ]);

            AuditLog::create([
                'company_id' => $employee->company_id,
                'user_id' => $employee->id,
                'action' => 'gdpr_employee_anonymized',
                'auditable_type' => $employee->getMorphClass(),
                'auditable_id' => $employee->id,
                'old_values' => ['status' => 'active'],
                'new_values' => ['status' => 'archived', 'pii' => 'anonymized'],
                'metadata' => ['legal_basis' => 'RGPD art. 17 / Loi 18-07', 'payroll_history_kept' => true],
            ]);
        });

        $this->info('Données personnelles anonymisées ; historique de paie conservé (obligation légale). Audit tracé.');

        return self::SUCCESS;
    }
}
