<?php

namespace App\AI;

use Illuminate\Support\Facades\DB;

class MemoryManager
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{id: int, messages: array<int, array{role: string, content: string}>, token_count: int}
     */
    public function loadOrCreateConversation(int $userId, string $companyId, ?int $conversationId = null, array $context = []): array
    {
        if ($conversationId !== null) {
            $conversation = DB::table('ai_conversations')
                ->where('id', $conversationId)
                ->where('user_id', $userId)
                ->where('company_id', $companyId)
                ->first();

            if ($conversation) {
                /** @var string $messagesJson */
                $messagesJson = $conversation->messages ?? '[]';
                /** @var array<int, array{role: string, content: string}> $messages */
                $messages = json_decode($messagesJson, true) ?: [];

                return [
                    'id' => (int) $conversation->id,
                    'messages' => $messages,
                    'token_count' => (int) ($conversation->token_count ?? 0),
                ];
            }
        }

        $id = DB::table('ai_conversations')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => 'Nouvelle conversation',
            'messages' => '[]',
            'context' => json_encode($context),
            'token_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $id, 'messages' => [], 'token_count' => 0];
    }

    /**
     * BC-23-D10 (issue #6238) : le `token_count` d'une conversation devient un
     * CUMUL (historique + échanges) — c'est ce cumul que le budget de contexte
     * (`ai.budgets.max_context_tokens`) borne, fail-closed.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function saveMessages(int $conversationId, array $messages, int $tokenCount = 0): void
    {
        $maxMessages = (int) config('ai.max_conversation_messages', 50);
        $trimmed = array_slice($messages, -$maxMessages);

        $current = (int) DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->value('token_count');

        DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->update([
                'messages' => json_encode($trimmed),
                'token_count' => $current + max(0, $tokenCount),
                'updated_at' => now(),
            ]);
    }

    public function updateTitle(int $conversationId, string $title): void
    {
        DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->update(['title' => mb_substr($title, 0, 200), 'updated_at' => now()]);
    }
}
