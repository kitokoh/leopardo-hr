<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AIAnalyticsController extends Controller
{
    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

        $rows = $this->auditRows($request, $from, $to);

        $usage = $rows
            ->groupBy('company_id')
            ->map(fn ($items, $companyId) => [
                'company_id' => $companyId,
                'total_requests' => $items->count(),
                'total_tokens' => $items->sum(fn (array $row) => $this->rowTokens($row)),
                'total_cost_cents' => $items->sum(fn (array $row) => $this->rowInt($row, 'cost_cents')),
                // BC-23-D10 (issue #6238) : percentiles de tokens par requête
                // et par workflow (p95) — observabilité des budgets.
                'p95_tokens_per_request' => $this->p95($items->map(fn (array $row) => $this->rowTokens($row))),
                'workflows' => $this->workflowBreakdown($items),
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

        /** @var array{from?: string, to?: string, group_by?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());
        $groupBy = (string) ($validated['group_by'] ?? 'day');

        $rows = $this->auditRows($request, $from, $to);

        $costs = $rows
            ->groupBy(fn (array $row) => $this->periodKey($row['created_at'] ?? null, $groupBy).'|'.$this->rowString($row, 'provider', 'unknown'))
            ->map(function ($items) use ($groupBy) {
                $first = $items->first() ?? [];

                return [
                    'period' => $this->periodKey($first['created_at'] ?? null, $groupBy),
                    'provider' => $this->rowString($first, 'provider', 'unknown'),
                    'total_cost_cents' => $items->sum(fn (array $row) => $this->rowInt($row, 'cost_cents')),
                    'requests' => $items->count(),
                    'total_tokens' => $items->sum(fn (array $row) => $this->rowTokens($row)),
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

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

        $rows = $this->auditRows($request, $from, $to);

        $calls = collect();
        foreach ($rows as $row) {
            foreach ($this->toolsCalled($row['tools_called'] ?? []) as $tool) {
                $calls->push([
                    'tool_called' => $tool,
                    'duration_ms' => $this->rowInt($row, 'duration_ms'),
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

        /** @var array{from?: string, to?: string} $validated */
        $from = (string) ($validated['from'] ?? now()->subDays(30)->toDateString());
        $to = (string) ($validated['to'] ?? now()->toDateString());

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
        $companyId = $request->attributes->get('ai_company_id');

        if ($companyId === null) {
            /** @var Employee|null $user */
            $user = $request->user();
            $companyId = $user?->company_id;
        }

        return DB::table('ai_audit_logs')->where('company_id', $companyId);
    }

    /**
     * Lignes d'audit de la période, sous forme de tableaux typés (les rows
     * Eloquent/Query sont des stdClass dynamiques — inanalysables au niveau
     * max de PHPStan sans ce passage explicite).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function auditRows(Request $request, string $from, string $to): Collection
    {
        /** @var Collection<int, object> $rows */
        $rows = $this->auditLogQuery($request)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->get();

        /** @var Collection<int, array<string, mixed>> $mapped */
        $mapped = $rows->map(static fn (object $row): array => (array) $row);

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowTokens(array $row): int
    {
        return $this->rowInt($row, 'input_tokens') + $this->rowInt($row, 'output_tokens');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowInt(array $row, string $key): int
    {
        return isset($row[$key]) && is_numeric($row[$key]) ? (int) $row[$key] : 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowString(array $row, string $key, string $default = ''): string
    {
        return isset($row[$key]) && is_scalar($row[$key]) ? (string) $row[$key] : $default;
    }

    /**
     * BC-23-D10 (issue #6238) — percentile 95 d'un ensemble de valeurs.
     * Retourne 0 sur un ensemble vide (aucune donnée).
     *
     * @param  iterable<mixed>  $values
     */
    private function p95(iterable $values): int
    {
        $sorted = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $sorted[] = max(0, (int) $value);
            }
        }

        if ($sorted === []) {
            return 0;
        }

        sort($sorted);

        $index = (int) ceil(0.95 * count($sorted)) - 1;

        return $sorted[max(0, $index)];
    }

    /**
     * BC-23-D10 (issue #6238) — ventilation par workflow (colonne `workflow`
     * de ai_audit_logs) : requêtes, tokens totaux et p95 par workflow.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array{workflow: string, requests: int, total_tokens: int, p95_tokens: int}>
     */
    private function workflowBreakdown(Collection $items): array
    {
        return $items
            ->filter(static fn (array $row): bool => isset($row['workflow'])
                && is_string($row['workflow'])
                && $row['workflow'] !== '')
            ->groupBy('workflow')
            ->map(fn ($workflowItems, $workflow) => [
                'workflow' => (string) $workflow,
                'requests' => $workflowItems->count(),
                'total_tokens' => $workflowItems->sum(fn (array $row) => $this->rowTokens($row)),
                'p95_tokens' => $this->p95($workflowItems->map(fn (array $row) => $this->rowTokens($row))),
            ])
            ->sortByDesc('total_tokens')
            ->values()
            ->all();
    }

    private function periodKey(mixed $createdAt, string $groupBy): string
    {
        $date = Carbon::parse($createdAt !== null && is_scalar($createdAt) ? (string) $createdAt : null);

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
            $decoded = null;
        }

        if (! is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $tool) {
            if (is_scalar($tool)) {
                $result[] = (string) $tool;
            }
        }

        return $result;
    }
}
