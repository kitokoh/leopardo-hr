<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\ToolPermissionPolicy;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;

class WriteActionRunner
{
    public function __construct(
        // #6533 — réutilise la matrice ai.tool_permissions (BC-23-D05) pour
        // les write-tools : rôle + permissions au moment de l'exécution.
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
    ) {}

    /**
     * Issue #5625 : liste statique des write tools qui ont un handler PHP.
     *
     * @return list<string>
     */
    public static function supportedWriteToolNames(): array
    {
        return [
            'create_absence',
            'approve_absence',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        $handler = $this->writeToolHandlers($companyId, $userId)[$toolName] ?? null;
        if ($handler === null) {
            return ['error' => "Write tool '{$toolName}' is not implemented."];
        }

        return $handler($arguments);
    }

    /**
     * Issue #5625 : source de vérité des write-tools — couplée au test de
     * couverture ToolRegistryCoverageTest (config ai.write_tools ⊆ ici, et
     * chaque outil ici doit être exposé dans ai_tool_registry).
     *
     * @return array<string, callable(array<string, mixed>): array<string, mixed>>
     */
    private function writeToolHandlers(string $companyId, int $userId): array
    {
        return [
            'create_absence' => fn (array $arguments): array => $this->createAbsence($companyId, $userId, $arguments),
            'approve_absence' => fn (array $arguments): array => $this->approveAbsence($companyId, $userId, $arguments),
        ];
    }

    /**
     * Noms des write-tools effectivement exécutables (issue #5625).
     *
     * @return list<string>
     */
    public function supportedWriteTools(): array
    {
        return array_keys($this->writeToolHandlers('', 0));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createAbsence(string $companyId, int $userId, array $arguments): array
    {
        $employeeId = $this->intArgument($arguments, 'employee_id', $userId);

        // #6533 — un employé ne peut créer une absence que pour lui-même ;
        // seul un manager peut viser un autre employé (miroir AbsencePolicy).
        if ($employeeId !== $userId && ! $this->actorIsManager($companyId, $userId)) {
            return ['error' => 'PERMISSION_DENIED: you may only create absences for yourself'];
        }

        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first();

        if ($employee === null) {
            return ['error' => 'Employee not found'];
        }

        $startDate = Carbon::parse($this->stringArgument($arguments, 'start_date', now()->toDateString()));
        $endDate = Carbon::parse($this->stringArgument($arguments, 'end_date', $startDate->toDateString()));
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
            'reason' => array_key_exists('reason', $arguments)
                ? ($this->stringArgument($arguments, 'reason', '') ?: null)
                : null,
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
        $absenceId = $this->intArgument($arguments, 'absence_id', 0);

        // #6533 — seule une personne avec le rôle manager peut approuver
        // (miroir de AbsencePolicy::approve : même company + isManager).
        if (! $this->actorIsManager($companyId, $userId)) {
            return ['error' => 'PERMISSION_DENIED: approval requires the manager role'];
        }

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
                ->where('id', $this->intArgument($arguments, 'absence_type_id', 0))
                ->first();
        }

        $code = array_key_exists('type', $arguments)
            ? $this->stringArgument($arguments, 'type', '')
            : '';
        if ($code !== '') {
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

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function intArgument(array $arguments, string $key, int $default): int
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function stringArgument(array $arguments, string $key, string $default): string
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * #6533 — l'acteur est-il manager ? (résolution de la matrice
     * ai.tool_permissions, miroir du REST).
     */
    private function actorIsManager(string $companyId, int $userId): bool
    {
        return $this->toolPermissionPolicy->resolveRole($userId, $companyId) === 'manager';
    }
}
