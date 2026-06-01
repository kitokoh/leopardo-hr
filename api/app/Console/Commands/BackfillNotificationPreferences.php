<?php

namespace App\Console\Commands;

use App\Services\Communication\NotificationPreferenceProvisioner;
use Illuminate\Console\Command;

class BackfillNotificationPreferences extends Command
{
    protected $signature = 'notifications:backfill-preferences
        {--company= : Limit backfill to one company UUID}
        {--dry-run : Count changes without writing}';

    protected $description = 'Create missing notification preferences for active employees.';

    public function handle(NotificationPreferenceProvisioner $provisioner): int
    {
        $stats = $provisioner->backfill(
            companyId: is_string($this->option('company')) ? $this->option('company') : null,
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->line(json_encode([
            'status' => 'ok',
            'dry_run' => (bool) $this->option('dry-run'),
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
