<?php

declare(strict_types=1);

namespace App\AI\Predictions;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProactiveNotificationService
{
    /**
     * @return list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>
     */
    public function getNotifications(string $companyId): array
    {
        $notifications = [];

        $this->checkExpiringContracts($companyId, $notifications);
        $this->checkTrialPeriodEnding($companyId, $notifications);
        $this->checkBirthdaysThisWeek($companyId, $notifications);
        $this->checkPendingApprovals($companyId, $notifications);
        $this->checkOverdueTraining($companyId, $notifications);
        $this->checkLowLeaveBalances($companyId, $notifications);

        usort($notifications, function (array $a, array $b): int {
            $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];

            return ($severityOrder[$a['severity']] ?? 3) <=> ($severityOrder[$b['severity']] ?? 3);
        });

        return $notifications;
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkExpiringContracts(string $companyId, array &$notifications): void
    {
        $expiring = DB::table('contracts')
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->where('contracts.company_id', $companyId)
            ->where('contracts.status', 'active')
            ->whereBetween('contracts.end_date', [now(), now()->addDays(30)])
            ->select([
                'contracts.id',
                'contracts.end_date',
                'employees.first_name',
                'employees.last_name',
            ])
            ->get();

        foreach ($expiring as $contract) {
            $daysLeft = $this->daysUntil($contract->end_date);
            $severity = $daysLeft <= 7 ? 'critical' : 'warning';
            /** @var string $firstName */
            $firstName = $contract->first_name;
            /** @var string $lastName */
            $lastName = $contract->last_name;

            $notifications[] = [
                'type' => 'contract_expiring',
                'severity' => $severity,
                'title' => 'Contrat expire dans '.$daysLeft.' jours',
                'message' => $firstName.' '.$lastName.' — renouvellement ou fin a planifier.',
                'action_url' => '/contracts',
                'entity_id' => $this->intId($contract->id),
            ];
        }
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkTrialPeriodEnding(string $companyId, array &$notifications): void
    {
        $trials = DB::table('contracts')
            ->join('employees', 'contracts.employee_id', '=', 'employees.id')
            ->where('contracts.company_id', $companyId)
            ->where('contracts.status', 'active')
            ->when(
                Schema::hasColumn('contracts', 'trial_end_date'),
                fn (Builder $query): Builder => $query->whereNotNull('contracts.trial_end_date')
                    ->whereBetween('contracts.trial_end_date', [now(), now()->addDays(14)]),
                fn (Builder $query): Builder => $query->whereNotNull('contracts.probation_end_date')
                    ->whereBetween('contracts.probation_end_date', [now(), now()->addDays(14)]),
            )
            ->select([
                'contracts.id',
                Schema::hasColumn('contracts', 'trial_end_date')
                    ? 'contracts.trial_end_date'
                    : DB::raw('contracts.probation_end_date as trial_end_date'),
                'employees.first_name',
                'employees.last_name',
            ])
            ->get();

        foreach ($trials as $contract) {
            $daysLeft = $this->daysUntil($contract->trial_end_date);
            /** @var string $firstName */
            $firstName = $contract->first_name;
            /** @var string $lastName */
            $lastName = $contract->last_name;

            $notifications[] = [
                'type' => 'trial_ending',
                'severity' => 'warning',
                'title' => 'Periode d\'essai termine dans '.$daysLeft.' jours',
                'message' => $firstName.' '.$lastName.' — evaluation a confirmer.',
                'action_url' => '/contracts',
                'entity_id' => $this->intId($contract->id),
            ];
        }
    }

    private function intId(mixed $id): int
    {
        if (is_int($id)) {
            return $id;
        }

        return is_numeric($id) ? (int) $id : 0;
    }

    private function daysUntil(mixed $date): int
    {
        if ($date instanceof CarbonInterface || $date instanceof \DateTimeInterface || is_string($date)) {
            return (int) now()->diffInDays($date);
        }

        return 0;
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkBirthdaysThisWeek(string $companyId, array &$notifications): void
    {
        $count = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('date_of_birth')
            ->whereRaw('EXTRACT(MONTH FROM date_of_birth) = ? AND EXTRACT(DAY FROM date_of_birth) BETWEEN ? AND ?', [
                now()->month,
                now()->day,
                now()->addDays(7)->day,
            ])
            ->count();

        if ($count > 0) {
            $notifications[] = [
                'type' => 'birthdays',
                'severity' => 'info',
                'title' => $count.' anniversaire(s) cette semaine',
                'message' => 'N\'oubliez pas de souhaiter un bon anniversaire a vos collaborateurs.',
                'action_url' => '/employees',
                'entity_id' => null,
            ];
        }
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkPendingApprovals(string $companyId, array &$notifications): void
    {
        $pendingCount = DB::table('absences')
            ->join('employees', 'absences.employee_id', '=', 'employees.id')
            ->where('employees.company_id', $companyId)
            ->where('absences.status', 'pending')
            ->where('absences.created_at', '<=', now()->subDays(3))
            ->count();

        if ($pendingCount > 0) {
            $notifications[] = [
                'type' => 'pending_approvals',
                'severity' => $pendingCount > 5 ? 'warning' : 'info',
                'title' => $pendingCount.' demande(s) en attente depuis 3+ jours',
                'message' => 'Des demandes de conges attendent votre validation.',
                'action_url' => '/leaves',
                'entity_id' => null,
            ];
        }
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkOverdueTraining(string $companyId, array &$notifications): void
    {
        $overdueCount = DB::table('training_enrollments')
            ->join('training_sessions', 'training_enrollments.training_session_id', '=', 'training_sessions.id')
            ->join('training_courses', 'training_sessions.training_course_id', '=', 'training_courses.id')
            ->where('training_courses.company_id', $companyId)
            ->where('training_enrollments.status', 'enrolled')
            ->where('training_sessions.end_date', '<', now())
            ->count();

        if ($overdueCount > 0) {
            $notifications[] = [
                'type' => 'overdue_training',
                'severity' => 'warning',
                'title' => $overdueCount.' inscription(s) formation non completee(s)',
                'message' => 'Des formations sont terminees mais les inscriptions ne sont pas cloturees.',
                'action_url' => '/training',
                'entity_id' => null,
            ];
        }
    }

    /**
     * @param  list<array{type: string, severity: string, title: string, message: string, action_url: string|null, entity_id: int|null}>  $notifications
     */
    private function checkLowLeaveBalances(string $companyId, array &$notifications): void
    {
        if (! Schema::hasTable('leave_balances')) {
            return;
        }

        $remainingColumn = $this->leaveBalanceRemainingColumn();
        if ($remainingColumn === null) {
            return;
        }

        $lowBalanceCount = DB::table('leave_balances')
            ->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
            ->where('employees.company_id', $companyId)
            ->where('employees.status', 'active')
            ->where("leave_balances.$remainingColumn", '<=', 2)
            ->where("leave_balances.$remainingColumn", '>=', 0)
            ->distinct('leave_balances.employee_id')
            ->count('leave_balances.employee_id');

        if ($lowBalanceCount > 0) {
            $notifications[] = [
                'type' => 'low_leave_balance',
                'severity' => 'info',
                'title' => $lowBalanceCount.' employe(s) avec solde conges faible',
                'message' => 'Certains collaborateurs ont 2 jours ou moins de conges restants.',
                'action_url' => '/leaves',
                'entity_id' => null,
            ];
        }
    }

    private function leaveBalanceRemainingColumn(): ?string
    {
        foreach (['remaining', 'remaining_days', 'balance'] as $column) {
            if (Schema::hasColumn('leave_balances', $column)) {
                return $column;
            }
        }

        return null;
    }
}
