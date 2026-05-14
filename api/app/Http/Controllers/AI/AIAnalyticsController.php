<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AIAnalyticsController extends Controller
{
    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $rows = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->get();

        $usage = $rows
            ->groupBy('company_id')
            ->map(fn ($items, $companyId) => [
                'company_id' => $companyId,
                'total_requests' => $items->count(),
                'total_tokens' => $items->sum(fn ($row) => (int) $row->input_tokens + (int) $row->output_tokens),
                'total_cost_cents' => $items->sum(fn ($row) => (int) $row->cost_cents),
            ])
            ->sortByDesc('total_requests')
            ->values();

        return response()->json([
            'data' => $usage,
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

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $groupBy = $validated['group_by'] ?? 'day';

        $rows = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->get();

        $costs = $rows
            ->groupBy(fn ($row) => $this->periodKey($row->created_at, $groupBy).'|'.$row->provider)
            ->map(function ($items) use ($groupBy) {
                $first = $items->first();

                return [
                    'period' => $this->periodKey($first->created_at, $groupBy),
                    'provider' => $first->provider,
                    'total_cost_cents' => $items->sum(fn ($row) => (int) $row->cost_cents),
                    'requests' => $items->count(),
                    'total_tokens' => $items->sum(fn ($row) => (int) $row->input_tokens + (int) $row->output_tokens),
                ];
            })
            ->sortBy('period')
            ->values();

        return response()->json([
            'data' => $costs,
            'meta' => ['from' => $from, 'to' => $to, 'group_by' => $groupBy],
        ]);
    }

    public function tools(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $rows = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->get();

        $calls = collect();
        foreach ($rows as $row) {
            foreach ($this->toolsCalled($row->tools_called) as $tool) {
                $calls->push([
                    'tool_called' => $tool,
                    'duration_ms' => (int) $row->duration_ms,
                ]);
            }
        }

        $tools = $calls
            ->groupBy('tool_called')
            ->map(fn ($items, $tool) => [
                'tool_called' => $tool,
                'call_count' => $items->count(),
                'avg_response_ms' => round((float) $items->avg('duration_ms'), 1),
            ])
            ->sortByDesc('call_count')
            ->values();

        return response()->json([
            'data' => $tools,
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function errors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $total = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->count();

        $errors = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->whereNotNull('error')
            ->count();

        $recentErrors = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->whereNotNull('error')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'company_id', 'user_id', 'provider', 'error', 'created_at']);

        $successRate = $total > 0 ? round((($total - $errors) / $total) * 100, 1) : 100;

        return response()->json([
            'data' => [
                'total_requests' => $total,
                'total_errors' => $errors,
                'success_rate' => $successRate,
                'recent_errors' => $recentErrors,
            ],
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    private function auditLogQuery(Request $request): Builder
    {
        $companyId = $request->attributes->get('ai_company_id') ?? $request->user()?->company_id;

        return DB::table('ai_audit_logs')->where('company_id', $companyId);
    }

    private function periodKey(mixed $createdAt, string $groupBy): string
    {
        $date = Carbon::parse($createdAt);

        return match ($groupBy) {
            'week' => $date->format('o-W'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * @return array<int, string>
     */
    private function toolsCalled(mixed $toolsCalled): array
    {
        if (is_string($toolsCalled)) {
            $decoded = json_decode($toolsCalled, true);
        } elseif (is_array($toolsCalled)) {
            $decoded = $toolsCalled;
        } else {
            $decoded = [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $tool) => is_scalar($tool) ? (string) $tool : null,
            $decoded,
        )));
    }
}
