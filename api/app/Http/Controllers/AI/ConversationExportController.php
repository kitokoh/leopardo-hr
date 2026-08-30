<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\AI\Services\ConversationExportService;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-23-D07 (issue #6239) — exports asynchrones de conversations IA
 * (idempotents, dead-letter queue AI, replay via `ai:dlq:replay`).
 */
class ConversationExportController extends Controller
{
    public function __construct(private readonly ConversationExportService $exportService) {}

    public function export(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'in:'.implode(',', ConversationExportService::SUPPORTED_FORMATS)],
        ]);

        /** @var Employee $user */
        $user = $request->user();

        try {
            $export = $this->exportService->request(
                (string) $user->company_id,
                (int) $user->id,
                $conversationId,
                $validated['format'] ?? 'json',
            );
        } catch (ModelNotFoundException) {
            abort(404, 'CONVERSATION_NOT_FOUND');
        }

        return response()->json(['data' => $export]);
    }

    public function show(Request $request, int $exportId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $export = \App\AI\Models\AiExport::query()
            ->where('id', $exportId)
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();

        if ($export === null) {
            abort(404, 'EXPORT_NOT_FOUND');
        }

        return response()->json([
            'data' => [
                'id' => $export->id,
                'conversation_id' => $export->conversation_id,
                'format' => $export->format,
                'status' => $export->status,
                'error_message' => $export->error_message,
                'created_at' => optional($export->created_at)->toIso8601String(),
                'updated_at' => optional($export->updated_at)->toIso8601String(),
            ],
        ]);
    }
}
