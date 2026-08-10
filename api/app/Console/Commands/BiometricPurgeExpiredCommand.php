<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Spec S-1 (#1661) — Biométrie : politique de rétention + purge automatique (RGPD, suite #1548).
 *
 * Purge (nullifie) les références de templates biométriques (visage/empreinte)
 * dont la durée de conservation est dépassée, conformément à
 * docs/security/POLITIQUE_RETENTION_DOCUMENTS.md (v2, §2.1) :
 *   - employé avec fin de contrat renseignée : contrat terminé depuis plus de
 *     `--months` mois (défaut : config `security.biometric.retention_months` = 24) ;
 *   - employé sans fin de contrat : consentement biométrique datant de plus de
 *     `--months` mois (proxy « dernier usage » lorsqu'aucun contrat n'existe).
 *
 * La purge est effectuée tenant par tenant :
 *   - nullifie `biometric_*_reference_path` (employés) et
 *     `biometric_enrollment_requests.*_reference_path` (demandes d'enrôlement) ;
 *   - désactive les flags `*_enabled` des employés concernés ;
 *   - supprime les fichiers physiques (`storage/app/biometrics/...`) ;
 *   - trace chaque société traitée dans `audit_logs`
 *     (action `biometric_templates_purged`) ;
 *   - expose un mode `--dry-run` sans aucune écriture.
 *
 * Usage :
 *   php artisan biometric:purge-expired [--months=24] [--company=<uuid>] [--dry-run]
 */
class BiometricPurgeExpiredCommand extends Command
{
    protected $signature = 'biometric:purge-expired
        {--months= : Duree de retention des templates en mois (defaut : config security.biometric.retention_months)}
        {--company= : UUID de la societe (tenant) cible — sinon toutes les societes}
        {--dry-run : Affiche les purges prevues sans rien ecrire}';

    protected $description = 'Purge les references de templates biometriques expirees (retention RGPD / Loi 18-07)';

    public function handle(TenantManager $tenantManager): int
    {
        $monthsOption = $this->option('months');
        $months = $monthsOption !== null && $monthsOption !== ''
            ? (int) $monthsOption
            : (int) config('security.biometric.retention_months', 24);
        $months = max(1, $months);
        $dryRun = (bool) $this->option('dry-run');
        $companyId = (string) ($this->option('company') ?? '');

        $cutoff = now()->subMonths($months);

        if ($companyId !== '') {
            $company = Company::query()->find($companyId);
            if (! $company instanceof Company) {
                $this->error("Societe introuvable pour --company={$companyId}.");

                return self::FAILURE;
            }
            $companies = [$company];
        } else {
            $companies = Company::query()->orderBy('name')->get()->all();
        }

        $this->info(sprintf(
            'Purge des references biometriques expirees avant le %s (%d mois%s)%s — %d societe(s) a traiter.',
            $cutoff->toDateString(),
            $months,
            $monthsOption === null || $monthsOption === '' ? ' [config]' : '',
            $dryRun ? ' [DRY-RUN]' : '',
            count($companies)
        ));

        $totalPurged = 0;

        foreach ($companies as $company) {
            $totalPurged += $tenantManager->withinTenant($company, function () use ($company, $cutoff, $months, $dryRun): int {
                return $this->purgeTenant($company, $cutoff, $months, $dryRun);
            });
        }

        $this->info("Total des employes traites : {$totalPurged}.");

        if ($dryRun) {
            $this->warn('DRY-RUN : aucune ecriture effectuee.');
        }

        return self::SUCCESS;
    }

    /**
     * Purge les templates expirés d'un tenant et trace l'opération.
     */
    private function purgeTenant(Company $company, Carbon $cutoff, int $months, bool $dryRun): int
    {
        $expired = Employee::query()
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNotNull('contract_end')
                    ->whereDate('contract_end', '<', $cutoff)
                    ->orWhere(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNull('contract_end')
                            ->whereNotNull('biometric_consent_at')
                            ->where('biometric_consent_at', '<', $cutoff);
                    });
            })
            ->where(function (Builder $query): void {
                $query->whereNotNull('biometric_face_reference_path')
                    ->orWhereNotNull('biometric_fingerprint_reference_path');
            })
            ->get();

        if ($expired->isEmpty()) {
            $this->line(sprintf('  [%s] Aucun template expire.', $company->name));

            return 0;
        }

        $this->line(sprintf(
            '  [%s] %d employe(s) avec templates biometriques expire(s).',
            $company->name,
            $expired->count()
        ));

        foreach ($expired as $employee) {
            $this->line(sprintf(
                '    - employe #%d (contrat: %s | consentement: %s)',
                $employee->id,
                $employee->contract_end?->toDateString() ?? '—',
                $employee->biometric_consent_at?->toDateTimeString() ?? '—'
            ));
        }

        $employeeIds = $expired->pluck('id')->all();

        // Demandes d'enrôlement biométrique expirées (chemins de référence).
        $expiredRequests = BiometricEnrollmentRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where(function (Builder $query): void {
                $query->whereNotNull('requested_face_reference_path')
                    ->orWhereNotNull('requested_fingerprint_reference_path');
            })
            ->get();

        // Chemins physiques à supprimer (templates kiosk/mobile en storage local).
        $filesToDelete = [];
        foreach ($expired as $employee) {
            foreach (['biometric_face_reference_path', 'biometric_fingerprint_reference_path'] as $column) {
                $path = $employee->{$column};
                if (is_string($path) && $path !== '') {
                    $filesToDelete[] = $path;
                }
            }
        }
        foreach ($expiredRequests as $request) {
            foreach (['requested_face_reference_path', 'requested_fingerprint_reference_path'] as $column) {
                $path = $request->{$column};
                if (is_string($path) && $path !== '') {
                    $filesToDelete[] = $path;
                }
            }
        }
        $filesToDelete = array_values(array_unique($filesToDelete));

        if ($dryRun) {
            $this->line(sprintf(
                '    (dry-run) %d fichier(s) seraient supprimes.',
                count($filesToDelete)
            ));

            return 0;
        }

        DB::transaction(function () use ($employeeIds): void {
            Employee::query()->whereIn('id', $employeeIds)->update([
                'biometric_face_reference_path' => null,
                'biometric_fingerprint_reference_path' => null,
                'biometric_face_enabled' => false,
                'biometric_fingerprint_enabled' => false,
            ]);

            // Purge des demandes d'enrôlement biométrique des employés concernés
            // (chemins de référence uniquement — l'historique de demande reste).
            BiometricEnrollmentRequest::query()
                ->whereIn('employee_id', $employeeIds)
                ->update([
                    'requested_face_reference_path' => null,
                    'requested_fingerprint_reference_path' => null,
                ]);
        });

        if ($filesToDelete !== []) {
            foreach ($filesToDelete as $path) {
                Storage::disk('local')->delete($path);
            }
        }

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => null,
            'action' => 'biometric_templates_purged',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => ['purged_employees' => count($employeeIds)],
            'new_values' => ['status' => 'reference_paths_nullified'],
            'metadata' => [
                'retention_months' => $months,
                'cutoff' => $cutoff->toDateString(),
                'employees_purged' => count($employeeIds),
                'requests_purged' => $expiredRequests->count(),
                'files_deleted' => count($filesToDelete),
                'legal_basis' => 'RGPD art. 9 / Loi 18-07 (consentement)',
                'policy' => 'docs/security/POLITIQUE_RETENTION_DOCUMENTS.md',
            ],
        ]);

        $this->info(sprintf(
            '  [%s] %d employe(s) purges — %d fichier(s) supprimes — audit trace.',
            $company->name,
            count($employeeIds),
            count($filesToDelete)
        ));

        return count($employeeIds);
    }
}
