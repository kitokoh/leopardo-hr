<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceAnomalyService
{
    /**
     * @param array{employee_id?: int|null, date_from?: string|null, date_to?: string|null, per_page?: int|null} $filters
     * @return array<string, mixed>
     */
    public function summarize(string $companyId, array $filters = []): array
    {
        $company = Company::query()->find($companyId);
        $dateTo = Carbon::parse($filters['date_to'] ?? now('UTC')->toDateString())->toDateString();
        $dateFrom = Carbon::parse($filters['date_from'] ?? Carbon::parse($dateTo)->subDays(30)->toDateString())->toDateString();
        $limit = max(1, min(100, (int) ($filters['per_page'] ?? 50)));

        $logs = AttendanceLog::query()
            ->with(['employee:id,company_id,first_name,last_name,matricule'])
            ->select([
                'id',
                'company_id',
                'employee_id',
                'date',
                'check_in',
                'check_out',
                'method',
                'source_device_code',
                'status',
                'hours_worked',
                'overtime_hours',
                'late_minutes',
                'corrected_by',
            ])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $items = collect()
            ->merge($this->lateArrivals($logs))
            ->merge($this->missingCheckOuts($logs))
            ->merge($this->manualCorrections($logs))
            ->merge($this->excessiveOvertime($logs))
            ->merge($this->rapidDevicePunches($logs))
            ->merge($this->repeatedExactCheckIns($logs))
            ->merge($this->outOfGeofencePunches($logs, $company))
            ->sortByDesc('detected_at')
            ->values();

        $counts = $items
            ->groupBy('type')
            ->map(fn (Collection $group): int => $group->count())
            ->all();

        return [
            'data' => [
                'period' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                'summary' => [
                    'total' => $items->count(),
                    'critical' => $items->where('severity', 'critical')->count(),
                    'warning' => $items->where('severity', 'warning')->count(),
                    'info' => $items->where('severity', 'info')->count(),
                    'by_type' => (object) $counts,
                ],
                'items' => $items->take($limit)->values(),
            ],
        ];
    }

    private function lateArrivals(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => (int) $log->late_minutes >= 15)
            ->map(fn (AttendanceLog $log): array => $this->item(
                log: $log,
                type: 'late_arrival',
                severity: (int) $log->late_minutes >= 60 ? 'critical' : 'warning',
                title: 'Retard significatif',
                details: [
                    'late_minutes' => (int) $log->late_minutes,
                ],
            ));
    }

    private function missingCheckOuts(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null)
            ->map(fn (AttendanceLog $log): array => $this->item(
                log: $log,
                type: 'missing_check_out',
                severity: 'warning',
                title: 'Sortie manquante',
            ));
    }

    private function manualCorrections(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null)
            ->map(fn (AttendanceLog $log): array => $this->item(
                log: $log,
                type: 'manual_correction',
                severity: 'info',
                title: 'Correction manuelle',
            ));
    }

    private function excessiveOvertime(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => (float) $log->overtime_hours >= 2.0)
            ->map(fn (AttendanceLog $log): array => $this->item(
                log: $log,
                type: 'excessive_overtime',
                severity: (float) $log->overtime_hours >= 4.0 ? 'critical' : 'warning',
                title: 'Heures supplementaires elevees',
                details: [
                    'overtime_hours' => (float) $log->overtime_hours,
                ],
            ));
    }

    private function rapidDevicePunches(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->source_device_code !== null)
            ->groupBy(fn (AttendanceLog $log): string => $log->date->format('Y-m-d').'|'.$log->source_device_code)
            ->flatMap(function (Collection $group): Collection {
                $ordered = $group->sortBy('check_in')->values();
                $anomalies = collect();

                for ($index = 1; $index < $ordered->count(); $index++) {
                    /** @var AttendanceLog $previous */
                    $previous = $ordered[$index - 1];
                    /** @var AttendanceLog $current */
                    $current = $ordered[$index];

                    if ($previous->employee_id === $current->employee_id) {
                        continue;
                    }

                    $seconds = $previous->check_in->diffInSeconds($current->check_in);
                    if ($seconds > 5) {
                        continue;
                    }

                    $anomalies->push($this->item(
                        log: $current,
                        type: 'rapid_device_sequence',
                        severity: 'critical',
                        title: 'Pointages en chaine sur le meme appareil',
                        details: [
                            'seconds_between_punches' => $seconds,
                            'previous_attendance_log_id' => $previous->id,
                            'previous_employee_id' => $previous->employee_id,
                            'source_device_code' => $current->source_device_code,
                        ],
                    ));
                }

                return $anomalies;
            });
    }

    private function repeatedExactCheckIns(Collection $logs): Collection
    {
        return $logs
            ->filter(fn (AttendanceLog $log): bool => $log->check_in !== null)
            ->groupBy('employee_id')
            ->flatMap(function (Collection $employeeLogs): Collection {
                return $employeeLogs
                    ->groupBy(fn (AttendanceLog $log): string => $log->check_in->format('H:i'))
                    ->filter(fn (Collection $group): bool => $group->count() >= 3)
                    ->flatMap(fn (Collection $group): Collection => $group->map(fn (AttendanceLog $log): array => $this->item(
                        log: $log,
                        type: 'repeated_exact_check_in',
                        severity: 'warning',
                        title: 'Heure de pointage trop repetitive',
                        details: [
                            'check_in_minute' => $log->check_in->format('H:i'),
                            'occurrences' => $group->count(),
                        ],
                    )));
            });
    }

    private function outOfGeofencePunches(Collection $logs, ?Company $company): Collection
    {
        $geofence = $company?->metadata['attendance_geofence'] ?? null;

        if (! is_array($geofence) || ! isset($geofence['lat'], $geofence['lng'], $geofence['radius_meters'])) {
            return collect();
        }

        $centerLat = (float) $geofence['lat'];
        $centerLng = (float) $geofence['lng'];
        $radius = (float) $geofence['radius_meters'];

        return $logs
            ->filter(fn (AttendanceLog $log): bool => $log->gps_lat !== null && $log->gps_lng !== null)
            ->map(function (AttendanceLog $log) use ($centerLat, $centerLng, $radius): ?array {
                $distance = $this->distanceMeters($centerLat, $centerLng, (float) $log->gps_lat, (float) $log->gps_lng);

                if ($distance <= $radius) {
                    return null;
                }

                return $this->item(
                    log: $log,
                    type: 'out_of_geofence',
                    severity: 'critical',
                    title: 'Pointage hors zone autorisee',
                    details: [
                        'distance_meters' => (int) round($distance),
                        'radius_meters' => (int) round($radius),
                    ],
                );
            })
            ->filter()
            ->values();
    }

    private function distanceMeters(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($latB - $latA);
        $lngDelta = deg2rad($lngB - $lngA);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function item(
        AttendanceLog $log,
        string $type,
        string $severity,
        string $title,
        array $details = [],
    ): array {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'attendance_log_id' => $log->id,
            'employee_id' => $log->employee_id,
            'employee' => [
                'id' => $log->employee?->id,
                'name' => trim(($log->employee?->first_name ?? '').' '.($log->employee?->last_name ?? '')),
                'matricule' => $log->employee?->matricule,
            ],
            'date' => $log->date?->format('Y-m-d'),
            'detected_at' => $log->check_in?->toIso8601String() ?? $log->date?->toDateString(),
            'details' => (object) $details,
        ];
    }
}
