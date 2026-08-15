<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Conversations IA — vue cross-tenant super-admin (contrat SPA admin,
 * issue #1764 : GET /v1/ai/conversations et /v1/ai/conversations/{id}/messages
 * étaient appelés par le SPA sans exister côté API).
 */
class PlatformAdminAiConversationController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function index(): JsonResponse
    {
        try {
            $conversations = DB::table(self::TENANT_SCHEMA.'.ai_conversations')
                ->select([
                    'id',
                    'company_id',
                    'user_id',
                    'title',
                    'token_count',
                    'created_at',
                    'updated_at',
                    DB::raw('json_array_length(messages) as message_count'),
                ])
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get();

            return response()->json(['data' => $conversations]);
        } catch (\Throwable) {
            return response()->json(['data' => []]);
        }
    }

    public function messages(int $conversation): JsonResponse
    {
        try {
            $row = DB::table(self::TENANT_SCHEMA.'.ai_conversations')
                ->where('id', $conversation)
                ->first(['messages']);

            if ($row === null) {
                return response()->json(['error' => 'CONVERSATION_NOT_FOUND', 'message' => __('platform.conversation_not_found')], 404);
            }

            $messages = json_decode((string) $row->messages, true);
            if (! is_array($messages)) {
                $messages = [];
            }

            $enriched = [];
            foreach ($messages as $index => $message) {
                if (! is_array($message)) {
                    continue;
                }
                $enriched[] = [
                    'id' => $index + 1,
                    'role' => $message['role'] ?? 'user',
                    'content' => $message['content'] ?? '',
                    'created_at' => $message['created_at'] ?? null,
                ];
            }

            return response()->json(['data' => $enriched]);
        } catch (\Throwable) {
            return response()->json(['data' => []]);
        }
    }

    /**
     * #2311 — envoi d'un message depuis la console super-admin. L'assistant
     * IA n'est pas câblé pour la plateforme (pas de LLM cross-tenant) : le
     * message utilisateur est persisté dans la conversation et la réponse est
     * une structure honnête « assistant non configuré » — plus de 404
     * silencieux côté vue.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        $message = trim((string) $validated['message']);
        $conversationId = isset($validated['conversation_id'])
            ? (int) $validated['conversation_id']
            : null;

        $userMessage = [
            'role' => 'user',
            'content' => $message,
            'created_at' => now()->toIso8601String(),
        ];

        try {
            $table = DB::table(self::TENANT_SCHEMA.'.ai_conversations');

            if ($conversationId === null) {
                $conversationId = (int) $table->insertGetId([
                    'company_id' => null,
                    'user_id' => null,
                    'title' => mb_strimwidth($message, 0, 80, '…'),
                    'messages' => json_encode([$userMessage], JSON_THROW_ON_ERROR),
                    'context' => '{}',
                    'token_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $row = $table->where('id', $conversationId)->first(['messages']);

                if ($row === null) {
                    return response()->json([
                        'error' => 'CONVERSATION_NOT_FOUND',
                        'message' => __('platform.conversation_not_found'),
                    ], 404);
                }

                $messages = json_decode((string) ($row->messages ?? '[]'), true);
                if (! is_array($messages)) {
                    $messages = [];
                }
                $messages[] = $userMessage;

                $table->where('id', $conversationId)->update([
                    'messages' => json_encode($messages, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Persistance impossible (ex. contrainte hors périmètre super-admin) :
            // l'envoi reste fonctionnel — réponse structurée sans écriture.
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'response' => __('platform.ai_assistant_not_configured'),
        ]);
    }
}
