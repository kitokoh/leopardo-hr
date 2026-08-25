<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Events\EmployeeDeparted;
use App\Modules\HR\Domain\Models\EmployeeDeparture;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Workflow de départ (issue #5324) — orchestration HR.
 *
 * HR enregistre le départ (dossier `employee_departures` + statut employé
 * `departed` + révocation d'accès) et ORCHESTRE les endpoints Payroll
 * (settlement, certificate) — il ne calcule JAMAIS (constitution §III).
 * L'exclusion des runs de paie est le gap G6, module Payroll.
 */
class DepartureService
{
    public function __construct(private readonly TenantCacheService $tenantCache) {}

    /**
     * Enregistre le départ d'un employé.
     *
     * @param  array<string, mixed>  $validated
     */
    public function registerDeparture(Employee $actor, Employee $employee, array $validated): EmployeeDeparture
    {
        if ($employee->status === 'departed' || $employee->status === 'archived') {
            throw new InvalidArgumentException('employees.already_departed');
        }

        $departureType = (string) ($validated['departure_type'] ?? '');
        if (! in_array($departureType, EmployeeDeparture::TYPES, true)) {
            throw new InvalidArgumentException('employees.departure_type_invalid');
        }

        return DB::transaction(function () use ($actor, $employee, $validated, $departureType): EmployeeDeparture {
            /** @var EmployeeDeparture $departure */
            $departure = EmployeeDeparture::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'departure_type' => $departureType,
                'reason' => $validated['reason'] ?? null,
                'last_work_day' => $validated['last_work_day'],
                'notice_served' => (bool) ($validated['notice_served'] ?? false),
                'notice_days_served' => $validated['notice_days_served'] ?? null,
                'departed_at' => $validated['departed_at'] ?? $validated['last_work_day'],
                'created_by' => $actor->id,
            ]);

            // Statut `departed` : AuthController refuse tout status ≠ active
            // (403) → l'employé perd l'accès immédiatement (fail-closed).
            $employee->status = 'departed';
            $employee->save();

            // Révocation des tokens Sanctum (comme archive).
            $employee->tokens()->delete();

            if ($employee->company_id !== null) {
                $this->tenantCache->invalidateEmployees($employee->company_id);
            }

            // Garde défensive : create() renvoie toujours un modèle existant ;
            // `exists` (bool) est la vérification PHPStan-compatible (id est int).
            if (! $departure->exists) {
                throw new RuntimeException('employees.departure_not_created');
            }

            EmployeeDeparted::dispatch($employee, $departure);

            // #5439 — journal d'audit global : départ enregistré (HR).
            AuditLog::record(
                'hr',
                'hr.departure.register',
                $departure,
                $actor,
                ['employee_status' => 'active'],
                ['employee_status' => 'departed', 'departure_type' => $departureType, 'last_work_day' => $departure->last_work_day],
            );

            return $departure;
        });
    }
}
