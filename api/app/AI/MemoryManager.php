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
            /** @var object{id: int, user_id: int, company_id: string, title: string, messages: string, token_count: int, updated_at: mixed, created_at: mixed}|null $conversation */
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
                    'id' => $conversation->id,
                    'messages' => $messages,
                    'token_count' => $conversation->token_count,
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
        $configuredMax = config('ai.max_conversation_messages', 50);
        $maxMessages = is_numeric($configuredMax) ? (int) $configuredMax : 50;
        $trimmed = array_slice($messages, -$maxMessages);

        /** @var mixed $currentValue */
        $currentValue = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->value('token_count');
        $current = is_numeric($currentValue) ? (int) $currentValue : 0;

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
