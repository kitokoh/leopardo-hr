<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * #8760 [BOS-CHAT] — Frontière transactionnelle de la messagerie publique.
 *
 * ⚠️ **Pourquoi cette classe existe** (issue #6568) : `check-layer-purity`
 * interdit les façades Laravel (`DB`, `Log`…) dans les couches `Domain` et
 * `Application`. `PublicConversationService` est une classe *Application* qui
 * a besoin des deux :
 *
 *   - `DB::transaction()` pour créer le fil et son premier message ensemble ;
 *   - `DB::selectOne(... RETURNING ...)` pour allouer `seq` de façon atomique
 *     (un `max() + 1` produirait des doublons sous concurrence) ;
 *   - `Log` pour tracer une notification manquée sans faire échouer un message
 *     déjà persisté.
 *
 * Ces trois besoins sont techniques, pas métier : ils appartiennent donc à
 * l'Infrastructure, et le service applicatif reçoit cette frontière par
 * injection. L'allowlist du gate n'est **pas** touchée — la contrainte
 * d'architecture reste vivante au lieu d'être contournée.
 */
class ConversationTransactionRunner
{
    /**
     * Exécute le callback dans une transaction.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Alloue le prochain `seq` d'un fil, atomiquement.
     *
     * L'`UPDATE ... RETURNING` prend un verrou de ligne et incrémente en une
     * seule instruction : deux messages concurrents obtiennent deux `seq`
     * distincts, jamais le même. `null` = fil introuvable (supprimé entre
     * l'autorisation et l'écriture).
     *
     * @return object{last_message_seq: int|string}|null
     */
    public function nextSeq(int $threadId): ?object
    {
        /** @var object{last_message_seq: int|string}|null $row */
        $row = DB::selectOne(
            'UPDATE conversation_threads SET last_message_seq = last_message_seq + 1 WHERE id = ? RETURNING last_message_seq',
            [$threadId],
        );

        return $row;
    }

    /**
     * Trace non bloquante d'un échec de notification.
     *
     * Un manager non notifié ne doit pas annuler un message déjà persisté : le
     * message existe, le client le verra, la notification est le seul manque.
     */
    public function logNotificationFailure(int $threadId, string $reason): void
    {
        Log::warning('public-conversation: notification des managers impossible', [
            'conversation_thread_id' => $threadId,
            'error' => $reason,
        ]);
    }
}
