<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * S-1 (#1661) — Purge automatique des templates biométriques expirés.
 *
 * Contexte : la politique de rétention documentaire (RGPD / Loi 18-07)
 * impose une durée de conservation aux templates biométriques (kiosk/mobile).
 * Les références (`biometric_*_reference_path`, `biometric_enrollment_requests.*_reference_path`)
 * pouvaient rester indéfiniment en base.
 *
 * Règle de rétention (docs/security/POLITIQUE_RETENTION_DOCUMENTS.md §Biométrie) :
 *   durée = 24 mois (config security.biometric.retention_months) APRÈS la date
 *   de référence = max(fin de contrat, date de consentement). Un template est
 *   purgé si date de référence + durée < maintenant.
 *
 * Traitement :
 *   - par tenant (schéma tenant via TenantManager::withinTenant) ;
 *   - nullifie les chemins de référence + désactive les flags biométriques ;
 *   - supprime physiquement les fichiers (storage/app/biometrics/...) ;
 *   - trace chaque purge dans `audit_logs` (qui/quoi/quand + compteurs) ;
 *   - `--dry-run` : rapport sans aucune écriture.
 *
 * Usage :
 *   php artisan biometric:purge-expired [--company={id}] [--months={N}] [--dry-run]
 */
class BiometricPurgeExpiredCommand extends Command
{
    protected $signature = 'biometric:purge-expired
        {--company= : ID de la société (tenant) cible — tous les tenants si absent}
        {--months= : Durée de rétention en mois (défaut : config security.biometric.retention_months)}
        {--dry-run : Affiche les actions sans rien écrire}';

    protected $description = 'Purge les templates biométriques expirés (rétention RGPD/Loi 18-07), tracée dans audit_logs';

    public function handle(TenantManager $tenantManager): int
    {
        $months = (int) ($this->option('months') ?: config('security.biometric.retention_months', 24));
        $months = max(1, $months);
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');

        $companies = Company::query()
            ->when($companyId !== null && $companyId !== '', fn ($query) => $query->where('id', $companyId))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->error('Aucune société cible.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Purge biométrique — rétention %d mois%s, %d société(s).',
            $months,
            $dryRun ? ' [DRY-RUN]' : '',
            $companies->count()
        ));

        $totals = ['employees' => 0, 'requests' => 0, 'files' => 0, 'audited' => 0];

        foreach ($companies as $company) {
            $result = $tenantManager->withinTenant($company, function () use ($months, $dryRun): array {
                return $this->purgeTenant($months, $dryRun);
            });

            $this->line(sprintf(
                '  société #%s : %d employé(s), %d demande(s) d\'enrôlement, %d fichier(s)%s',
                $company->id,
                $result['employees'],
                $result['requests'],
                $result['files'],
                $dryRun ? ' (simulé)' : ''
            ));

            foreach (['employees', 'requests', 'files', 'audited'] as $key) {
                $totals[$key] += $result[$key];
            }
        }

        $this->info(sprintf(
            'Terminé : %d employé(s), %d demande(s), %d fichier(s) purgés%s%s.',
            $totals['employees'],
            $totals['requests'],
            $totals['files'],
            $totals['audited'] > 0 ? ", {$totals['audited']} trace(s) d'audit" : '',
            $dryRun ? ' (dry-run — aucune écriture)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Purge les templates expirés d'un tenant (schéma déjà actif).
     *
     * @return array{employees: int, requests: int, files: int, audited: int}
     */
    private function purgeTenant(int $months, bool $dryRun): array
    {
        $cutoff = now()->subMonths($months);
        $companyId = currentCompany()->id;

        // ── Employés : templates expirés ─────────────────────────────────────
        $expiredEmployees = Employee::query()
            ->where(function ($query) {
                $query->whereNotNull('biometric_face_reference_path')
                    ->orWhereNotNull('biometric_fingerprint_reference_path');
            })
            ->get()
            ->filter(fn (Employee $employee): bool => $this->isExpired($employee, $cutoff));

        $employeeIds = $expiredEmployees->pluck('id')->all();

        // ── Demandes d'enrôlement : références expirées ──────────────────────
        $expiredRequests = BiometricEnrollmentRequest::query()
            ->where(function ($query) {
                $query->whereNotNull('requested_face_reference_path')
                    ->orWhereNotNull('requested_fingerprint_reference_path');
            })
            ->whereRaw('COALESCE(submitted_at, created_at) < ?', [$cutoff->toDateTimeString()])
            ->get();

        $requestIds = $expiredRequests->pluck('id')->all();
        $filesToDelete = [];

        if (! $dryRun && ($employeeIds !== [] || $requestIds !== [])) {
            DB::transaction(function () use ($employeeIds, $requestIds): void {
                if ($employeeIds !== []) {
                    Employee::query()->whereIn('id', $employeeIds)->update([
                        'biometric_face_reference_path' => null,
                        'biometric_fingerprint_reference_path' => null,
                        'biometric_face_enabled' => false,
                        'biometric_fingerprint_enabled' => false,
                    ]);
                }

                if ($requestIds !== []) {
                    BiometricEnrollmentRequest::query()->whereIn('id', $requestIds)->update([
                        'requested_face_reference_path' => null,
                        'requested_fingerprint_reference_path' => null,
                    ]);
                }
            });
        }

        // Fichiers physiques : suppression après purge DB (dry-run = simulée).
        foreach ($expiredEmployees as $employee) {
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

        if (! $dryRun && $filesToDelete !== []) {
            foreach ($filesToDelete as $path) {
                Storage::disk('local')->delete($path);
            }
        }

        // ── Trace d'audit (une par tenant, uniquement si purge réelle) ───────
        $audited = 0;
        if (! $dryRun && ($employeeIds !== [] || $requestIds !== [])) {
            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => null,
                'action' => 'biometric_templates_purged',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => null,
                'metadata' => [
                    'category' => 'biometric_retention',
                    'retention_months' => $months,
                    'cutoff' => $cutoff->toDateString(),
                    'employees_purged' => count($employeeIds),
                    'requests_purged' => count($requestIds),
                    'files_deleted' => count($filesToDelete),
                    'legal_basis' => 'RGPD art. 5.1.e / Loi 18-07 (consentement)',
                ],
            ]);
            $audited = 1;
        }

        return [
            'employees' => count($employeeIds),
            'requests' => count($requestIds),
            'files' => count($filesToDelete),
            'audited' => $audited,
        ];
    }

    /**
     * Un template employé est expiré si date de référence + rétention < now,
     * avec date de référence = max(fin de contrat, consentement biométrique).
     */
    private function isExpired(Employee $employee, Carbon $cutoff): bool
    {
        $reference = null;

        $consent = $employee->biometric_consent_at;
        if ($consent instanceof Carbon) {
            $reference = $consent;
        }

        $contractEnd = $employee->contract_end;
        if ($contractEnd instanceof Carbon && ($reference === null || $contractEnd->greaterThan($reference))) {
            $reference = $contractEnd;
        }

        return $reference !== null && $reference->lessThan($cutoff);
    }
}
