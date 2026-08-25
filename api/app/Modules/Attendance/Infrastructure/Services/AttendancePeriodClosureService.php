<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Exceptions\AttendancePeriodClosedException;
use App\Modules\Attendance\Domain\Models\AttendancePeriodClosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Issue #5267 — clôture des périodes de pointage.
 *
 * Une période close (mois) verrouille les corrections de pointage :
 *  - `assertPeriodOpen()` → exception 422 `ATTENDANCE_PERIOD_CLOSED` si la
 *    date est couverte par une clôture (utilisée par request/approve/reject) ;
 *  - `closePeriod()` → clôture idempotente et tracée (AuditLog) ;
 *  - `isDateClosed()` → prédicat pour la présentation.
 */
final class AttendancePeriodClosureService
{
    /**
     * La date est-elle couverte par une clôture de période ?
     */
    public function isDateClosed(string $companyId, Carbon $date): bool
    {
        return AttendancePeriodClosure::query()
            ->where('company_id', $companyId)
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * @throws AttendancePeriodClosedException si la date est clôturée
     */
    public function assertPeriodOpen(string $companyId, Carbon $date): void
    {
        if ($this->isDateClosed($companyId, $date)) {
            throw new AttendancePeriodClosedException($date->format('Y-m'));
        }
    }

    /**
     * Clôture idempotente d'une période (mois entier recommandé).
     *
     * @return AttendancePeriodClosure la clôture existante ou créée
     */
    public function closePeriod(string $companyId, Carbon $periodStart, Carbon $periodEnd, Employee $actor): AttendancePeriodClosure
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->startOfDay();

        $closure = AttendancePeriodClosure::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]
        );

        if (! $closure->wasRecentlyCreated) {
            return $closure; // idempotent : pas de double audit
        }

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $actor->id,
            'action' => 'attendance_period_closed',
            'auditable_type' => $closure->getMorphClass(),
            'auditable_id' => $closure->id,
            'old_values' => [],
            'new_values' => [
                'period_start' => $closure->period_start->toDateString(),
                'period_end' => $closure->period_end->toDateString(),
            ],
        ]);

        return $closure;
    }

    /**
     * Clôtures d'un tenant, triées par période décroissante.
     *
     * @return Collection<int, AttendancePeriodClosure>
     */
    public function closingsFor(string $companyId): Collection
    {
        return AttendancePeriodClosure::query()
            ->where('company_id', $companyId)
            ->orderByDesc('period_end')
            ->get();
    }
}
