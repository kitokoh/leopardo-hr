<?php

declare(strict_types=1);

namespace App\AI;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class WriteActionRunner
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        return match ($toolName) {
            'create_absence' => $this->createAbsence($companyId, $userId, $arguments),
            'approve_absence' => $this->approveAbsence($companyId, $userId, $arguments),
            default => ['error' => "Write tool '{$toolName}' is not implemented."],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createAbsence(string $companyId, int $userId, array $arguments): array
    {
        $employeeId = (int) ($arguments['employee_id'] ?? $userId);
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first();

        if ($employee === null) {
            return ['error' => 'Employee not found'];
        }

        $startDate = Carbon::parse((string) ($arguments['start_date'] ?? now()->toDateString()));
        $endDate = Carbon::parse((string) ($arguments['end_date'] ?? $startDate->toDateString()));
        if ($endDate->lessThan($startDate)) {
            return ['error' => 'end_date must be on or after start_date'];
        }

        $absenceType = $this->resolveAbsenceType($companyId, $arguments);

        $absence = Absence::create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'absence_type_id' => $absenceType?->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days_count' => $startDate->diffInDays($endDate) + 1,
            'status' => 'pending',
            'reason' => isset($arguments['reason']) ? (string) $arguments['reason'] : null,
        ]);

        return [
            'absence_id' => $absence->id,
            'status' => $absence->status,
            'employee_id' => $employeeId,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function approveAbsence(string $companyId, int $userId, array $arguments): array
    {
        $absenceId = (int) ($arguments['absence_id'] ?? 0);
        $absence = Absence::query()
            ->where('company_id', $companyId)
            ->where('id', $absenceId)
            ->first();

        if ($absence === null) {
            return ['error' => 'Absence not found'];
        }

        if ($absence->status !== 'pending') {
            return ['error' => 'Absence is not pending approval'];
        }

        $absence->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);

        return [
            'absence_id' => $absence->id,
            'status' => $absence->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveAbsenceType(string $companyId, array $arguments): ?AbsenceType
    {
        if (isset($arguments['absence_type_id'])) {
            return AbsenceType::query()
                ->where('company_id', $companyId)
                ->where('id', (int) $arguments['absence_type_id'])
                ->first();
        }

        $code = isset($arguments['type']) ? (string) $arguments['type'] : null;
        if ($code !== null && $code !== '') {
            $byCode = AbsenceType::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        return AbsenceType::query()->where('company_id', $companyId)->first();
    }
}
