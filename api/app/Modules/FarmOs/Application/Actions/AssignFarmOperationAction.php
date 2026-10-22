<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Exceptions\FarmAssignmentException;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\FarmTeam;
use App\Modules\FarmOs\Domain\Models\FarmTeamMember;
use App\Shared\Contracts\Notification\EmployeeNotifier;

/**
 * Farm OS — T1.3 Affectation d'une intervention à un travailleur ou une équipe.
 *
 * Réutilise le socle, ne le duplique pas :
 *   - les travailleurs sont des `Employee` (BC-04 HR) — Farm OS ne crée AUCUN
 *     second référentiel de personnes ni de rôles ;
 *   - l'équipe est `FarmTeam` / `FarmTeamMember` (relation de terrain, ADR-FARM-0007) ;
 *   - la notification passe par le contrat partagé {@see EmployeeNotifier}
 *     (BC-13 COMMS), jamais par un import croisé `FarmOs -> Notification`
 *     (règle d'isolation #5584) : les préférences et le consentement du
 *     destinataire sont donc toujours respectés.
 *
 * CONTRÔLE CÔTÉ SERVEUR (exigence de la mission) : l'employé affecté doit
 * appartenir à l'entreprise courante, et l'équipe aussi. Sans ce contrôle, un
 * identifiant d'employé d'un autre tenant pourrait être affecté à une
 * intervention — c'est précisément le trou que corrige cette action (l'ancien
 * `fill()` du contrôleur acceptait n'importe quel entier).
 *
 * IDEMPOTENT : réaffecter la même personne à la même opération ne renvoie
 * qu'une seule notification (comparaison avec l'état persisté avant écriture).
 */
final class AssignFarmOperationAction
{
    public function __construct(
        private readonly EmployeeNotifier $notifier,
    ) {}

    /**
     * @param  array{assigned_to?: int|null, farm_team_id?: int|null, responsible_id?: int|null}  $assignment
     * @return array{operation: FarmOperation, notified: list<int>, changed: bool}
     */
    public function execute(
        Company $company,
        FarmOperation $operation,
        array $assignment,
        ?int $actorId = null,
    ): array {
        $assignedTo = $this->resolveEmployeeId($company, $assignment['assigned_to'] ?? null, 'assigned_to');
        $responsibleId = $this->resolveEmployeeId($company, $assignment['responsible_id'] ?? null, 'responsible_id');
        $teamId = $this->resolveTeamId($company, $assignment['farm_team_id'] ?? null);

        // État AVANT écriture : sert à ne notifier que ce qui change réellement.
        $previousAssignedTo = $operation->assigned_to;
        $previousTeamId = $operation->farm_team_id;

        $operation->fill([
            'assigned_to' => $assignedTo,
            'responsible_id' => $responsibleId,
            'farm_team_id' => $teamId,
        ]);
        $operation->save();

        $recipients = $this->recipients($company, $assignedTo, $teamId);
        $changed = $previousAssignedTo !== $assignedTo || $previousTeamId !== $teamId;

        $notified = $changed
            ? $this->notify($recipients, $operation, $actorId)
            : [];

        return [
            'operation' => $operation->refresh(),
            'notified' => $notified,
            'changed' => $changed,
        ];
    }

    /**
     * L'employé doit exister DANS l'entreprise courante (isolation multi-tenant).
     */
    private function resolveEmployeeId(Company $company, mixed $employeeId, string $field): ?int
    {
        if ($employeeId === null) {
            return null;
        }

        $id = (int) $employeeId;

        $exists = Employee::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->exists();

        if ($exists === false) {
            throw FarmAssignmentException::employeeNotInCompany($field, $id);
        }

        return $id;
    }

    /**
     * L'équipe doit exister DANS l'entreprise courante.
     */
    private function resolveTeamId(Company $company, mixed $teamId): ?int
    {
        if ($teamId === null) {
            return null;
        }

        $id = (int) $teamId;

        $exists = FarmTeam::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->exists();

        if ($exists === false) {
            throw FarmAssignmentException::teamNotInCompany($id);
        }

        return $id;
    }

    /**
     * Destinataires : le travailleur affecté, le responsable, et les membres
     * ACTIFS de l'équipe (`left_at` null) — jamais un ancien membre.
     *
     * @return list<Employee>
     */
    private function recipients(Company $company, ?int $assignedTo, ?int $teamId): array
    {
        $ids = [];

        if ($assignedTo !== null) {
            $ids[] = $assignedTo;
        }

        if ($teamId !== null) {
            $memberIds = FarmTeamMember::query()
                ->where('company_id', $company->id)
                ->where('farm_team_id', $teamId)
                ->whereNull('left_at')
                ->pluck('employee_id')
                ->all();

            foreach ($memberIds as $memberId) {
                $ids[] = (int) $memberId;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        /** @var list<Employee> $employees */
        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->get()
            ->all();

        return $employees;
    }

    /**
     * @param  list<Employee>  $recipients
     * @return list<int>
     */
    private function notify(array $recipients, FarmOperation $operation, ?int $actorId): array
    {
        $notified = [];

        foreach ($recipients as $employee) {
            // L'auteur de l'affectation n'a pas besoin d'être notifié de son
            // propre geste.
            if ($actorId !== null && (int) $employee->id === $actorId) {
                continue;
            }

            $this->notifier->notifyEmployee($employee, 'farm_operation_assigned', [
                'category' => 'farm_os',
                'metadata' => [
                    'operation_id' => $operation->id,
                    'farm_site_id' => $operation->farm_site_id,
                    'parcel_id' => $operation->parcel_id,
                    'op_type' => $operation->op_type,
                    'title' => $operation->title,
                    'planned_start_at' => $operation->planned_start_at?->toIso8601String(),
                ],
            ]);

            $notified[] = (int) $employee->id;
        }

        return $notified;
    }
}
