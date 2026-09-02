<?php

declare(strict_types=1);

namespace App\Modules\CRM\Console\Commands;

use App\Modules\CRM\Domain\Models\CrmExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Purge des exports CRM expirés/anciens (issue #5729).
 *
 * - Supprime les fichiers du disque privé des jobs `expired` ou terminés
 *   depuis plus de `retention_days` jours.
 * - Marque `expired` les jobs `completed` dont `expires_at` est passé.
 * - Audit de la purge.
 */
final class CleanupCrmExports extends Command
{
    protected $signature = 'crm:exports:cleanup {--retention-days=7 : conservation des fichiers apres expiration}';

    protected $description = 'Purge les exports CRM expires et leurs fichiers (disque prive).';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('retention-days');

        $expiredCount = CrmExportJob::query()
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $cutoff = now()->subDays($retentionDays);
        $stale = CrmExportJob::query()
            ->whereIn('status', ['completed', 'expired'])
            ->where('updated_at', '<', $cutoff)
            ->get();

        $deletedFiles = 0;
        foreach ($stale as $job) {
            if ($job->file_path !== null && Storage::disk('private')->exists($job->file_path)) {
                Storage::disk('private')->delete($job->file_path);
                $deletedFiles++;
            }
            $job->forceFill(['status' => 'expired', 'file_path' => null])->save();
        }

        Log::info('CRM exports: purge executee', [
            'expired' => $expiredCount,
            'stale_jobs' => $stale->count(),
            'deleted_files' => $deletedFiles,
        ]);

        $this->info("CRM exports purges : {$expiredCount} expires, {$stale->count()} fichiers nettoyes.");

        return self::SUCCESS;
    }
}
