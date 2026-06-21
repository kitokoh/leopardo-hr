<?php

namespace App\Console\Commands;

use App\Models\PartnerClick;
use Illuminate\Console\Command;

class ArchiveGrowthClicksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'growth:archive-clicks {--days=90 : Keep clicks from the last X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old tracking clicks to keep the database fast';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning up partner clicks older than {$days} days ({$cutoff->toDateString()})...");

        $deleted = PartnerClick::where('clicked_at', '<', $cutoff)->delete();

        $this->info("Successfully removed {$deleted} old clicks.");

        return self::SUCCESS;
    }
}
