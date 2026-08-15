<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\AI\LLMClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * QA #2311 — envoi de message dans le chat IA super-admin.
 *
 * Le SPA admin appelle POST /v1/ai/chat (ChatView.vue:168) mais aucune route
 * backend n'existait (la route tenant /api/v1/ai/chat exige un Employee) →
 * envoi de message impossible. Ce contrôleur expose POST /admin/ai/chat :
 * le super-admin répond DANS une conversation tenant existante (lecture
 * cross-tenant), la réponse LLM est générée best-effort.
 */
class PlatformAdminAiChatController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function __construct(
        private readonly LLMClient $llm,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            // Le super-admin répond dans une conversation existante (il n'a
            // pas d'employee_id — la création cross-tenant n'est pas supportée).
            'conversation_id' => ['required', 'integer'],
        ]);

        $conversationId = (int) $validated['conversation_id'];

        $row = DB::table(self::TENANT_SCHEMA.'.ai_conversations')
            ->where('id', $conversationId)
            ->first();

        if ($row === null) {
            return response()->json(['message' => 'CONVERSATION_NOT_FOUND'], 404);
        }

        $messages = json_decode((string) ($row->messages ?? '[]'), true);
        if (! is_array($messages)) {
            $messages = [];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $validated['message'],
            'created_at' => now()->toIso8601String(),
        ];

        $reply = $this->generateReply($messages);
        $messages[] = [
            'role' => 'assistant',
            'content' => $reply,
            'created_at' => now()->toIso8601String(),
        ];

        DB::table(self::TENANT_SCHEMA.'.ai_conversations')
            ->where('id', $conversationId)
            ->update([
                'messages' => json_encode($messages, JSON_THROW_ON_ERROR),
                'token_count' => DB::raw('token_count + 1'),
                'updated_at' => now(),
            ]);

        return response()->json([
            'data' => [
                'conversation_id' => $conversationId,
                'response' => $reply,
                'id' => count($messages),
            ],
        ]);
    }

    /**
     * @param  array<int, array{role?: string|null, content?: string|null, created_at?: string|null}>  $messages
     */
    private function generateReply(array $messages): string
    {
        try {
            $llmMessages = array_map(
                static fn (array $message): array => [
                    'role' => (string) ($message['role'] ?? 'user'),
                    'content' => (string) ($message['content'] ?? ''),
                ],
                $messages
            );

            $response = $this->llm->chat($llmMessages);

            if ($response->failed()) {
                return 'Assistant indisponible : '.($response->error ?? 'erreur LLM');
            }

            return $response->content;
        } catch (Throwable $e) {
            report($e);

            return 'Assistant indisponible ('.$e->getMessage().').';
        }
    }
}
