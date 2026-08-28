<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Contracts\CrmImportRowPersisterInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #5714 — Commit asynchrone d'un import CSV CRM.
 *
 * Persiste les lignes validées (stockées à la preview) dans le tenant
 * courant, dans une transaction. Chaque ligne est persistée via le port
 * {@see CrmImportRowPersisterInterface} ; les erreurs de ligne sont
 * collectées sans interrompre le lot (résultat détaillé dans la session).
 *
 * Implémente `TenantScopedJob` (même convention que les jobs métier du
 * dépôt) : le middleware `EnsureTenantContext` établit `search_path` +
 * `current_company` avant exécution — indispensable en mode tenancy
 * « schema », et sans impact en mode « shared » par défaut.
 */
class CrmImportCommitJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 120;

    public function __construct(
        public readonly int $importId,
        public readonly string $companyId,
        public readonly int $actorId,
    ) {
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function handle(
        CrmImportRepositoryInterface $imports,
        CrmImportRowPersisterInterface $persister,
        TenantManager $tenants,
    ): void {
        $company = Company::query()->find($this->companyId);

        if (! $company instanceof Company) {
            Log::warning('[CrmImportCommitJob] entreprise introuvable', [
                'import_id' => $this->importId,
                'company_id' => $this->companyId,
            ]);

            return;
        }

        // Établit search_path + current_company de façon explicite : les
        // modèles cibles portent BelongsToCompany (scope global + auto-fill
        // company_id). En queue sync (tests) le middleware EnsureTenantContext
        // n'est pas garanti — withinTenant couvre les deux cas.
        $tenants->withinTenant($company, function () use ($imports, $persister): void {
            $import = $imports->findForCompany($this->importId, $this->companyId);

            if ($import === null) {
                Log::warning('[CrmImportCommitJob] session introuvable ou hors tenant', [
                    'import_id' => $this->importId,
                    'company_id' => $this->companyId,
                ]);

                return;
            }

            $entityType = $import->entity_type;

            if (! $persister->supports($entityType)) {
                $imports->markFailed($import, ['error' => 'unsupported_entity']);

                return;
            }

            $rowErrors = [];
            $persisted = 0;

            DB::transaction(function () use ($import, $entityType, $persister, &$rowErrors, &$persisted): void {
                foreach ($import->raw_rows as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    // Le parser produit des chaînes ; garantie de typage pour
                    // le port de persistance (PHPStan strict level 8).
                    $safeRow = array_map(static fn (mixed $value): string => (string) $value, $row);

                    try {
                        $persister->persistRow($entityType, $safeRow);
                        $persisted++;
                    } catch (Throwable $e) {
                        $rowErrors[] = [
                            'row' => $index + 2, // en-tête = ligne 1
                            'message' => $e->getMessage(),
                        ];

                        if (count($rowErrors) >= 50) {
                            break; // borne : ne pas avaler des milliers d'erreurs
                        }
                    }
                }
            });

            if ($persisted < 1) {
                $imports->markFailed($import, ['error' => 'all_rows_failed', 'row_errors' => $rowErrors]);
                Log::error('[CrmImportCommitJob] aucun enregistrement persisté', [
                    'import_id' => $this->importId,
                    'company_id' => $this->companyId,
                ]);

                return;
            }

            $imports->markCommitted($import, $this->actorId, [
                'persisted' => $persisted,
                'row_errors' => $rowErrors,
            ]);

            AuditLog::create([
                'company_id' => $this->companyId,
                'user_id' => $this->actorId,
                'action' => 'crm.import.committed',
                'module' => 'crm',
                'auditable_type' => $import->getMorphClass(),
                'auditable_id' => $import->id,
                'new_values' => [
                    'persisted' => $persisted,
                    'row_errors' => count($rowErrors),
                ],
            ]);
        });
    }

    public function failed(Throwable $e): void
    {
        Log::error('[CrmImportCommitJob] échec définitif', [
            'import_id' => $this->importId,
            'company_id' => $this->companyId,
            'error' => $e->getMessage(),
        ]);
    }
}
