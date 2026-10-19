<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\ConversationBlock;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationReport;
use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * #8750 [BOS-CHAT] — modération de base du chat horizontal.
 *
 * Périmètre volontairement minimal, et **sans nouveau rôle plateforme** :
 *
 *  - **blocage** unilatéral (aucune notification au bloqué, aucun effacement) ;
 *  - **signalement** d'un message ou d'un compte, traité par un `manager` du
 *    même tenant ;
 *  - **rate limiting** : appliqué par le middleware de route
 *    ({@see \App\Http\Middleware\ResilientThrottleRequests}), pas ici — un
 *    compteur applicatif se contournerait en changeant d'instance ;
 *  - **journalisation** : chaque signalement et chaque traitement produit une
 *    ligne de log structurée portant le tenant, jamais le contenu du message.
 *
 * ⚠️ Contrainte de la mission respectée : un administrateur en charge de la
 * modération **ne gagne aucun accès** aux conversations privées. Il voit les
 * signalements, leur motif et leur statut ; il ne peut pas lire un fil dont il
 * n'est pas participant. Le seul contenu visible est celui du message signalé
 * s'il y a déjà accès par ailleurs.
 */
class ConversationModerationService
{
    /**
     * L'acteur peut-il contacter `$targetId` ? Vrai par défaut ; faux si l'un
     * des deux a bloqué l'autre (le blocage est unilatéral dans son écriture
     * mais bilatéral dans son effet : si A bloque B, ni A ni B ne peut ouvrir
     * une nouvelle conversation entre eux).
     */
    public function canContact(Employee $actor, int $targetId): bool
    {
        return ! ConversationBlock::query()
            ->where('company_id', $actor->company_id)
            ->where(function ($query) use ($actor, $targetId): void {
                $query->where(function ($pair) use ($actor, $targetId): void {
                    $pair->where('blocker_id', $actor->id)->where('blocked_id', $targetId);
                })->orWhere(function ($pair) use ($actor, $targetId): void {
                    $pair->where('blocker_id', $targetId)->where('blocked_id', $actor->id);
                });
            })
            ->exists();
    }

