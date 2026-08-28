<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Contracts\CrmImportRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Enums\CrmImportStatus;
use App\Modules\CRM\Domain\Exceptions\CrmImportException;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Infrastructure\Jobs\CrmImportCommitJob;
use Illuminate\Http\UploadedFile;

/**
 * #5714 — Orchestrateur du cycle de vie d'un import CSV CRM.
 *
 * preview  → parse + validation structurelle, AUCUNE écriture cible ;
 *            la session stocke les lignes parsées (commit différé).
 * commit   → claim atomique puis dispatch du job de persistance
 *            (idempotent : une session committed/cancelled refuse).
 * cancel   → annulation avant commit (claim atomique).
 *
 * Toute action sensible est auditée (AuditLog, module `crm`, préfixe
 * `crm.import.*`) — critère « audit d'import ».
 */
final class CrmImportService
{
    public const PREVIEW_SAMPLE_SIZE = 5;

    public function __construct(
        private readonly CrmImportRepositoryInterface $imports,
        private readonly CsvParser $parser,
    ) {
    }

    /**
     * Upload + preview : parse le fichier et crée la session en statut
     * `previewed`, sans écrire dans les tables cibles.
     */
    public function preview(
        UploadedFile $file,
        CrmImportEntityType $entityType,
        Employee $actor,
    ): CrmImport {
        $this->parser->validateUpload($file);

        $result = $this->parser->parse($file->getRealPath(), $entityType);

        $sample = array_slice($result->rows, 0, self::PREVIEW_SAMPLE_SIZE);
        $maskedSample = array_map(
            fn (array $row): array => $this->maskSensitive($row, $entityType),
            $sample
        );

        $import = $this->imports->createForCompany($actor->company_id, $actor->id, [
            'entity_type' => $entityType,
            'filename' => $file->getClientOriginalName(),
            'status' => 'previewed',
            'total_rows' => $result->validCount + $result->errorCount,
            'valid_rows' => $result->validCount,
            'error_rows' => $result->errorCount,
            'columns' => $result->columns,
            'preview_data' => $maskedSample,
            'errors' => $result->errors,
            'raw_rows' => $result->rows,
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'crm.import.previewed',
            'module' => 'crm',
            'auditable_type' => $import->getMorphClass(),
            'auditable_id' => $import->id,
            'new_values' => [
                'entity_type' => $entityType->value,
                'filename' => $import->filename,
                'valid_rows' => $result->validCount,
                'error_rows' => $result->errorCount,
            ],
        ]);

        return $import;
    }

    /**
     * Commit explicite : claim atomique puis dispatch du job de
     * persistance. Idempotent — un second commit est rejeté (409).
     */
    public function commit(int $importId, Employee $actor): CrmImport
    {
        $import = $this->imports->findForCompany($importId, $actor->company_id)
            ?? throw CrmImportException::notFound();

        if (! $this->imports->claimCommit($import)) {
            if ($import->status === CrmImportStatus::Cancelled) {
                throw CrmImportException::alreadyCancelled();
            }

            throw CrmImportException::alreadyCommitted();
        }

        if ($import->valid_rows < 1) {
            $this->imports->markFailed($import, ['error' => 'no_valid_rows']);

            throw CrmImportException::noValidRows();
        }

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'crm.import.commit_requested',
            'module' => 'crm',
            'auditable_type' => $import->getMorphClass(),
            'auditable_id' => $import->id,
            'new_values' => ['valid_rows' => $import->valid_rows],
        ]);

        CrmImportCommitJob::dispatch($import->id, $actor->company_id, $actor->id);

        return $this->imports->findForCompany($importId, $actor->company_id) ?? $import;
    }

    /**
     * Annulation avant commit (claim atomique).
     */
    public function cancel(int $importId, Employee $actor): CrmImport
    {
        $import = $this->imports->findForCompany($importId, $actor->company_id)
            ?? throw CrmImportException::notFound();

        if (! $this->imports->claimCancel($import)) {
            throw CrmImportException::alreadyCancelled();
        }

        // Statut re-posé explicitement : l'attribut du modèle est périmé
        // (le claim est passé par un UPDATE conditionnel brut).
        $import->forceFill([
            'status' => CrmImportStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ])->save();

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'crm.import.cancelled',
            'module' => 'crm',
            'auditable_type' => $import->getMorphClass(),
            'auditable_id' => $import->id,
            'new_values' => ['status' => 'cancelled'],
        ]);

        return $import;
    }

    /**
     * Masque les colonnes PII dans l'aperçu renvoyé au client (jamais de
     * PII en clair dans une réponse non autorisée, cf. #5713).
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function maskSensitive(array $row, CrmImportEntityType $entityType): array
    {
        foreach ($entityType->sensitiveColumns() as $column) {
            if (isset($row[$column]) && $row[$column] !== '') {
                $row[$column] = $this->mask($row[$column]);
            }
        }

        return $row;
    }

    private function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 3) {
            return '***';
        }

        return mb_substr($value, 0, 3).'***';
    }
}
