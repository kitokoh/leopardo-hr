<?php

namespace App\Http\Controllers\AI;

use App\AI\DTOs\AIRequest;
use App\AI\IntentEngine;
use App\AI\Orchestrator;
use App\AI\PendingActionStore;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIGatewayController extends Controller
{
    public function __construct(
        private readonly Orchestrator $orchestrator,
        private readonly IntentEngine $intentEngine,
        private readonly PendingActionStore $pendingActionStore,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $aiRequest = new AIRequest(
            message: $validated['message'],
            userId: (int) $user->id,
            companyId: (string) $user->company_id,
            conversationId: isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
        );

        $result = $this->orchestrator->handle($aiRequest);

        return response()->json(['data' => $result]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $conversations = DB::table('ai_conversations')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->select(['id', 'title', 'token_count', 'created_at', 'updated_at'])
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $conversations->items(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $deleted = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->delete();

        if ($deleted === 0) {
            abort(404);
        }

        return response()->json(['message' => 'Conversation deleted.']);
    }

    public function confirmAction(Request $request, string $pendingActionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $pending = $this->pendingActionStore->pull(
            $pendingActionId,
            (string) $user->company_id,
            (int) $user->id,
        );

        if ($pending === null) {
            abort(404, 'Pending action not found or expired.');
        }

        $result = $this->intentEngine->executeConfirmedWrite(
            $pending['tool'],
            $pending['arguments'],
            (string) $user->company_id,
            (int) $user->id,
        );

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error'], 'data' => $result], 422);
        }

        return response()->json([
            'data' => [
                'status' => 'executed',
                'tool' => $pending['tool'],
                'result' => $result,
            ],
        ]);
    }

    public function rejectAction(Request $request, string $pendingActionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $pending = $this->pendingActionStore->pull(
            $pendingActionId,
            (string) $user->company_id,
            (int) $user->id,
        );

        if ($pending === null) {
            abort(404, 'Pending action not found or expired.');
        }

        return response()->json([
            'data' => [
                'status' => 'rejected',
                'tool' => $pending['tool'],
            ],
        ]);
    }

    public function tools(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $tools = DB::table('ai_tool_registry')
            ->where('active', true)
            ->select(['id', 'name', 'description', 'required_role', 'module', 'active'])
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tools]);
    }
}
