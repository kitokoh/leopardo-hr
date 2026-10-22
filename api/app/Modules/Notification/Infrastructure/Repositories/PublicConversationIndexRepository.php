<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * #8760 [BOS-CHAT] — Accès à l'index de ROUTAGE plateforme des conversations
 * publiques (`public_conversation_index`, schéma `public`).
 *
 * ⚠️ **Pourquoi ce dépôt existe** (issue #6568, `check-layer-purity`) : la
 * façade `Illuminate\Support\Facades\DB` est réservée aux couches
 * `Interfaces`/`Infrastructure`. Le service applicatif
 * `PublicConversationService` en dépendait directement, ce qui faisait échouer
 * le gate de pureté de couches sur une classe `Application`. La sortie de la
 * dépendance est un dépôt injecté, pas une exception dans l'allowlist : le
 * service reste testable et la contrainte d'architecture reste vivante.
 *
 * ⚠️ **Cette table ne porte AUCUN contenu de message et n'autorise RIEN.**
 * Elle dit seulement *dans quel schéma tenant* chercher un fil. Chaque lecture
 * rejoue ensuite le contrôle d'accès dans son propre contexte tenant — un
 * `thread_id` présent ici ne suffit jamais à lire une conversation.
 */
class PublicConversationIndexRepository
{
    public function __construct(private readonly ?ConnectionInterface $connection = null) {}

    /**
     * Crée ou rafraîchit le pointeur (client → tenant + fil).
     *
     * `updateOrInsert` est idempotent : rejouer l'ouverture d'une conversation
     * ne duplique pas la ligne. Le lien `thread_id` est réécrit au cas où le
     * fil aurait été recréé après purge côté tenant.
     */
    public function remember(int $userId, string $companyId, string $targetSlug, int $threadId): void
    {
        $this->connection()->table('public_conversation_index')->updateOrInsert(
            [
                'user_id' => $userId,
                'company_id' => $companyId,
                'target_slug' => $targetSlug,
            ],
            [
                'thread_id' => $threadId,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Pointeurs d'un client, du plus récemment actif au plus ancien.
     *
     * @return list<object{company_id: string, thread_id: int|string}>
     */
    public function forUser(int $userId, int $limit = 30): array
    {
        /** @var list<object{company_id: string, thread_id: int|string}> $rows */
        $rows = $this->connection()->table('public_conversation_index')
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * Rafraîchit la récence d'un pointeur précis (tri « mes conversations »).
     *
     * Best-effort assumé par l'appelant : un échec ici ne doit pas faire perdre
     * un message déjà persisté.
     */
    public function touch(int $userId, string $companyId, int $threadId): void
    {
        $this->connection()->table('public_conversation_index')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('thread_id', $threadId)
            ->update(['updated_at' => now()]);
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?? DB::connection();
    }
}
