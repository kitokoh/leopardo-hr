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
     * @param array<string, mixed> $data
     */
    public function create(Employee $manager, array $data): Payroll
    {
        $month = $this->toInt($data['period_month'] ?? null);
        $year = $this->toInt($data['period_year'] ?? null);

        if (Payroll::where('employee_id', $data['employee_id'])->where('period_month', $month)->where('period_year', $year)->exists()) {
            throw new PayrollPeriodConflictException($month, $year);
        }

        $net = $this->computeNet($data);

        return Payroll::create([
            'company_id' => $manager->company_id,
            'employee_id' => $data['employee_id'],
            'period_month' => $month,
            'period_year' => $year,
            'gross_salary' => $this->toFloat($data['gross_salary'] ?? null),
            'overtime_amount' => $this->toFloat($data['overtime_amount'] ?? null),
            'bonuses' => $this->toLineItems($data['bonuses'] ?? []),
            'deductions' => $this->toLineItems($data['deductions'] ?? []),
            'cotisations' => $this->toLineItems($data['cotisations'] ?? []),
            'ir_amount' => $this->toFloat($data['ir_amount'] ?? null),
            'advance_deduction' => $this->toFloat($data['advance_deduction'] ?? null),
            'absence_deduction' => $this->toFloat($data['absence_deduction'] ?? null),
            'penalty_deduction' => $this->toFloat($data['penalty_deduction'] ?? null),
            'net_salary' => max(0, $net),
            'status' => 'draft',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Payroll $payroll, array $data): Payroll
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException;
        }

        $payroll->fill($this->normalizePayrollData($data));
        $payroll->net_salary = max(0, $this->computeNet($payroll->toArray()));
        $payroll->save();

        $payroll->refresh();

        return $payroll;
    }

    public function validate(Payroll $payroll, Employee $validator): Payroll
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException;
        }

        DB::transaction(function () use ($payroll, $validator): void {
            $payroll->update(['status' => 'validated', 'validated_by' => $validator->id, 'validated_at' => Carbon::now()]);

            if ($payroll->advance_deduction > 0) {
                $remaining = $payroll->advance_deduction;
                foreach (SalaryAdvance::where('employee_id', $payroll->employee_id)->where('status', 'active')->orderBy('created_at')->lockForUpdate()->get() as $advance) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $deducted = min($remaining, $advance->amount_remaining);
                    $newRem = round($advance->amount_remaining - $deducted, 2);
                    $advance->update(['amount_remaining' => $newRem, 'status' => $newRem <= 0 ? 'repaid' : 'active']);
                    $remaining -= $deducted;
                }
            }
        });

        $payroll->refresh();

        return $payroll;
    }

    public function delete(Payroll $payroll): void
    {
        if ($payroll->status === 'validated') {
            throw new PayrollAlreadyValidatedException;
        }
        $payroll->delete();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function computeNet(array $data): float
    {
        return $this->toFloat($data['gross_salary'] ?? null)
            + $this->toFloat($data['overtime_amount'] ?? null)
            + $this->sumLineItems($data['bonuses'] ?? [])
            - $this->sumLineItems($data['deductions'] ?? [])
            - $this->sumLineItems($data['cotisations'] ?? [])
            - $this->toFloat($data['ir_amount'] ?? null)
            - $this->toFloat($data['advance_deduction'] ?? null)
            - $this->toFloat($data['absence_deduction'] ?? null)
            - $this->toFloat($data['penalty_deduction'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayrollData(array $data): array
    {
        foreach (['period_month', 'period_year'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->toInt($data[$key]);
            }
        }

        foreach ([
            'gross_salary',
            'overtime_amount',
            'ir_amount',
            'advance_deduction',
            'absence_deduction',
            'penalty_deduction',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->toFloat($data[$key]);
            }
        }

        foreach (['bonuses', 'deductions', 'cotisations'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->toLineItems($data[$key]);
            }
        }

        return $data;
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toLineItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_array($item)
        ));
    }

    private function sumLineItems(mixed $items): float
    {
        $total = 0.0;

        foreach ($this->toLineItems($items) as $item) {
            $total += $this->toFloat($item['amount'] ?? null);
        }

        return $total;
    }
}
