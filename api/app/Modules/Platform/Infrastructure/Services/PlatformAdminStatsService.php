<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Agrégats du cockpit super-admin (issue #1764).
 *
 * Extrait de `PlatformAdminDashboardController` (404 lignes, dont 20 méthodes
 * privées) : le contrôleur ne garde que le contrat HTTP (forme de la réponse,
 * action de dismissal). Les lectures cross-tenant et la construction des
 * alertes vivent ici.
 *
 * Note : les lectures cross-tenant ciblent le schéma partagé `shared_tenants`
 * (mode de déploiement par défaut de la plateforme) ; les tenants en schéma
 * dédié (tenancy_type != shared) ne sont pas couverts par ces agrégats — même
 * limite que les autres lectures plateforme.
 *
 * Politique d'erreur conservée telle quelle : toute source indisponible
 * remonte un 503 explicite (spec #3001) plutôt qu'un zéro trompeur.
 */
class PlatformAdminStatsService
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'totalUsers' => $this->tenantCount('employees'),
            'totalCompanies' => $this->publicCount('companies'),
            'activeSubscriptions' => $this->tenantWhere('subscriptions', 'status', 'active'),
            'monthlyRevenue' => $this->monthlyRevenue(),
            'newUsersToday' => $this->tenantCreatedToday('employees'),
            'newCompaniesToday' => $this->publicCreatedToday('companies'),
            'supportTickets' => $this->publicWhere('platform_support_tickets', 'status', 'open'),
            'systemHealth' => $this->systemHealth(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activities(): Collection
    {
        return collect()
            ->merge($this->companyActivities())
            ->merge($this->supportTicketActivities())
            ->merge($this->edgeSyncActivities())
            ->merge($this->signupActivities())
            ->sortByDesc('created_at')
            ->take(25)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alerts(): array
    {
        $dismissed = $this->dismissedAlertKeys();

        return array_values(array_filter([
            $this->redisAlert(),
            $this->queueDepthAlert(),
            $this->failedJobsAlert(),
            $this->licensesExpiringAlert(),
            $this->trialsExpiringAlert(),
            $this->highPriorityTicketsAlert(),
        ], fn (?array $alert): bool => $alert !== null && ! in_array($alert['id'], $dismissed, true)));
    }

    // ── Stats helpers ────────────────────────────────────────────────────────

    private function tenantCount(string $table): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /**
     * La table existe-t-elle dans le schéma partagé ?
     *
     * Certaines sources du cockpit sont OPTIONNELLES (`edge_nodes` n'existe
     * que sur les déploiements qui exploitent EdgeSync). Les interroger sans
     * garde faisait remonter un 503 sur tout le dashboard — y compris sur une
     * base de test ou un environnement sans EdgeSync.
     */
    private function tenantTableExists(string $table): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT 1
                   FROM information_schema.tables
                  WHERE table_schema = ?
                    AND table_name = ?
                  LIMIT 1',
                [self::TENANT_SCHEMA, $table]
            );

            return $row !== null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function publicCount(string $table): int
    {
        try {
            return (int) DB::table('public.'.$table)->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function tenantWhere(string $table, string $column, mixed $value): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)->where($column, $value)->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function publicWhere(string $table, string $column, mixed $value): int
    {
        try {
            return (int) DB::table('public.'.$table)->where($column, $value)->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function tenantCreatedToday(string $table): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)
                ->where('created_at', '>=', Carbon::today()->startOfDay())
                ->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function publicCreatedToday(string $table): int
    {
        try {
            return (int) DB::table('public.'.$table)
                ->where('created_at', '>=', Carbon::today()->startOfDay())
                ->count();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function monthlyRevenue(): float
    {
        try {
            $total = DB::table(self::TENANT_SCHEMA.'.invoices')
                ->where('status', 'paid')
                ->where('due_date', '>=', Carbon::now()->startOfMonth()->toDateString())
                ->where('due_date', '<=', Carbon::now()->endOfMonth()->toDateString())
                ->sum('total');

            return round((float) $total, 2);
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function systemHealth(): string
    {
        try {
            DB::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }

        $queue = config('queue.default');
        if ($queue === 'redis') {
            try {
                Redis::connection()->ping();
            } catch (\Throwable $e) {
                $this->reportDashboardFailure(__FUNCTION__.':redis', $e);
            }
        }

        return 'good';
    }

    // ── Activities ───────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function companyActivities(): array
    {
        try {
            return DB::table('public.companies')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'name', 'status', 'created_at'])
                ->map(fn ($row): array => [
                    'id' => 'company-'.$row->id,
                    'type' => 'company_created',
                    'message' => __('platform.activity_company_created', ['name' => $row->name]),
                    'entity' => 'company',
                    'created_at' => $row->created_at,
                ])
                ->all();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function supportTicketActivities(): array
    {
        try {
            return DB::table('public.platform_support_tickets')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'company_id', 'subject', 'priority', 'created_at'])
                ->map(fn ($row): array => [
                    'id' => 'ticket-'.$row->id,
                    'type' => 'support_ticket',
                    'message' => __('platform.activity_support_ticket', ['subject' => $row->subject]),
                    'entity' => 'support_ticket',
                    'created_at' => $row->created_at,
                ])
                ->all();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function edgeSyncActivities(): array
    {
        // `edge_nodes` est une table OPTIONNELLE : elle n'existe que sur les
        // déploiements qui exploitent EdgeSync. Son absence ne doit pas faire
        // tomber tout le cockpit super-admin en 503 — c'était le cas, et le
        // moindre environnement sans EdgeSync (dont la base de test) rendait
        // `/admin/dashboard/activities` inutilisable.
        if (! $this->tenantTableExists('edge_nodes')) {
            return [];
        }

        try {
            return DB::table(self::TENANT_SCHEMA.'.edge_nodes')
                ->whereNotNull('last_sync_at')
                ->orderByDesc('last_sync_at')
                ->limit(10)
                ->get(['id', 'name', 'last_sync_at'])
                ->map(fn ($row): array => [
                    'id' => 'edge-'.$row->id,
                    'type' => 'edge_sync',
                    'message' => __('platform.activity_edge_sync', ['name' => $row->name]),
                    'entity' => 'edge_node',
                    'created_at' => $row->last_sync_at,
                ])
                ->all();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function signupActivities(): array
    {
        try {
            return DB::table(self::TENANT_SCHEMA.'.employees')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'first_name', 'last_name', 'email', 'created_at'])
                ->map(fn ($row): array => [
                    'id' => 'user-'.$row->id,
                    'type' => 'user_signup',
                    'message' => __('platform.activity_user_signup', ['name' => trim("{$row->first_name} {$row->last_name}"), 'email' => $row->email]),
                    'entity' => 'employee',
                    'created_at' => $row->created_at,
                ])
                ->all();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    // ── Alerts ───────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function dismissedAlertKeys(): array
    {
        try {
            return DB::table('public.platform_alert_dismissals')->pluck('alert_key')->all();
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return null (toujours — une panne Redis remonte un 503 via reportDashboardFailure, spec #3001) */
    private function redisAlert(): null
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis' && config('session.driver') !== 'redis') {
            return null;
        }

        try {
            Redis::connection()->ping();

            return null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function queueDepthAlert(): ?array
    {
        if (config('queue.default') !== 'redis') {
            return null;
        }

        try {
            $depth = (int) Redis::connection()->llen('queues:default');
            if ($depth < 20) {
                return null;
            }

            return $this->alert('queue_depth', 'warning', __('platform.alert_queue_depth', ['count' => $depth]));
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function failedJobsAlert(): ?array
    {
        try {
            $failed = (int) DB::table('public.failed_jobs')->count();

            return $failed > 0
                ? $this->alert('failed_jobs', 'warning', __('platform.alert_failed_jobs', ['count' => $failed]))
                : null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function licensesExpiringAlert(): ?array
    {
        if (! $this->tenantTableExists('edge_nodes')) {
            return null;
        }

        try {
            $count = (int) DB::table(self::TENANT_SCHEMA.'.edge_nodes')
                ->where('license_expires_at', '>=', Carbon::now()->toDateTimeString())
                ->where('license_expires_at', '<=', Carbon::now()->addDays(30)->toDateTimeString())
                ->count();

            return $count > 0
                ? $this->alert('licenses_expiring', 'warning', "{$count} licence(s) Edge expirent sous 30 jours.")
                : null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function trialsExpiringAlert(): ?array
    {
        try {
            $count = (int) DB::table('public.companies')
                ->where('status', 'trial')
                ->where('subscription_end', '>=', Carbon::today()->toDateString())
                ->where('subscription_end', '<=', Carbon::today()->addDays(7)->toDateString())
                ->count();

            return $count > 0
                ? $this->alert('trials_expiring', 'warning', __('platform.alert_trials_expiring', ['count' => $count]))
                : null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    /** @return array<string, mixed>|null */
    private function highPriorityTicketsAlert(): ?array
    {
        try {
            $count = (int) DB::table('public.platform_support_tickets')
                ->where('status', 'open')
                ->whereIn('priority', ['high', 'urgent'])
                ->count();

            return $count > 0
                ? $this->alert('high_priority_tickets', 'warning', __('platform.alert_high_priority_tickets', ['count' => $count]))
                : null;
        } catch (\Throwable $e) {
            $this->reportDashboardFailure(__FUNCTION__, $e);
        }
    }

    private function reportDashboardFailure(string $operation, \Throwable $exception): never
    {
        Log::error('Platform admin dashboard data source failed', [
            'operation' => $operation,
            'exception' => $exception,
        ]);

        throw new HttpException(503, 'Platform dashboard data is temporarily unavailable.');
    }

    /** @return array<string, mixed> */
    private function alert(string $id, string $level, string $message): array
    {
        return [
            'id' => $id,
            'level' => $level,
            'message' => $message,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
