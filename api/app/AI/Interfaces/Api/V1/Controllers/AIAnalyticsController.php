<?php

declare(strict_types=1);

namespace App\AI\Interfaces\Api\V1\Controllers;

use App\AI\Services\AiAnalyticsStatsService;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Observabilité IA (usage, coûts, outils, erreurs).
 *
 * Ce contrôleur ne porte plus que le contrat HTTP : résolution du tenant de
 * l'acteur, validation des entrées, forme de la réponse. Les agrégations
 * vivent dans `AiAnalyticsStatsService`.
 */
class AIAnalyticsController extends Controller
{
    public function __construct(
        private readonly AiAnalyticsStatsService $stats,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

        return response()->json([
            'data' => $this->stats->usage($this->companyId($request), $from, $to),
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function costs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        /** @var array{from?: string, to?: string, group_by?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());
        $groupBy = (string) ($validated['group_by'] ?? 'day');

        return response()->json([
            'data' => $this->stats->costs($this->companyId($request), $from, $to, $groupBy),
            'meta' => ['from' => $from, 'to' => $to, 'group_by' => $groupBy],
        ]);
    }

    public function tools(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

        return response()->json([
            'data' => $this->stats->tools($this->companyId($request), $from, $to),
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function errors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

        return response()->json([
            'data' => $this->stats->errors($this->companyId($request), $from, $to),
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Tenant de l'acteur : l'attribut `ai_company_id` (posé par le middleware
     * IA quand il cible une société précise) prime sur la société de
     * l'utilisateur authentifié.
     */
    private function companyId(Request $request): string
    {
        $companyId = $request->attributes->get('ai_company_id');

        if ($companyId === null) {
            /** @var Employee|null $user */
            $user = $request->user();
            $companyId = $user?->company_id;
        }

        return (string) $companyId;
    }
}
