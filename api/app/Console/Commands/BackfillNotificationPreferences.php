<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner;
use App\Support\SearchPathGuard;
use Illuminate\Console\Command;

class BackfillNotificationPreferences extends Command
{
    protected $signature = 'notifications:backfill-preferences
        {--company= : Limit backfill to one company UUID}
        {--dry-run : Count changes without writing}';

    protected $description = 'Create missing notification preferences for active employees.';

    public function handle(NotificationPreferenceProvisioner $provisioner): int
    {
        // Issue #9115 : le search_path est restaure a son etat d'entree dans un
        // finally. Cette commande peut tourner sur un worker qui enchaine
        // d'autres taches : sans restauration, le worker restait sur
        // `shared_tenants` et les taches suivantes ciblaient le mauvais schema.
        return SearchPathGuard::run('shared_tenants, public', function () use ($provisioner): int {
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
        });
    }
}
