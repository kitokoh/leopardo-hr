<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Infrastructure\Services\PiiLifecycleService;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Console\Command;

/**
 * Programme FOCUS — F-18 (#1548) : droit à l'effacement (RGPD).
 *
 * Anonymise les données personnelles d'un employé (Loi 18-07 / RGPD) tout en
 * CONSERVANT l'historique de paie (obligation légale de conservation : DZ 10
 * ans). La logique d'anonymisation vit dans {@see PiiLifecycleService}
 * (MAT-011 #5869) ; cette commande ajoute la garde tenant, le dry-run et la
 * confirmation interactive (la confirmation précède TOUJOURS l'écriture).
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

    public function handle(PiiLifecycleService $piiLifecycle): int
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

        // Idempotence : un employé déjà anonymisé ne doit pas être re-traité.
        if ($employee->status === 'archived' && $employee->first_name === 'Anonymisé') {
            $this->warn("Employé #{$employee->id} déjà anonymisé — aucune action.");

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            'Anonymisation de l\'employé #%d (%s) — société #%s%s',
            $employee->id,
            $employee->email,
            $employee->company_id,
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        if (! $dryRun && ! (bool) $this->option('force') && ! $this->confirm('Cette opération est irréversible. Confirmer l\'anonymisation ?')) {
            $this->warn('Annulé.');

            return self::SUCCESS;
        }

        $result = $piiLifecycle->anonymize($employee, $dryRun);

        $this->line('Champs PII effacés : '.$result['fields_changed'].' — biométrie (références + consentement), photo, banque, zkteco_id inclus.');

        if ($dryRun) {
            $this->info('DRY-RUN : aucune écriture effectuée.');

            return self::SUCCESS;
        }

        $this->info('Données personnelles anonymisées ; historique de paie conservé (obligation légale). Audit tracé.');

        return self::SUCCESS;
    }
}
