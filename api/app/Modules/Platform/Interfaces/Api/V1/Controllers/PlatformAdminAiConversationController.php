<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
}
