<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use App\Modules\CRM\Domain\Models\CrmExportJob;
use App\Modules\CRM\Infrastructure\Services\CrmExportColumns;
use App\Modules\CRM\Infrastructure\Services\CrmExportSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Génération asynchrone d'un export CRM (issue #5729).
 *
 * - Colonnes allowlistées uniquement (manifeste CrmExportColumns).
 * - Écriture CSV sur le disque privé (jamais public).
 * - Progression reportée sur le job (0 → 50 → 100).
 * - Échec → job marqué failed avec message explicite (pas de retry infini).
 * - `expires_at` posé à la création : le téléchargement est refusé après
 *   expiration (URL/accès expirant).
 * - Audit `crm.export.completed` / `crm.export.failed`.
 */
final class ExportCrmDataJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $exportJobId) {}

    public function tenantCompanyId(): ?string
    {
        return CrmExportJob::query()->find($this->exportJobId)?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(CrmExportSource $source): void
    {
        $job = CrmExportJob::query()->find($this->exportJobId);
        if ($job === null) {
            return;
        }

        try {
            $job->forceFill(['status' => 'processing', 'progress' => 5])->save();

            $entity = $job->entity;
            if (! CrmExportEntity::isValid($entity)) {
                $this->failJob($job, 'CRM_EXPORT_ENTITY_INVALID', 'Entité d\'export inconnue.');

                return;
            }

            $source->assertAvailable($entity);
            $columns = $this->resolveColumns($entity, $job->columns);
            $rows = $source->query($entity, $job->filters ?? [])->get();

            $job->forceFill(['progress' => 50])->save();

            $csv = $this->buildCsv($columns, $rows);
            $fileName = sprintf('crm-%s-%s.csv', $entity, now()->format('Ymd-His'));
            $path = 'crm-exports/'.($job->company_id).'/'.$fileName;

            Storage::disk('private')->put($path, $csv);

            $job->forceFill([
                'status' => 'completed',
                'progress' => 100,
                'file_path' => $path,
                'file_name' => $fileName,
                'completed_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addHours((int) config('crm.exports.ttl_hours', 24)),
            ])->save();

            Log::info('CRM export: terminé', [
                'export_job_id' => $job->id,
                'entity' => $entity,
                'rows' => $rows->count(),
            ]);
        } catch (Throwable $e) {
            $this->failJob($job, 'CRM_EXPORT_FAILED', substr($e->getMessage(), 0, 480));
            Log::error('CRM export: échec', [
                'export_job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>|null  $requestedColumns
     * @return array<string, string>
     */
    private function resolveColumns(string $entity, ?array $requestedColumns): array
    {
        $manifest = CrmExportColumns::manifest()[$entity] ?? [];
        if ($requestedColumns === null || $requestedColumns === []) {
            return $manifest;
        }

        $selected = [];
        foreach ($requestedColumns as $column) {
            if (isset($manifest[$column])) {
                $selected[$column] = $manifest[$column];
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, string>  $columns
     * @param  iterable<object>  $rows
     */
    private function buildCsv(array $columns, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir le flux CSV temporaire.');
        }

        fputcsv($handle, array_values($columns));

        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $column) {
                $value = $row->{$column} ?? null;
                $line[] = is_scalar($value) ? (string) $value : json_encode($value) ?: '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function failJob(CrmExportJob $job, string $code, string $message): void
    {
        $job->forceFill([
            'status' => 'failed',
            'error' => $code.': '.substr($message, 0, 450),
        ])->save();
    }
}
