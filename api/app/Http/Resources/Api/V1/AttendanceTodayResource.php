<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use App\Shared\Contracts\Attendance\AttendanceLogView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #8299 (BOS-023 cycle 3) : le journal est accepté via le contrat Shared
 * `AttendanceLogView` (implémenté par le modèle `AttendanceLog`) — JSON de
 * sortie strictement identique, accès par les méthodes du contrat.
 */
class AttendanceTodayResource extends JsonResource
{
    private ?AttendanceLogView $log;

    private string $timezone;

    /** @var array<string, mixed>|null */
    private ?array $summary;

    /**
     * @param  Employee  $resource
     * @param  array<string, mixed>|null  $summary
     */
    public function __construct($resource, ?AttendanceLogView $log = null, ?string $timezone = null, ?array $summary = null)
    {
        parent::__construct($resource);
        $this->log = $log;
        $this->timezone = $timezone ?? currentCompany()->timezone;
        $this->summary = $summary;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        $estimationService = app(EstimationService::class);
        $summary = $this->summary
            ?? $estimationService->dailySummary($employee, $this->log?->date()?->toDateString());
        $meta = $this->log?->punchMeta() ?? [];

        return [
            'id' => $this->log?->id(),
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            // Session réellement OUVERTE (check_in posé, check_out absent) — après
            // le check-out du jour, la session la plus récente a check_in ET
            // check_out renseignés : l'employé n'est plus « en service » (#6962).
            'checked_in' => $this->log !== null && $this->log->checkIn() !== null && $this->log->checkOut() === null,
            'session_number' => (int) ($this->log?->sessionNumber() ?? 0),
            'check_in' => $this->log?->checkIn()?->toIso8601String(),
            'check_out' => $this->log?->checkOut()?->toIso8601String(),
            'check_in_time' => $this->log?->checkIn()?->copy()->setTimezone($this->timezone)->format('H:i'),
            'check_out_time' => $this->log?->checkOut()?->copy()->setTimezone($this->timezone)->format('H:i'),
            'timezone' => $this->timezone,
            'device_timezone' => $meta['device_timezone'] ?? null,
            'sessions_count' => (int) ($summary['sessions_count'] ?? 0),
            'work_type' => $this->log?->workType() ?? 'normal',
            'hours_worked' => (float) ($summary['hours_worked'] ?? 0.00),
            'overtime_hours' => (float) ($summary['overtime_hours'] ?? 0.00),
            'status' => $summary['status'] ?? ($this->log?->status() ?? 'absent'),
            'late_minutes' => (int) ($summary['late_minutes'] ?? ($this->log?->lateMinutes() ?? 0)),
            'base_gain' => (float) $summary['base_gain'],
            'overtime_gain' => (float) $summary['overtime_gain'],
            'total_estimated' => (float) $summary['total_estimated'],
            'currency' => $summary['currency'],
            'geofence' => $meta['geofence'] ?? null,
        ];
    }
}
