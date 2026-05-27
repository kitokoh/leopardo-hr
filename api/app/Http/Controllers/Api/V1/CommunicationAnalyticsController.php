<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunicationEvent;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\V1\Analytics\__invokeCommunicationAnalyticsRequest;

class CommunicationAnalyticsController extends Controller
{
    public function __invoke(__invokeCommunicationAnalyticsRequest $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Employee || ! $actor->company_id || ! ($actor->isPrincipal() || $actor->isHr())) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validated();

        $days = (int) ($validated['days'] ?? 30);
        $since = now()->subDays($days - 1)->startOfDay();

        $base = CommunicationEvent::query()
            ->where('company_id', (string) $actor->company_id)
            ->where('occurred_at', '>=', $since);

        $total = (clone $base)->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $skipped = (clone $base)->where('status', 'skipped')->count();
        $sentOrQueued = (clone $base)->whereIn('status', ['sent', 'queued'])->count();

        return response()->json([
            'data' => [
                'period' => [
                    'days' => $days,
                    'since' => $since->toIso8601String(),
                ],
                'totals' => [
                    'events' => $total,
                    'sent_or_queued' => $sentOrQueued,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'failure_rate' => $total > 0 ? round($failed / $total, 4) : 0.0,
                ],
                'by_channel' => $this->groupedCounts($base, 'channel'),
                'by_status' => $this->groupedCounts($base, 'status'),
                'by_template' => $this->groupedCounts($base, 'template_key'),
                'daily' => $this->dailyCounts($base),
            ],
        ]);
    }

    /**
     * @param  Builder<CommunicationEvent>  $base
     * @return list<array{key: string, count: int}>
     */
    private function groupedCounts(Builder $base, string $column): array
    {
        $allowedColumns = ['channel', 'status', 'template_key'];

        if (! in_array($column, $allowedColumns, true)) {
            return [];
        }

        return (clone $base)
            ->selectRaw($column.' as key, count(*) as aggregate_count')
            ->groupBy($column)
            ->orderByDesc('aggregate_count')
            ->limit(10)
            ->get()
            ->map(static fn (CommunicationEvent $event): array => [
                'key' => (string) ($event->getAttribute('key') ?? 'unknown'),
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<CommunicationEvent>  $base
     * @return list<array{date: string, count: int}>
     */
    private function dailyCounts(Builder $base): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpression = $driver === 'pgsql'
            ? "to_char(occurred_at, 'YYYY-MM-DD')"
            : 'DATE(occurred_at)';

        return (clone $base)
            ->selectRaw($dateExpression.' as event_date, count(*) as aggregate_count')
            ->groupBy('event_date')
            ->orderBy('event_date')
            ->get()
            ->map(static fn (CommunicationEvent $event): array => [
                'date' => (string) ($event->getAttribute('event_date') ?? Carbon::now()->toDateString()),
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }
}
