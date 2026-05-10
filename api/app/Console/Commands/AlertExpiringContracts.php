<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AlertExpiringContracts extends Command
{
    protected $signature = 'contracts:alert-expiring';

    protected $description = 'Notify when contracts expire in 30, 15, or 7 days';

    public function handle(): int
    {
        $thresholds = [30, 15, 7];
        $total = 0;

        foreach ($thresholds as $days) {
            $contracts = Contract::query()
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', now()->addDays($days)->toDateString())
                ->with('employee:id,first_name,last_name,email')
                ->get();

            foreach ($contracts as $contract) {
                Log::info("Contract {$contract->reference} for {$contract->employee->first_name} {$contract->employee->last_name} expires in {$days} days.");
                $total++;
            }
        }

        $this->info("Found {$total} contract(s) expiring at alert thresholds.");

        return self::SUCCESS;
    }
}
