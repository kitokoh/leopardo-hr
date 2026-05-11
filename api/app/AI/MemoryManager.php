<?php

namespace App\AI;

use Illuminate\Support\Facades\DB;

class MemoryManager
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{id: int, messages: array<int, array{role: string, content: string}>}
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

                return ['id' => (int) $conversation->id, 'messages' => $messages];
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

        return ['id' => (int) $id, 'messages' => []];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function saveMessages(int $conversationId, array $messages, int $tokenCount = 0): void
    {
        $maxMessages = (int) config('ai.max_conversation_messages', 50);
        $trimmed = array_slice($messages, -$maxMessages);

        DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->update([
                'messages' => json_encode($trimmed),
                'token_count' => $tokenCount,
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
