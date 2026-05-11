<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTrialExpiring extends Command
{
    protected $signature = 'billing:check-trials';

    protected $description = 'Notify companies whose trial expires in 3 days or less';

    public function handle(): int
    {
        $expiringTrials = Subscription::where('status', 'trial')
            ->where('current_period_end', '<=', now()->addDays(3))
            ->where('current_period_end', '>', now())
            ->with('company:id,name')
            ->get();

        foreach ($expiringTrials as $subscription) {
            $daysLeft = (int) now()->diffInDays($subscription->current_period_end, false);
            Log::info("Trial expiring: company={$subscription->company_id} days_left={$daysLeft}");

            $subscription->update([
                'metadata' => array_merge(
                    $subscription->metadata ?? [],
                    ['trial_warning_sent_at' => now()->toIso8601String()]
                ),
            ]);
        }

        $this->info("Processed {$expiringTrials->count()} expiring trial(s).");

        return self::SUCCESS;
    }
}
