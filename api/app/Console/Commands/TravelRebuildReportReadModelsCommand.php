<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\TravelAgency\Application\Actions\RebuildReportReadModelsAction;
use Illuminate\Console\Command;

/**
 * travel:rebuild-report-readmodels — Recalcule les read models des rapports
 * TravelAgency (TRAVEL-506, issue #6076).
 *
 * Idempotent : delete + rebuild par tenant dans une transaction — la
 * reprise donne un état identique.
 *
 * Usage : php artisan travel:rebuild-report-readmodels
 */
class TravelRebuildReportReadModelsCommand extends Command
{
    protected $signature = 'travel:rebuild-report-readmodels';

    protected $description = 'Recalcule les read models de rapports TravelAgency (ventes journalières + occupation par trajet).';

    public function handle(RebuildReportReadModelsAction $action): int
    {
        $count = $action->execute();

        $this->info("Read models TravelAgency recalculés : {$count} lignes.");

        return self::SUCCESS;
    }
}
