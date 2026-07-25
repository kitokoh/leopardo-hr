<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * PA2-ADM-005 — Monitoring plateforme lisible.
 *
 * Cross-tenant view of outbound notification health (email/SMS/push/
 * WhatsApp delivery failures) plus a curated list of operational runbooks,
 * so a super-admin can answer "are notifications healthy right now, and
 * what do I do if not?" from a single screen — without SSH-ing into the
 * box or grepping per-tenant logs.
 *
 * `communication_events` lives per-tenant (shared_tenants schema on
 * Postgres), so this walks every company the same way
 * `PlatformCompanyHealthService::portfolio()` does, switching the search
 * path per company and aggregating the recent-failure window in PHP. This
 * is a small, bounded dashboard query (recent window, capped company
 * count), not an analytics pipeline — `CommunicationAnalyticsController`
 * already covers the deeper per-tenant breakdown for HR/managers.
 */
class PlatformNotificationObservabilityService
{
    /**
     * Recent-failure lookback window, in hours.
     */
    private const LOOKBACK_HOURS = 24;

    /**
     * Failure count above this threshold, within the lookback window,
     * flips `alerts.notification_failures` to true.
     */
    private const FAILURE_ALERT_THRESHOLD = 10;

    /**
     * Companies scanned per snapshot call. This is a dashboard summary,
     * not a full platform audit — bounded to keep the request fast.
     */
    private const MAX_COMPANIES_SCANNED = 200;

    /**
     * Most recent failed events surfaced to the super-admin, across all
     * scanned companies combined.
     */
    private const MAX_RECENT_FAILURES = 10;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $failures = $this->recentFailures();

        return [
            'window_hours' => self::LOOKBACK_HOURS,
            'companies_scanned' => $failures['companies_scanned'],
            'totals' => [
                'events' => $failures['total_events'],
                'failed' => $failures['total_failed'],
                'failure_rate' => $failures['total_events'] > 0
                    ? round($failures['total_failed'] / $failures['total_events'], 4)
                    : 0.0,
            ],
            'by_channel' => $failures['by_channel'],
            'recent_failures' => $failures['recent'],
            'runbooks' => $this->runbooks(),
            'alerts' => [
                'notification_failures' => $failures['total_failed'] >= self::FAILURE_ALERT_THRESHOLD,
            ],
            'thresholds' => [
                'notification_failures' => self::FAILURE_ALERT_THRESHOLD,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     companies_scanned: int,
     *     total_events: int,
     *     total_failed: int,
     *     by_channel: array<int, array{channel: string, failed: int}>,
     *     recent: array<int, array{company_id: string, company_name: string, channel: string, template_key: string|null, error_message: string|null, occurred_at: string|null}>,
     * }
     */
    private function recentFailures(): array
    {
        $companies = Company::query()
            ->select(['id', 'name', 'schema_name'])
            ->latest()
            ->limit(self::MAX_COMPANIES_SCANNED)
            ->get();

        $since = now()->subHours(self::LOOKBACK_HOURS);
        $totalEvents = 0;
        $totalFailed = 0;
        $byChannel = [];
        $recent = [];

        foreach ($companies as $company) {
            $this->withTenantSearchPath($company, function () use ($company, $since, &$totalEvents, &$totalFailed, &$byChannel, &$recent): void {
                try {
                    $base = CommunicationEvent::query()
                        ->where('company_id', $company->id)
                        ->where('occurred_at', '>=', $since);

                    $totalEvents += (clone $base)->count();

                    $failedEvents = (clone $base)
                        ->where('status', 'failed')
                        ->orderByDesc('occurred_at')
                        ->limit(self::MAX_RECENT_FAILURES)
                        ->get(['channel', 'template_key', 'error_message', 'occurred_at']);

                    $failedCount = (clone $base)->where('status', 'failed')->count();
                    $totalFailed += $failedCount;

                    foreach ($failedEvents->groupBy('channel') as $channel => $group) {
                        $byChannel[(string) $channel] = ($byChannel[(string) $channel] ?? 0) + $group->count();
                    }

                    foreach ($failedEvents as $event) {
                        $recent[] = [
                            'company_id' => (string) $company->id,
                            'company_name' => (string) $company->name,
                            'channel' => (string) $event->channel,
                            'template_key' => $event->template_key,
                            'error_message' => $event->error_message !== null
                                ? $this->truncate((string) $event->error_message, 200)
                                : null,
                            'occurred_at' => $event->occurred_at->toIso8601String(),
                        ];
                    }
                } catch (Throwable) {
                    // A single tenant with a missing/broken table must not
                    // take down the whole platform snapshot.
                }
            });
        }

        usort($recent, static fn (array $a, array $b): int => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));
        $recent = array_slice($recent, 0, self::MAX_RECENT_FAILURES);

        $byChannelList = [];
        foreach ($byChannel as $channel => $count) {
            $byChannelList[] = ['channel' => $channel, 'failed' => $count];
        }
        usort($byChannelList, static fn (array $a, array $b): int => $b['failed'] <=> $a['failed']);

        return [
            'companies_scanned' => $companies->count(),
            'total_events' => $totalEvents,
            'total_failed' => $totalFailed,
            'by_channel' => $byChannelList,
            'recent' => $recent,
        ];
    }

    /**
     * Curated list of operational runbooks a super-admin needs when this
     * screen shows red: incident response, alerting, uptime, backups,
     * rollback and the notification-specific pieces (ZKTeco kiosk sync).
     * Paths are relative to the repository root (docs/GESTION_PROJET) so
     * they resolve the same way locally and once published.
     *
     * @return array<int, array{key: string, title: string, path: string}>
     */
    private function runbooks(): array
    {
        return [
            ['key' => 'incident_p1', 'title' => 'Incident P1', 'path' => 'docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md'],
            ['key' => 'alerting', 'title' => 'Alerting', 'path' => 'docs/GESTION_PROJET/RUNBOOK_ALERTING.md'],
            ['key' => 'observability', 'title' => 'Observabilité', 'path' => 'docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md'],
            ['key' => 'uptime_monitoring', 'title' => 'Uptime Monitoring', 'path' => 'docs/GESTION_PROJET/RUNBOOK_UPTIME_MONITORING.md'],
            ['key' => 'backup_restore', 'title' => 'Backup & Restore', 'path' => 'docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md'],
            ['key' => 'rollback', 'title' => 'Rollback', 'path' => 'docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md'],
            ['key' => 'operations', 'title' => 'Opérations quotidiennes', 'path' => 'docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md'],
        ];
    }

    private function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength).'…' : $value;
    }

    /**
     * @param  callable(): void  $callback
     */
    private function withTenantSearchPath(Company $company, callable $callback): void // @phpstan-ignore callable.nonCallable
    {
        if (DB::getDriverName() !== 'pgsql') {
            $callback();

            return;
        }

        $searchPathRow = DB::selectOne('SHOW search_path');
        $previous = 'public';
        if (is_object($searchPathRow) && property_exists($searchPathRow, 'search_path') && is_string($searchPathRow->search_path)) {
            $previous = $searchPathRow->search_path;
        }
        DB::statement('SET search_path TO '.$company->getSafeSearchPath());

        try {
            $callback();
        } finally {
            DB::statement("SET search_path TO {$previous}");
        }
    }
}
