<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Cockpit super-admin — contrat SPA front/admin-dashboard (issue #1764).
 *
 * Les endpoints /admin/dashboard/* étaient appelés par le SPA admin sans
 * exister côté API (404 en production). Implémentation côté API (décision
 * produit) : agrégats réels depuis public.* et shared_tenants.*.
 *
 * Note : les lectures cross-tenant ciblent le schéma partagé
 * `shared_tenants` (mode de déploiement par défaut de la plateforme) ;
 * les tenants en schéma dédié (tenancy_type != shared) ne sont pas couverts
 * par ces agrégats — même limite que les autres lectures plateforme.
 */
class PlatformAdminDashboardController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function stats(): JsonResponse
    {
        return response()->json([
            'totalUsers' => $this->tenantCount('employees'),
            'totalCompanies' => $this->publicCount('companies'),
            'activeSubscriptions' => $this->tenantWhere('subscriptions', 'status', 'active'),
            'monthlyRevenue' => $this->monthlyRevenue(),
            'newUsersToday' => $this->tenantCreatedToday('employees'),
            'newCompaniesToday' => $this->publicCreatedToday('companies'),
            'supportTickets' => $this->publicWhere('platform_support_tickets', 'status', 'open'),
            'systemHealth' => $this->systemHealth(),
        ]);
    }

    public function activities(): JsonResponse
    {
        $activities = collect()
            ->merge($this->companyActivities())
            ->merge($this->supportTicketActivities())
            ->merge($this->edgeSyncActivities())
            ->merge($this->signupActivities())
            ->sortByDesc('created_at')
            ->take(25)
            ->values();

        return response()->json(['data' => $activities]);
    }

    public function alerts(): JsonResponse
    {
        $dismissed = $this->dismissedAlertKeys();
        $alerts = array_values(array_filter([
            $this->redisAlert(),
            $this->queueDepthAlert(),
            $this->failedJobsAlert(),
            $this->licensesExpiringAlert(),
            $this->trialsExpiringAlert(),
            $this->highPriorityTicketsAlert(),
        ], fn (?array $alert): bool => $alert !== null && ! in_array($alert['id'], $dismissed, true)));

        return response()->json(['data' => $alerts]);
    }

    public function dismissAlert(Request $request, string $alertKey): JsonResponse
    {
        /** @var SuperAdmin|null $actor */
        $actor = $request->user();
        DB::table('platform_alert_dismissals')->updateOrInsert(
            ['alert_key' => $alertKey],
            ['dismissed_by' => $actor?->id, 'created_at' => now()]
        );

        return response()->json(['status' => 'dismissed'], 202);
    }

    // ── Stats helpers ───────────────────────────────────────────────────

    private function tenantCount(string $table): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function publicCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function tenantWhere(string $table, string $column, mixed $value): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)->where($column, $value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function publicWhere(string $table, string $column, mixed $value): int
    {
        try {
            return (int) DB::table($table)->where($column, $value)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function tenantCreatedToday(string $table): int
    {
        try {
            return (int) DB::table(self::TENANT_SCHEMA.'.'.$table)
                ->where('created_at', '>=', Carbon::today()->startOfDay())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function publicCreatedToday(string $table): int
    {
        try {
            return (int) DB::table($table)
                ->where('created_at', '>=', Carbon::today()->startOfDay())
                ->count();
        } catch (\Throwable) {
            return 0;
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
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function systemHealth(): string
    {
        try {
            DB::selectOne('SELECT 1');
        } catch (\Throwable) {
            return 'error';
        }

        $queue = config('queue.default');
        if ($queue === 'redis') {
            try {
                Redis::connection()->ping();
            } catch (\Throwable) {
                return 'warning';
            }
        }

        return 'good';
    }

    // ── Activities ──────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function companyActivities(): array
    {
        try {
            return DB::table('companies')
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
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function supportTicketActivities(): array
    {
        try {
            return DB::table('platform_support_tickets')
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
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function edgeSyncActivities(): array
    {
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
        } catch (\Throwable) {
            return [];
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
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Alerts ──────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function dismissedAlertKeys(): array
    {
        try {
            return DB::table('platform_alert_dismissals')->pluck('alert_key')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function redisAlert(): ?array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis' && config('session.driver') !== 'redis') {
            return null;
        }

        try {
            Redis::connection()->ping();

            return null;
        } catch (\Throwable) {
            return $this->alert('redis_unreachable', 'critical', __('platform.alert_redis_unreachable'));
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

            return $this->alert('queue_depth', 'warning', "File d'attente élevée : {$depth} jobs en attente.");
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function failedJobsAlert(): ?array
    {
        try {
            $failed = (int) DB::table('failed_jobs')->count();

            return $failed > 0
                ? $this->alert('failed_jobs', 'warning', "{$failed} job(s) en échec — vérifier la file.")
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function licensesExpiringAlert(): ?array
    {
        try {
            $count = (int) DB::table(self::TENANT_SCHEMA.'.edge_nodes')
                ->where('license_expires_at', '>=', Carbon::now()->toDateTimeString())
                ->where('license_expires_at', '<=', Carbon::now()->addDays(30)->toDateTimeString())
                ->count();

            return $count > 0
                ? $this->alert('licenses_expiring', 'warning', "{$count} licence(s) Edge expirent sous 30 jours.")
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function trialsExpiringAlert(): ?array
    {
        try {
            $count = (int) DB::table('companies')
                ->where('status', 'trial')
                ->where('subscription_end', '>=', Carbon::today()->toDateString())
                ->where('subscription_end', '<=', Carbon::today()->addDays(7)->toDateString())
                ->count();

            return $count > 0
                ? $this->alert('trials_expiring', 'warning', "{$count} essai(s) expirent sous 7 jours.")
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function highPriorityTicketsAlert(): ?array
    {
        try {
            $count = (int) DB::table('platform_support_tickets')
                ->where('status', 'open')
                ->whereIn('priority', ['high', 'urgent'])
                ->count();

            return $count > 0
                ? $this->alert('high_priority_tickets', 'warning', "{$count} ticket(s) support haute priorité ouverts.")
                : null;
        } catch (\Throwable) {
            return null;
        }
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
