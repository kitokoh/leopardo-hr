<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $usage = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->selectRaw('company_id, count(*) as total_requests, sum(total_tokens) as total_tokens, sum(cost) as total_cost')
            ->groupBy('company_id')
            ->orderByDesc('total_requests')
            ->get();

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

        $dateFormat = match ($groupBy) {
            'week' => 'YYYY-IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        $costs = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->selectRaw("to_char(created_at, '{$dateFormat}') as period, provider, sum(cost) as total_cost, count(*) as requests, sum(total_tokens) as total_tokens")
            ->groupBy('period', 'provider')
            ->orderBy('period')
            ->get();

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

        $tools = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->whereNotNull('tool_called')
            ->selectRaw('tool_called, count(*) as call_count, avg(response_time_ms) as avg_response_ms')
            ->groupBy('tool_called')
            ->orderByDesc('call_count')
            ->get();

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

        $total = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->count();

        $errors = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->where('status', 'error')
            ->count();

        $recentErrors = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->where('status', 'error')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'company_id', 'user_id', 'provider', 'error_message', 'created_at']);

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
}
