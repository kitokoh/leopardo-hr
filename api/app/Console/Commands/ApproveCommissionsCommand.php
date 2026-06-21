<?php

namespace App\Console\Commands;

use App\Services\PartnerService;
use Illuminate\Console\Command;

class ApproveCommissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'growth:approve-commissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve pending commissions after 14 days delay';

    /**
     * Execute the console command.
     */
    public function handle(PartnerService $partnerService): int
    {
        $this->info('Starting commission approval process...');

        $count = $partnerService->approvePendingCommissions();

        $this->info("Successfully approved {$count} commissions.");

        return self::SUCCESS;
    }
}
