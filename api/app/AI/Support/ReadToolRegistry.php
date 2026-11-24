<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * Registre des read-tools dispatchables — extrait de `IntentEngine` par #9887.
 *
 * ## Une seule source de verite (issue #5625)
 *
 * La map ci-dessous sert a la fois au DISPATCH et au test de couverture
 * (`ToolRegistryCoverageTest`) : tout outil du registre `ai_tool_registry`
 * sans entree ici fait echouer la CI. C'est ce qui interdit de promettre au
 * modele « je peux X » sans handler derriere.
 *
 * ## Pourquoi la map est construite ici et non dans les handlers
 *
 * Les handlers ne connaissent pas les NOMS d'outils : ils exposent des
 * methodes. Le couplage nom-public -> methode est une decision de contrat,
 * elle vit a un seul endroit. Un handler peut ainsi etre renomme ou deplace
 * sans toucher au contrat expose au LLM.
 *
 * ## Les closures capturent le tenant
 *
 * `$companyId` et `$userId` sont resolus depuis la session par l'appelant et
 * capturés ici : aucun handler ne les lit dans les arguments du LLM. C'est la
 * garantie qu'un modele ne peut pas demander les donnees d'un autre tenant en
 * forgeant un argument.
 */
final class ReadToolRegistry
{
    public function __construct(
        private readonly HrReadHandlers $hr,
        private readonly PayrollReadHandlers $payroll,
        private readonly CommunicationReadHandlers $communication,
    ) {}

    /**
     * @return array<string, callable(array<string, mixed>): array<string, mixed>>
     */
    public function handlers(string $companyId, int $userId): array
    {
        return [
            'get_employees' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getEmployees($companyId, $userId, $arguments);
            },
            'get_employee_details' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getEmployeeDetails($companyId, $userId, $arguments);
            },
            'get_departments' => fn (array $arguments): array => $this->hr->getDepartments($companyId),
            'get_headcount' => fn (array $arguments): array => $this->hr->getHeadcount($companyId),
            'search_employees' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->searchEmployees($companyId, $userId, $arguments);
            },
            'get_attendance_today' => fn (array $arguments): array => $this->hr->getAttendanceToday($companyId, $userId),
            'get_attendance_anomalies' => function (array $arguments) use ($companyId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getAttendanceAnomalies($companyId, $arguments);
            },
            'get_monthly_report' => fn (array $arguments): array => $this->hr->getMonthlyReport($companyId),
            'get_absences' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getAbsences($companyId, $userId, $arguments);
            },
            'get_daily_summary' => fn (array $arguments): array => $this->hr->getDailySummary($companyId, $userId),
            'get_notifications' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getNotifications($companyId, $userId, $arguments);
            },
            'get_leave_balances' => fn (array $arguments): array => $this->hr->getLeaveBalances($companyId, $userId),
            'get_payroll_summary' => fn (array $arguments): array => $this->payroll->getPayrollSummary($companyId, $userId),
            // B1 (#6854) — outils lecture BC-04 HR (contrat A3 #6850) :
            // agregats via les modeles canoniques HR/Planning.
            'team_overview' => fn (array $arguments): array => $this->hr->getTeamOverview($companyId, $userId),
            'team_absences_recent' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getTeamAbsencesRecent($companyId, $userId, $arguments);
            },
            'employee_leave_balance' => function (array $arguments) use ($companyId, $userId): array {
                /** @var array<string, mixed> $arguments */
                return $this->hr->getEmployeeLeaveBalance($companyId, $userId, $arguments);
            },
            // B2 (#6855) — agregat du statut de paie via les modeles canoniques
            // Payroll (PayrollRun + PaySlip) — aucun montant expose.
            'payroll_current_status' => fn (array $arguments): array => $this->payroll->getPayrollCurrentStatus($companyId, $userId),
            // R3/R5 Communication (#7688/#7690) — outils lecture BC-29
            // COMMUNICATION (contrat A3 #6850) : classification d'un email
            // synchronise et brouillon de reponse, via les services canoniques
            // du module (parite pipeline sync/jobs — jamais d'envoi ici :
            // #8023 le mode `auto` est retrograde en `confirm` sur ce chemin).
            'email_classify' => fn (array $arguments): array => $this->communication->classifyCommunicationMessage($companyId, $arguments),
            'email_reply_draft' => fn (array $arguments): array => $this->communication->draftCommunicationReply($companyId, $arguments),
        ];
    }

    /**
     * Noms des read-tools effectivement dispatchables (issue #5625).
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->handlers('', 0));
    }
}
