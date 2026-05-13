<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlatformMetricsOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'revenue' => $this->revenueMetrics(),
                'companies' => $this->companyMetrics(),
                'subscriptions' => $this->subscriptionMetrics(),
                'billing' => $this->billingMetrics(),
                'system' => $this->systemMetrics(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function revenueMetrics(): array
    {
        $mrr = $this->monthlyRecurringRevenue();
        $collected30d = $this->sumPaymentsSince(now()->subDays(30));
        $overdue = $this->sumInvoicesByStatus('overdue');

        return [
            'currency' => $this->primaryCurrency(),
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'collected_30d' => $collected30d,
            'overdue_total' => $overdue,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function companyMetrics(): array
    {
        return $this->safeArray(fn (): array => [
            'total' => (int) DB::table('companies')->count(),
            'active' => (int) DB::table('companies')->where('status', 'active')->count(),
            'trial' => (int) DB::table('companies')->where('status', 'trial')->count(),
            'suspended' => (int) DB::table('companies')->where('status', 'suspended')->count(),
            'expired' => (int) DB::table('companies')->where('status', 'expired')->count(),
        ], [
            'total' => 0,
            'active' => 0,
            'trial' => 0,
            'suspended' => 0,
            'expired' => 0,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function subscriptionMetrics(): array
    {
        return $this->safeArray(fn (): array => [
            'total' => (int) DB::table($this->tenantTable('subscriptions'))->count(),
            'active' => (int) DB::table($this->tenantTable('subscriptions'))->where('status', 'active')->count(),
            'trial' => (int) DB::table($this->tenantTable('subscriptions'))->where('status', 'trial')->count(),
            'past_due' => (int) DB::table($this->tenantTable('subscriptions'))->where('status', 'past_due')->count(),
            'cancelled_30d' => (int) DB::table($this->tenantTable('subscriptions'))
                ->where('status', 'cancelled')
                ->where('cancelled_at', '>=', now()->subDays(30))
                ->count(),
        ], [
            'total' => 0,
            'active' => 0,
            'trial' => 0,
            'past_due' => 0,
            'cancelled_30d' => 0,
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function billingMetrics(): array
    {
        return $this->safeArray(fn (): array => [
            'invoices_total' => (int) DB::table($this->tenantTable('invoices'))->count(),
            'invoices_paid' => (int) DB::table($this->tenantTable('invoices'))->where('status', 'paid')->count(),
            'invoices_overdue' => (int) DB::table($this->tenantTable('invoices'))->where('status', 'overdue')->count(),
            'payments_completed_30d' => (int) DB::table($this->tenantTable('payments'))
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ], [
            'invoices_total' => 0,
            'invoices_paid' => 0,
            'invoices_overdue' => 0,
            'payments_completed_30d' => 0,
        ]);
    }

    /**
     * @return array<string, string|int>
     */
    private function systemMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage_mb' => (int) round(memory_get_usage(true) / 1048576),
            'cache_driver' => (string) config('cache.default'),
            'queue_driver' => (string) config('queue.default'),
            'db_driver' => (string) config('database.default'),
        ];
    }

    private function monthlyRecurringRevenue(): float
    {
        return $this->safeFloat(function (): float {
            $value = DB::table('companies')
                ->join('plans', 'plans.id', '=', 'companies.plan_id')
                ->whereIn('companies.status', ['active', 'trial'])
                ->sum('plans.price_monthly');

            return round((float) $value, 2);
        });
    }

    private function sumPaymentsSince(\DateTimeInterface $since): float
    {
        return $this->safeFloat(function () use ($since): float {
            $value = DB::table($this->tenantTable('payments'))
                ->where('status', 'completed')
                ->where('created_at', '>=', $since)
                ->sum('amount');

            return round((float) $value, 2);
        });
    }

    private function sumInvoicesByStatus(string $status): float
    {
        return $this->safeFloat(function () use ($status): float {
            $value = DB::table($this->tenantTable('invoices'))
                ->where('status', $status)
                ->sum('total');

            return round((float) $value, 2);
        });
    }

    private function primaryCurrency(): string
    {
        return $this->safeString(function (): string {
            $currency = DB::table('companies')
                ->select('currency', DB::raw('count(*) as count'))
                ->groupBy('currency')
                ->orderByDesc('count')
                ->value('currency');

            return is_string($currency) && $currency !== '' ? $currency : 'DZD';
        }, 'DZD');
    }

    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function safeArray(callable $callback, array $fallback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  callable(): float  $callback
     */
    private function safeFloat(callable $callback): float
    {
        try {
            return $callback();
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  callable(): string  $callback
     */
    private function safeString(callable $callback, string $fallback): string
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
