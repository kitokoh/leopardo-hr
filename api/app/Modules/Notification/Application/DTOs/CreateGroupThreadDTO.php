<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\DTOs;

/**
 * #8746 [BOS-CHAT] — Création d'un fil de discussion de type `group`.
 *
 * Distinct de {@see CreateThreadDTO} (fil binaire employé/manager, PA2-COMM-002) :
 * ici l'auteur choisit explicitement les membres. Les règles de rôle sont
 * appliquées par `ConversationService::createGroup()` — ce DTO ne fait que
 * transporter la demande validée.
 */
final readonly class CreateGroupThreadDTO
{
    /**
     * @param  array<int, int>  $participantIds  Identifiants employés (tenant courant uniquement)
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $participantIds,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
    ) {}

    /**
     * Identifiants cibles, dédupliqués et sans l'auteur (qui devient le
     * `creator` et reçoit son message d'ouverture séparément).
     *
     * @return array<int, int>
     */
    public function distinctParticipantIds(int $authorId): array
    {
        $ids = array_map(static fn (int $id): int => $id, $this->participantIds);
        $ids = array_values(array_unique($ids));

        return array_values(array_filter($ids, static fn (int $id): bool => $id !== $authorId));
    }
}
