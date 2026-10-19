<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\ConversationParticipant;

/**
 * #8746 [BOS-CHAT] — Politique d'administration d'un fil de discussion.
 *
 * Règle unique et volontairement minimale (aucun nouveau rôle plateforme) :
 *   - un `creator` ou un `admin` du fil peut ajouter/retirer des membres ;
 *   - un `creator` ne peut jamais être retiré (sinon un groupe devient
 *     orphelin, sans personne pour l'administrer) ;
 *   - tout participant peut se retirer lui-même (`leave`), y compris un admin,
 *     mais le créateur doit d'abord transmettre son rôle ;
 *   - un non-participant n'a AUCUN droit (et se voit répondre 404, jamais 403 :
 *     un 403 révélerait l'existence du fil à un tiers).
 *
 * La politique ne consulte jamais le contexte métier (commande, réservation,
 * espace pro) : le `subject_type` d'un fil ne donne aucun droit.
 */
final class ConversationParticipantPolicy
{
    /**
     * L'acteur peut-il administrer les membres du fil ?
     */
    public function canAdminister(?ConversationParticipant $actor): bool
    {
        return $actor !== null && $actor->canAdminister();
    }

    /**
     * L'acteur peut-il retirer `$target` du fil ?
     */
    public function canRemove(?ConversationParticipant $actor, ConversationParticipant $target): bool
    {
        if ($actor === null || ! $actor->canAdminister()) {
            return false;
        }

        // Le créateur est indéboulonnable : retirer le créateur laisserait le
        // groupe sans administrateur désigné.
        if ($target->isCreator()) {
            return false;
        }

        // Un admin ne peut pas retirer un autre admin : seul le créateur le
        // peut. Évite qu'un admin évince ses pairs.
        if ($target->role === ConversationParticipant::ROLE_ADMIN && ! $actor->isCreator()) {
            return false;
        }

        return true;
    }

    /**
     * L'acteur peut-il quitter le fil ?
     *
     * Un créateur ne peut pas quitter tant qu'il n'a pas transmis son rôle :
     * {@see ConversationParticipantPolicy::canTransferCreator()} documente la
     * transmission, non implémentée dans cette phase (elle nécessite un
     * endpoint dédié, issue de suivi).
     */
    public function canLeave(?ConversationParticipant $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return ! $actor->isCreator();
    }

    /**
     * L'acteur peut-il promouvoir `$target` au rôle `$role` ?
     */
    public function canChangeRole(?ConversationParticipant $actor, ConversationParticipant $target, string $role): bool
    {
        if ($actor === null || ! $actor->canAdminister()) {
            return false;
        }

        if (! in_array($role, ConversationParticipant::ROLES, true)) {
            return false;
        }

        // Seul le créateur peut nommer un autre créateur ou un admin.
        if ($role !== ConversationParticipant::ROLE_MEMBER && ! $actor->isCreator()) {
            return false;
        }

        // On ne rétrograde jamais le créateur par cette voie.
        return ! $target->isCreator();
    }

    /**
     * Un employé peut-il être ajouté au fil ? (l'ajout lui-même reste borné au
     * tenant par `ConversationService` : un employé d'un autre tenant n'est
     * jamais résolu, donc jamais ajouté).
     */
    public function canAddParticipant(?ConversationParticipant $actor, Employee $candidate): bool
    {
        return $actor !== null && $actor->canAdminister() && $candidate->id !== $actor->employee_id;
    }
}