    /**
     * Bloque un employé. Idempotent : re-bloquer ne crée pas de doublon et ne
     * lève pas d'erreur (le client peut réessayer sans se soucier de l'état).
     */
    public function block(Employee $actor, int $targetId, ?string $reason = null): ConversationBlock
    {
        if ($targetId === $actor->id) {
            throw ValidationException::withMessages([
                'employee_id' => ['Vous ne pouvez pas vous bloquer vous-même.'],
            ]);
        }

        // Résolution tenant-scopée : un identifiant d'un autre tenant ne matche
        // pas, donc aucun blocage cross-tenant ne peut être enregistré.
        $target = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($targetId);

        if (! $target instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => ['Cet employé n\'appartient pas à votre organisation.'],
            ]);
        }

        $block = ConversationBlock::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'blocker_id' => $actor->id,
                'blocked_id' => $target->id,
            ],
            ['reason' => $reason],
        );

        $this->journal('chat.user_blocked', [
            'company_id' => $actor->company_id,
            'blocker_id' => $actor->id,
            'blocked_id' => $target->id,
        ]);

        return $block;
    }

    /**
     * Débloque. Silencieux si le blocage n'existait pas (débloquer deux fois
     * n'est pas une erreur).
     */
    public function unblock(Employee $actor, int $targetId): void
    {
        $deleted = ConversationBlock::query()
            ->where('company_id', $actor->company_id)
            ->where('blocker_id', $actor->id)
            ->where('blocked_id', $targetId)
            ->delete();

        if ($deleted > 0) {
            $this->journal('chat.user_unblocked', [
                'company_id' => $actor->company_id,
                'blocker_id' => $actor->id,
                'blocked_id' => $targetId,
            ]);
        }
    }

    /**
     * Employés bloqués par l'acteur — pour l'écran de modération et pour que le
     * client puisse griser une conversation.
     *
     * @return LengthAwarePaginator<int, ConversationBlock>
     */
    public function blocksFor(Employee $actor, int $perPage = 50): LengthAwarePaginator
    {
        return ConversationBlock::query()
            ->where('company_id', $actor->company_id)
            ->where('blocker_id', $actor->id)
            ->with('blocked:id,first_name,last_name,matricule')
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));
    }

    /**
     * Signale un message d'un fil auquel l'acteur participe.
     *
     * Le contrôle d'appartenance est fait par l'appelant
     * (`ConversationService::findForActor()`), puis re-vérifié ici : le message
     * doit appartenir à CE fil. Un identifiant de message d'un autre fil est
     * rejeté en 422 — sans quoi un signalement servirait à sonder l'existence
     * de messages ailleurs.
     */
    public function reportMessage(
        ConversationThread $thread,
        Employee $actor,
        int $messageId,
        string $reason,
        ?string $details = null,
    ): ConversationReport {
        $message = ConversationMessage::query()
            ->where('conversation_thread_id', $thread->id)
            ->whereKey($messageId)
            ->first();

        if (! $message instanceof ConversationMessage) {
            throw ValidationException::withMessages([
                'message_id' => ['Ce message n\'appartient pas à cette conversation.'],
            ]);
        }

        /** @var ConversationReport $report */
        $report = ConversationReport::query()->create([
            'company_id' => $thread->company_id,
            'reporter_id' => $actor->id,
            'conversation_thread_id' => $thread->id,
            'conversation_message_id' => $message->id,
            'reported_employee_id' => $message->author_id,
            'reason' => $reason,
            'details' => $details,
            'status' => ConversationReport::STATUS_OPEN,
        ]);

        // Le contenu du message n'est JAMAIS journalisé : un log est plus
        // largement lisible qu'une table tenant.
        $this->journal('chat.message_reported', [
            'company_id' => $thread->company_id,
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'reporter_id' => $actor->id,
            'reported_employee_id' => $message->author_id,
            'reason' => $reason,
        ]);

        return $report;
    }

    /**
     * Signale un compte (comportement général, sans message précis).
     */
    public function reportEmployee(Employee $actor, int $targetId, string $reason, ?string $details = null): ConversationReport
    {
        if ($targetId === $actor->id) {
            throw ValidationException::withMessages([
                'employee_id' => ['Vous ne pouvez pas vous signaler vous-même.'],
            ]);
        }

        $target = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($targetId);

        if (! $target instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => ['Cet employé n\'appartient pas à votre organisation.'],
            ]);
        }

        /** @var ConversationReport $report */
        $report = ConversationReport::query()->create([
            'company_id' => $actor->company_id,
            'reporter_id' => $actor->id,
            'reported_employee_id' => $target->id,
            'reason' => $reason,
            'details' => $details,
            'status' => ConversationReport::STATUS_OPEN,
        ]);

        $this->journal('chat.account_reported', [
            'company_id' => $actor->company_id,
            'reporter_id' => $actor->id,
            'reported_employee_id' => $target->id,
            'reason' => $reason,
        ]);

        return $report;
    }

    /**
     * File de modération du tenant.
     *
     * Réservée aux `manager` du tenant : un employé ne voit que SES propres
     * signalements (voir `reportsFiledBy()`), et jamais ceux des autres.
     *
     * @return LengthAwarePaginator<int, ConversationReport>
     */
    public function reportsFor(Employee $actor, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->assertCanModerate($actor);

        return ConversationReport::query()
            ->where('company_id', $actor->company_id)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with([
                'reporter:id,first_name,last_name',
                'reviewedBy:id,first_name,last_name',
            ])
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));
    }

    /**
     * Signalements émis par l'acteur — un employé a le droit de suivre ses
     * propres signalements, sans jamais voir ceux des autres.
     *
     * @return LengthAwarePaginator<int, ConversationReport>
     */
    public function reportsFiledBy(Employee $actor, int $perPage = 20): LengthAwarePaginator
    {
        return ConversationReport::query()
            ->where('company_id', $actor->company_id)
            ->where('reporter_id', $actor->id)
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $perPage)));
    }

    /**
     * Clôt un signalement (`reviewed` ou `dismissed`).
     *
     * Un signalement déjà clos n'est pas re-clos : la seconde requête renvoie
     * l'état courant sans écraser la première décision (idempotence).
     */
    public function resolve(Employee $actor, int $reportId, string $status, ?string $resolution = null): ConversationReport
    {
        $this->assertCanModerate($actor);

        if (! in_array($status, [ConversationReport::STATUS_REVIEWED, ConversationReport::STATUS_DISMISSED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Statut de traitement invalide.'],
            ]);
        }

        $report = ConversationReport::query()
            ->where('company_id', $actor->company_id)
            ->find($reportId);

        if (! $report instanceof ConversationReport) {
            // 404 logique : un signalement d'un autre tenant n'existe pas ici.
            throw ValidationException::withMessages([
                'report' => ['Signalement introuvable.'],
            ]);
        }

        if (! $report->isOpen()) {
            return $report;
        }

        $report->forceFill([
            'status' => $status,
            'reviewed_by_id' => $actor->id,
            'reviewed_at' => now(),
            'resolution' => $resolution,
        ]);
        $report->save();

        $this->journal('chat.report_resolved', [
            'company_id' => $actor->company_id,
            'report_id' => $report->id,
            'status' => $status,
            'reviewed_by_id' => $actor->id,
        ]);

        return $report;
    }

    /**
     * Le traitement de la modération est réservé aux managers du tenant.
     * Aucun nouveau rôle : `Employee::isManager()` existe déjà.
     */
    private function assertCanModerate(Employee $actor): void
    {
        if (! $actor->isManager()) {
            throw ValidationException::withMessages([
                'moderation' => ['Seul un responsable peut traiter les signalements.'],
            ]);
        }
    }

    /**
     * Journalisation structurée : tenant + identifiants, jamais de contenu.
     *
     * @param  array<string, mixed>  $context
     */
    private function journal(string $event, array $context): void
    {
        // Canal par défaut : le journal de l'application. Pas de canal dédié
        // (aucune configuration à ajouter côté déploiement), pas de contenu de
        // message — uniquement des identifiants et le tenant.
        Log::info($event, $context);
    }
}
