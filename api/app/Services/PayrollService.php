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
    /**
     * Create a draft payroll for an employee for a given period.
     *
     * @throws PayrollPeriodConflictException
     */
    public function create(Employee $manager, array $data): Payroll
    {
        $employeeId  = $data['employee_id'];
        $month       = (int) $data['period_month'];
        $year        = (int) $data['period_year'];

        // Check for duplicate period
        $exists = Payroll::where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->exists();

        if ($exists) {
            throw new PayrollPeriodConflictException($month, $year);
        }

        $grossSalary      = (float) $data['gross_salary'];
        $overtimeAmount   = (float) ($data['overtime_amount'] ?? 0);
        $irAmount         = (float) ($data['ir_amount'] ?? 0);
        $advanceDeduction = (float) ($data['advance_deduction'] ?? 0);
        $absenceDeduction = (float) ($data['absence_deduction'] ?? 0);
        $penaltyDeduction = (float) ($data['penalty_deduction'] ?? 0);
        $bonuses          = $data['bonuses'] ?? [];
        $deductions       = $data['deductions'] ?? [];
        $cotisations      = $data['cotisations'] ?? [];

        $bonusTotal      = array_sum(array_column($bonuses, 'amount'));
        $deductionTotal  = array_sum(array_column($deductions, 'amount'));
        $cotisationTotal = array_sum(array_column($cotisations, 'amount'));

        $netSalary = $grossSalary
            + $overtimeAmount
            + $bonusTotal
            - $deductionTotal
            - $cotisationTotal
            - $irAmount
            - $advanceDeduction
            - $absenceDeduction
            - $penaltyDeduction;

        return Payroll::create([
            'company_id'        => $manager->company_id,
            'employee_id'       => $employeeId,
            'period_month'      => $month,
            'period_year'       => $year,
            'gross_salary'      => $grossSalary,
            'overtime_amount'   => $overtimeAmount,
            'bonuses'           => $bonuses,
            'deductions'        => $deductions,
            'cotisations'       => $cotisations,
            'ir_amount'         => $irAmount,
            'advance_deduction' => $advanceDeduction,
            'absence_deduction' => $absenceDeduction,
            'penalty_deduction' => $penaltyDeduction,
            'net_salary'        => max(0, $netSalary),
            'status'            => 'draft',
        ]);
    }

    /**
     * Update a draft payroll. Recalculates net_salary.
     *
     * @throws PayrollAlreadyValidatedException
     */
    public function update(Payroll $payroll, array $data): Payroll
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException();
        }

        $payroll->fill($data);

        // Recalculate net salary
        $bonusTotal      = array_sum(array_column($payroll->bonuses ?? [], 'amount'));
        $deductionTotal  = array_sum(array_column($payroll->deductions ?? [], 'amount'));
        $cotisationTotal = array_sum(array_column($payroll->cotisations ?? [], 'amount'));

        $netSalary = $payroll->gross_salary
            + $payroll->overtime_amount
            + $bonusTotal
            - $deductionTotal
            - $cotisationTotal
            - $payroll->ir_amount
            - $payroll->advance_deduction
            - $payroll->absence_deduction
            - $payroll->penalty_deduction;

        $payroll->net_salary = max(0, $netSalary);
        $payroll->save();

        return $payroll->fresh();
    }

    /**
     * Validate a draft payroll. Marks active salary advances as repaid if fully deducted.
     *
     * @throws PayrollAlreadyValidatedException
     */
    public function validate(Payroll $payroll, Employee $validator): Payroll
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException();
        }

        DB::transaction(function () use ($payroll, $validator): void {
            $payroll->update([
                'status'       => 'validated',
                'validated_by' => $validator->id,
                'validated_at' => Carbon::now(),
            ]);

            // If advance_deduction > 0, update active salary advances for this employee
            if ($payroll->advance_deduction > 0) {
                $this->processAdvanceDeductions($payroll);
            }
        });

        return $payroll->fresh();
    }

    /**
     * Delete a draft payroll.
     *
     * @throws PayrollAlreadyValidatedException
     */
    public function delete(Payroll $payroll): void
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException();
        }

        $payroll->delete();
    }

    /**
     * Process advance deductions when a payroll is validated.
     * Updates amount_remaining on active salary advances.
     */
    private function processAdvanceDeductions(Payroll $payroll): void
    {
        $remaining = $payroll->advance_deduction;

        $advances = SalaryAdvance::where('employee_id', $payroll->employee_id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $deducted = min($remaining, $advance->amount_remaining);
            $newRemaining = round($advance->amount_remaining - $deducted, 2);

            $advance->update([
                'amount_remaining' => $newRemaining,
                'status'           => $newRemaining <= 0 ? 'repaid' : 'active',
            ]);

            $remaining -= $deducted;
        }
    }
}
