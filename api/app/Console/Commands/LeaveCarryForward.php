<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeaveAccrual;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCarryForward extends Command
{
    protected $signature = 'leave:carry-forward {--year= : Year to carry forward from (defaults to previous year)}';

    protected $description = 'Carry forward unused leave balances to the new year and expire old carry-forwards';

    public function handle(): int
    {
        $fromYear = (int) ($this->option('year') ?? (now()->year - 1));
        $toYear = $fromYear + 1;

        $this->info("Processing carry-forward from {$fromYear} to {$toYear}...");

        $policies = LeavePolicy::where('active', true)
            ->where('carry_forward', true)
            ->get();

        $carried = 0;
        $expired = 0;

        foreach ($policies as $policy) {
            $balances = LeaveBalance::where('company_id', $policy->company_id)
                ->where('absence_type_id', $policy->absence_type_id)
                ->where('year', $fromYear)
                ->get();

            foreach ($balances as $oldBalance) {
                try {
                    DB::transaction(function () use ($policy, $oldBalance, $toYear, &$carried): void {
                        $unused = max(0, $oldBalance->balance - $oldBalance->used);
                        if ($unused <= 0) {
                            return;
                        }

                        $maxCarry = $policy->max_carry_forward ?? $unused;
                        $carryAmount = min($unused, $maxCarry);

                        $newBalance = LeaveBalance::firstOrCreate(
                            [
                                'company_id' => $policy->company_id,
                                'employee_id' => $oldBalance->employee_id,
                                'absence_type_id' => $policy->absence_type_id,
                                'year' => $toYear,
                            ],
                            ['balance' => 0, 'used' => 0, 'pending' => 0]
                        );

                        $newBalance->increment('balance', $carryAmount);

                        LeaveAccrual::create([
                            'company_id' => $policy->company_id,
                            'employee_id' => $oldBalance->employee_id,
                            'leave_policy_id' => $policy->id,
                            'amount' => $carryAmount,
                            'type' => 'carry_forward',
                            'description' => "Report de {$carryAmount} jour(s) de ".($toYear - 1),
                            'effective_date' => now()->startOfYear()->toDateString(),
                        ]);

                        $carried++;
                    });
                } catch (\Throwable $e) {
                    Log::warning("Carry-forward failed for employee {$oldBalance->employee_id}: {$e->getMessage()}");
                }
            }
        }

        $this->expireOldCarryForwards($toYear, $expired);

        $this->info("Carry-forward complete: {$carried} balance(s) carried, {$expired} expired.");

        return self::SUCCESS;
    }

    private function expireOldCarryForwards(int $currentYear, int &$expired): void
    {
        $policies = LeavePolicy::where('active', true)
            ->where('carry_forward', true)
            ->whereNotNull('carry_forward_expiry_days')
            ->where('carry_forward_expiry_days', '>', 0)
            ->get();

        foreach ($policies as $policy) {
            $expiryDate = now()->startOfYear()->addDays($policy->carry_forward_expiry_days);

            if (now()->lt($expiryDate)) {
                continue;
            }

            $carryForwards = LeaveAccrual::where('leave_policy_id', $policy->id)
                ->where('type', 'carry_forward')
                ->where('effective_date', now()->startOfYear()->toDateString())
                ->whereNull('expired_at')
                ->get();

            foreach ($carryForwards as $accrual) {
                $balance = LeaveBalance::where('company_id', $accrual->company_id)
                    ->where('employee_id', $accrual->employee_id)
                    ->where('absence_type_id', $policy->absence_type_id)
                    ->where('year', $currentYear)
                    ->first();

                if ($balance) {
                    $deduction = min($accrual->amount, $balance->balance - $balance->used);
                    if ($deduction > 0) {
                        $balance->decrement('balance', $deduction);
                    }
                }

                $accrual->update(['expired_at' => now()]);
                $expired++;
            }
        }
    }
}
