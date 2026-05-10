<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveAccrual;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrueLeaveBalances extends Command
{
    protected $signature = 'leave:accrue {--force : Run even if not 1st of month}';

    protected $description = 'Accrue monthly leave balances based on active leave policies';

    public function handle(): int
    {
        $today = now();

        if ($today->day !== 1 && ! $this->option('force')) {
            $this->info('Skipped — accrual runs on 1st of month. Use --force to override.');

            return self::SUCCESS;
        }

        $year = (int) $today->format('Y');
        $policies = LeavePolicy::where('active', true)
            ->where('accrual_type', 'monthly')
            ->get();

        $count = 0;

        foreach ($policies as $policy) {
            $employees = Employee::where('company_id', $policy->company_id)->get();

            foreach ($employees as $employee) {
                try {
                    DB::transaction(function () use ($policy, $employee, $year, $today, &$count): void {
                        $balance = LeaveBalance::firstOrCreate(
                            [
                                'company_id' => $policy->company_id,
                                'employee_id' => $employee->id,
                                'absence_type_id' => $policy->absence_type_id,
                                'year' => $year,
                            ],
                            ['balance' => 0, 'used' => 0, 'pending' => 0]
                        );

                        if ($policy->max_balance && ($balance->balance + $policy->accrual_amount) > $policy->max_balance) {
                            return;
                        }

                        $balance->increment('balance', $policy->accrual_amount);

                        LeaveAccrual::create([
                            'company_id' => $policy->company_id,
                            'employee_id' => $employee->id,
                            'leave_policy_id' => $policy->id,
                            'amount' => $policy->accrual_amount,
                            'type' => 'accrual',
                            'description' => "Monthly accrual — {$today->format('F Y')}",
                            'effective_date' => $today->toDateString(),
                        ]);

                        $count++;
                    });
                } catch (\Throwable $e) {
                    Log::warning("Leave accrual failed for employee {$employee->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Accrued leave for {$count} employee-policy combinations.");

        return self::SUCCESS;
    }
}
