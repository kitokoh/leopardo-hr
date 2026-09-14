<?php

declare(strict_types=1);

namespace App\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Infrastructure\Services\AnnouncementService;
use App\Modules\Planning\Application\Actions\ApproveAbsence;
use App\Modules\Planning\Application\Actions\RejectAbsence;
use App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Planning\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WriteActionRunner
{
    public function __construct(
        // B3a (#6856) — l'outil `absence_decision` exécute les Actions
        // canoniques Planning (ApproveAbsence/RejectAbsence → AbsenceService),
        // propriétaire du domaine absence/congé (PA2-ARCH-002) — même chemin
        // que les endpoints REST du module façade Absence.
        private readonly ApproveAbsence $approveAbsence,
        private readonly RejectAbsence $rejectAbsence,
        // B3b (#6857) — l'outil `shift_assign` invalide le cache employés du
        // tenant après affectation (même service que
        // ScheduleController::assignEmployees).
        private readonly TenantCacheService $tenantCache,
        // B3c (#6858) — l'outil `notify_team` passe par le service canonique
        // d'annonces du module Notification (BC-13 COMMS), même chemin que
        // l'endpoint REST POST /api/v1/announcements.
        private readonly AnnouncementService $announcements,
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
            'absence_decision',
            'shift_assign',
            'notify_team',
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
            // B3a (#6856) — décision (approbation/refus motivé) via les Actions
            // canoniques Planning, parité REST AbsenceController approve/reject.
            'absence_decision' => fn (array $arguments): array => $this->decideAbsence($companyId, $userId, $arguments),
            // B3b (#6857) — affectation d'un shift (schedule) à un employé,
            // parité ScheduleController::assignEmployees (BC-05 WORKFORCE).
            'shift_assign' => fn (array $arguments): array => $this->assignShift($companyId, $userId, $arguments),
            // B3c (#6858) — message à une équipe via AnnouncementService
            // (parité AnnouncementController, BC-13 COMMS).
            'notify_team' => fn (array $arguments): array => $this->notifyTeam($companyId, $userId, $arguments),
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
     * B3a (#6856) — décision sur une demande d'absence (approbation ou refus
     * motivé), exécutée APRÈS confirmation humaine (flux A4) via les Actions
     * canoniques Planning (ApproveAbsence/RejectAbsence → AbsenceService,
     * PA2-ARCH-002) — mêmes transitions de statut, mêmes événements métier
     * (AbsenceApproved/AbsenceRejected) et même journal d'audit que les
     * endpoints REST `POST|PUT /api/v1/absences/{absence}/approve|reject`
     * (module façade Absence).
     *
     * Parité REST AbsenceController : seul un manager du tenant peut décider
     * (défense en profondeur par-dessus la matrice ai.tool_permissions, rôle
     * manager + absences.approve) ; une absence hors tenant est introuvable
     * (isolation fail-closed) ; un refus exige un motif (≤ 1000 caractères,
     * même règle que RejectAbsenceRequest).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function decideAbsence(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        if (! $actor->isManager()) {
            return ['error' => 'AI_TOOL_PERMISSION_DENIED', 'message' => 'Manager role required to decide on absences'];
        }

        $absenceId = $this->intArgument($arguments, 'absence_id', 0);
        $absence = Absence::query()
            ->where('company_id', $companyId)
            ->where('id', $absenceId)
            ->first();

        if ($absence === null) {
            return ['error' => 'Absence not found'];
        }

        $decision = $this->stringArgument($arguments, 'decision', '');
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return ['error' => 'INVALID_DECISION', 'message' => "decision must be 'approve' or 'reject'"];
        }

        try {
            if ($decision === 'approve') {
                $approved = $this->approveAbsence->execute($absence, $actor);

                return [
                    'absence_id' => $approved->id,
                    'status' => $approved->status,
                    'approved_by' => $approved->approved_by,
                ];
            }

            $reason = trim($this->stringArgument($arguments, 'reason', ''));
            if ($reason === '') {
                return ['error' => 'ABSENCE_REJECT_REASON_REQUIRED', 'message' => 'A rejection reason is required (parité RejectAbsenceRequest)'];
            }

            if (mb_strlen($reason) > 1000) {
                return ['error' => 'ABSENCE_REJECT_REASON_TOO_LONG', 'message' => 'Rejection reason must not exceed 1000 characters'];
            }

            $rejected = $this->rejectAbsence->execute($absence, $reason);

            return [
                'absence_id' => $rejected->id,
                'status' => $rejected->status,
                'rejected_reason' => $rejected->rejected_reason,
            ];
        } catch (AbsenceNotPendingException $exception) {
            return ['error' => $exception->errorCode(), 'message' => $exception->getMessage()];
        } catch (InsufficientLeaveBalanceException $exception) {
            return ['error' => $exception->errorCode(), 'message' => $exception->getMessage()];
        }
    }

    /**
     * B3b (#6857) — affectation d'un shift (gabarit horaire `Schedule`) à un
     * employé, exécutée APRÈS confirmation humaine (flux A4). Parité exacte
     * avec l'endpoint REST canonique
     * `POST /api/v1/schedules/{schedule}/assign-employees`
     * (ScheduleController::assignEmployees, module Planning, BC-05 WORKFORCE) :
     * manager du tenant uniquement (défense en profondeur par-dessus la
     * matrice ai.tool_permissions) ; schedule et employé du tenant ; manager
     * d'équipe (dept/superviseur) borné à son périmètre (`visibleToManager`,
     * PA2-SEC-002/003) ; invalidation du cache employés après affectation.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function assignShift(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        if (! $actor->isManager()) {
            return ['error' => 'AI_TOOL_PERMISSION_DENIED', 'message' => 'Manager role required to assign shifts'];
        }

        $scheduleId = $this->intArgument($arguments, 'schedule_id', 0);
        $schedule = Schedule::query()
            ->where('company_id', $companyId)
            ->where('id', $scheduleId)
            ->first();

        if ($schedule === null) {
            return ['error' => 'Schedule not found'];
        }

        $employeeId = $this->intArgument($arguments, 'employee_id', 0);
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->when($actor->isTeamScoped(), static fn ($query) => $query->visibleToManager($actor))
            ->where('id', $employeeId)
            ->first();

        if ($employee === null) {
            return [
                'error' => 'Employee not found',
                'message' => 'Only employees from the current company (and manager scope) can be assigned',
            ];
        }

        $employee->update(['schedule_id' => $schedule->id]);

        // Même invalidation de cache que le REST assignEmployees : les agrégats
        // employés (par schedule) ne doivent pas servir une valeur périmée.
        $this->tenantCache->invalidateEmployees((string) $companyId);

        return [
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'status' => 'assigned',
        ];
    }

    /**
     * B3c (#6858) — envoi d'un message à une équipe, exécuté APRÈS
     * confirmation humaine (flux A4). Passe par le service canonique
     * `AnnouncementService::publish` (module Notification, BC-13 COMMS — même
     * chemin que `POST /api/v1/announcements`) : l'annonce est créée puis
     * fan-out immédiat vers les destinataires résolus (préférences et canaux
     * BC-13 respectés via CommunicationService).
     *
     * Parité REST AnnouncementController (store + authorizeAudience) :
     * manager du tenant uniquement (défense en profondeur par-dessus la
     * matrice ai.tool_permissions) ; audience `company` réservée aux rôles
     * principal/RH ; audience `department` — le manager de département ne
     * peut viser que SON département, principal/RH tout département ;
     * contenus bornés (titre ≤ 200, message ≤ 5000, mêmes limites que le
     * REST). Anti-spam : plafond d'envois confirmés par acteur et par heure
     * (`config ai.notify_team_rate.max_per_hour`, défaut 10).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function notifyTeam(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null) {
            return ['error' => 'Employee not found'];
        }

        if (! $actor->isManager()) {
            return ['error' => 'AI_TOOL_PERMISSION_DENIED', 'message' => 'Manager role required to notify a team'];
        }

        $title = trim($this->stringArgument($arguments, 'title', ''));
        $message = trim($this->stringArgument($arguments, 'message', ''));
        $audienceType = $this->stringArgument($arguments, 'audience_type', CompanyAnnouncement::AUDIENCE_DEPARTMENT);

        if ($title === '' || $title === '0' || mb_strlen($title) > 200) {
            return ['error' => 'NOTIFY_TITLE_INVALID', 'message' => 'Title is required (max 200 characters)'];
        }
        if ($message === '' || mb_strlen($message) > 5000) {
            return ['error' => 'NOTIFY_MESSAGE_INVALID', 'message' => 'Message is required (max 5000 characters)'];
        }
        if (! in_array($audienceType, [CompanyAnnouncement::AUDIENCE_COMPANY, CompanyAnnouncement::AUDIENCE_DEPARTMENT], true)) {
            return ['error' => 'NOTIFY_AUDIENCE_INVALID', 'message' => "audience_type must be 'company' or 'department'"];
        }

        // Parité authorizeAudience : cible entreprise = principal/RH ; cible
        // département = manager de CE département (principal/RH : tous).
        $companyWide = $actor->hasManagerRole('principal', 'rh');
        $departmentId = $this->intArgument($arguments, 'department_id', 0);

        if ($audienceType === CompanyAnnouncement::AUDIENCE_COMPANY && ! $companyWide) {
            return ['error' => 'AUDIENCE_NOT_ALLOWED', 'message' => 'Only principal/RH managers can broadcast to the whole company'];
        }

        if ($audienceType === CompanyAnnouncement::AUDIENCE_DEPARTMENT) {
            if ($departmentId < 1) {
                return ['error' => 'NOTIFY_DEPARTMENT_REQUIRED', 'message' => 'department_id is required when audience_type is department'];
            }
            if (! $companyWide && ! ($actor->isDept() && (int) ($actor->department_id ?? -1) === $departmentId)) {
                return ['error' => 'AUDIENCE_NOT_ALLOWED', 'message' => 'You can only broadcast to your own department'];
            }
        }

        // Anti-spam : plafond d'envois confirmés par acteur et par heure.
        $limit = config('ai.notify_team_rate.max_per_hour', 10);
        $maxPerHour = max(1, is_numeric($limit) ? (int) $limit : 10);
        $bucket = 'ai:notify_team:'.$companyId.':'.$userId.':'.now()->format('YmdH');
        $cached = Cache::get($bucket, 0);
        $sent = is_numeric($cached) ? (int) $cached : 0;
        if ($sent >= $maxPerHour) {
            return ['error' => 'NOTIFY_RATE_LIMITED', 'message' => "Team notifications are limited to {$maxPerHour} per hour per manager"];
        }

        try {
            /** @var \App\Modules\Notification\Domain\Models\CompanyAnnouncement $announcement */
            $announcement = $this->announcements->publish($actor, [
                'title' => $title,
                'body' => $message,
                'priority' => 'normal',
                'audience_type' => $audienceType,
                'audience_department_id' => $audienceType === CompanyAnnouncement::AUDIENCE_DEPARTMENT ? $departmentId : null,
                'status' => CompanyAnnouncement::STATUS_PUBLISHED,
            ]);
        } catch (\Throwable $exception) {
            return ['error' => 'NOTIFY_SEND_FAILED', 'message' => $exception->getMessage()];
        }

        Cache::put($bucket, $sent + 1, now()->addHour());

        return [
            'announcement_id' => $announcement->id,
            'status' => $announcement->status,
            'audience_type' => $announcement->audience_type,
            'recipients_count' => $announcement->recipients_count ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createAbsence(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null) {
            return ['error' => 'Actor not found'];
        }

        // audit(securite) #6533 : un non-manager ne peut créer une absence que
        // pour LUI-MÊME — l'employee_id passé par le LLM est ignoré. Un
        // manager peut créer pour un employé du même tenant (périmètre
        // AbsencePolicy::create + company_id).
        $employeeId = $actor->isManager()
            ? $this->intArgument($arguments, 'employee_id', $userId)
            : $userId;

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

        // #7357 — `absences.absence_type_id` est NOT NULL. Un tenant sans
        // catalogue `absence_types` (société fraîchement provisionnée, aucune
        // template sectorielle appliquée) faisait tomber l'insertion en
        // SQLSTATE[23502] → 500 brut sur la confirmation. On refuse
        // proprement AVANT toute écriture (422 côté contrôleur).
        if ($absenceType === null) {
            return [
                'error' => 'ABSENCE_TYPE_UNAVAILABLE',
                'message' => "Aucun type d'absence n'est configuré pour cette société : créez au moins un type de congé avant d'utiliser l'assistant.",
            ];
        }

        $absence = Absence::create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'absence_type_id' => $absenceType->id,
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
        // audit(securite) #6533 : ré-utilisation des policies REST
        // (AbsencePolicy::approve = company_id match && isManager) — un
        // manager dept/superviseur ne peut plus approuver via l'IA hors de son
        // périmètre, et un employé ne peut pas approuver du tout.
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null || ! $actor->isManager()) {
            return ['error' => 'AI_TOOL_PERMISSION_DENIED', 'message' => 'Manager role required to approve absences'];
        }

        $absenceId = $this->intArgument($arguments, 'absence_id', 0);
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
}
