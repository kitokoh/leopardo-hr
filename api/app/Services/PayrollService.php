<?php

namespace App\Services;

use App\Exceptions\PayrollAlreadyValidatedException;
use App\Exceptions\PayrollPeriodConflictException;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryAdvance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function create(Employee $manager, array $data): Payroll
    {
        $month = (int) $data['period_month'];
        $year  = (int) $data['period_year'];

        if (Payroll::where('employee_id', $data['employee_id'])->where('period_month', $month)->where('period_year', $year)->exists()) {
            throw new PayrollPeriodConflictException($month, $year);
        }

        $net = $this->computeNet($data);

        return Payroll::create([
            'company_id'        => $manager->company_id,
            'employee_id'       => $data['employee_id'],
            'period_month'      => $month,
            'period_year'       => $year,
            'gross_salary'      => (float) $data['gross_salary'],
            'overtime_amount'   => (float) ($data['overtime_amount'] ?? 0),
            'bonuses'           => $data['bonuses'] ?? [],
            'deductions'        => $data['deductions'] ?? [],
            'cotisations'       => $data['cotisations'] ?? [],
            'ir_amount'         => (float) ($data['ir_amount'] ?? 0),
            'advance_deduction' => (float) ($data['advance_deduction'] ?? 0),
            'absence_deduction' => (float) ($data['absence_deduction'] ?? 0),
            'penalty_deduction' => (float) ($data['penalty_deduction'] ?? 0),
            'net_salary'        => max(0, $net),
            'status'            => 'draft',
        ]);
    }

    public function update(Payroll $payroll, array $data): Payroll
    {
        if ($payroll->status === 'validated') throw new PayrollAlreadyValidatedException();

        $payroll->fill($data);
        $payroll->net_salary = max(0, $this->computeNet($payroll->toArray()));
        $payroll->save();

        return $payroll->fresh();
    }

    public function validate(Payroll $payroll, Employee $validator): Payroll
    {
        if ($payroll->status === 'validated') throw new PayrollAlreadyValidatedException();

        DB::transaction(function () use ($payroll, $validator): void {
            $payroll->update(['status' => 'validated', 'validated_by' => $validator->id, 'validated_at' => Carbon::now()]);

            if ($payroll->advance_deduction > 0) {
                $remaining = $payroll->advance_deduction;
                foreach (SalaryAdvance::where('employee_id', $payroll->employee_id)->where('status', 'active')->orderBy('created_at')->lockForUpdate()->get() as $advance) {
                    if ($remaining <= 0) break;
                    $deducted = min($remaining, $advance->amount_remaining);
                    $newRem   = round($advance->amount_remaining - $deducted, 2);
                    $advance->update(['amount_remaining' => $newRem, 'status' => $newRem <= 0 ? 'repaid' : 'active']);
                    $remaining -= $deducted;
                }
            }
        });

        return $payroll->fresh();
    }

    public function delete(Payroll $payroll): void
    {
        if ($payroll->status === 'validated') throw new PayrollAlreadyValidatedException();
        $payroll->delete();
    }

    private function computeNet(array $data): float
    {
        return (float) ($data['gross_salary'] ?? 0)
            + (float) ($data['overtime_amount'] ?? 0)
            + array_sum(array_column($data['bonuses'] ?? [], 'amount'))
            - array_sum(array_column($data['deductions'] ?? [], 'amount'))
            - array_sum(array_column($data['cotisations'] ?? [], 'amount'))
            - (float) ($data['ir_amount'] ?? 0)
            - (float) ($data['advance_deduction'] ?? 0)
            - (float) ($data['absence_deduction'] ?? 0)
            - (float) ($data['penalty_deduction'] ?? 0);
    }
}
