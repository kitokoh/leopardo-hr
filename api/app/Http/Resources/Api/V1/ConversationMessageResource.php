<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Notification\Domain\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationMessage
 */
class ConversationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_thread_id' => $this->conversation_thread_id,
            'author_id' => $this->author_id,
            'author' => $this->relationLoaded('author') && $this->author ? [
                'id' => $this->author->id,
                'name' => trim(($this->author->first_name ?? '').' '.($this->author->last_name ?? '')),
            ] : null,
            // #8747 — curseur d'ordre : le client trie sur `seq`, jamais sur
            // `created_at` (deux messages d'une même transaction partagent la
            // seconde). `seq` est présent sur les messages historiques depuis
            // le backfill de la migration.
            'seq' => $this->seq,
            // #8747 — clé d'idempotence fournie par le client, renvoyée telle
            // quelle pour qu'il puisse réconcilier un message local en attente
            // avec la ligne serveur.
            'client_message_id' => $this->client_message_id,
            // #8747 — suppression logique : le corps n'est jamais exposé pour un
            // message supprimé, mais la ligne reste dans la page pour ne pas
            // percer l'ordre.
            'deleted' => $this->isDeleted(),
            'body' => $this->visibleBody(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'reply_to' => $this->replyTo !== null ? [
                'id' => $this->replyTo->id,
                'author_id' => $this->replyTo->author_id,
                'body' => $this->replyTo->visibleBody(),
                'deleted' => $this->replyTo->isDeleted(),
            ] : null,
            'attachment' => $this->hasAttachment() ? [
                'original_name' => $this->attachment_original_name,
                'mime_type' => $this->attachment_mime_type,
                'size' => $this->attachment_size,
                // #8748 — l'URL reste une route AUTHENTIFIÉE : le contrôle
                // d'accès est rejoué à chaque téléchargement, jamais délégué à
                // un lien public.
                'url' => route('conversations.messages.attachment', [$this->conversation_thread_id, $this->id]),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
