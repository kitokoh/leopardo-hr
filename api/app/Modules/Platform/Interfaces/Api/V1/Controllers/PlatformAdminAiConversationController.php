<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        } catch (\Throwable $exception) {
            Log::error('admin.ai.conversations.list_failed', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'AI_CONVERSATIONS_UNAVAILABLE',
                'message' => __('platform.conversations_unavailable'),
            ], 500);
        }
    }

    /**
     * Issue #2311 — POST /admin/ai/chat : envoi d'un message depuis la
     * console super-admin.
     *
     * L'IA conversationnelle est configurée PAR TENANT : la console
     * plateforme est cross-tenant en lecture seule et ne doit PAS écrire de
     * message dans une conversation d'un tenant (isolation). On renvoie donc
     * une réponse structurée et honnête — l'envoi « fonctionne » (plus de
     * 404), mais l'assistant indique explicitement qu'il n'est pas
     * disponible au niveau plateforme. Aucune écriture cross-tenant.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['conversation_id'])) {
            $exists = DB::table(self::TENANT_SCHEMA.'.ai_conversations')
                ->where('id', $validated['conversation_id'])
                ->exists();

            if (! $exists) {
                return response()->json([
                    'error' => 'CONVERSATION_NOT_FOUND',
                    'message' => __('platform.conversation_not_found'),
                ], 404);
            }
        }

        // La console plateforme est cross-tenant en lecture seule : aucun
        // assistant IA plateforme n'existe (issue #2311). On renvoie une
        // erreur explicite et documentée — jamais un 200 factice.
        return response()->json([
            'error' => 'ADMIN_CHAT_UNAVAILABLE',
            'message' => __('platform.admin_chat_unavailable'),
        ], 501);
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
        } catch (\Throwable $exception) {
            Log::error('admin.ai.conversation.messages_failed', [
                'conversation' => $conversation,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'AI_MESSAGES_UNAVAILABLE',
                'message' => __('platform.conversations_unavailable'),
            ], 500);
        }
    }
}
