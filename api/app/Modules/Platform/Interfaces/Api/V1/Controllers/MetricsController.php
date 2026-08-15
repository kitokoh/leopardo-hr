<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'companies' => $this->companyCounts(),
            'employees' => $this->employeeCounts(),
            'system' => $this->systemMetrics(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function companyCounts(): array
    {
        try {
            $total = DB::table('companies')->count();
            $active = DB::table('companies')->where('status', 'active')->count();
            $trial = DB::table('companies')->where('status', 'trial')->count();

            return compact('total', 'active', 'trial');
        } catch (\Throwable $e) {
            Log::error('Platform metrics data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new HttpException(503, 'Platform metrics are temporarily unavailable.');
        }
    }

    private function employeeCounts(): array
    {
        try {
            $total = DB::table('employees')->count();
            $active = DB::table('employees')->where('status', 'active')->count();

            return compact('total', 'active');
        } catch (\Throwable $e) {
            Log::error('Platform metrics data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new HttpException(503, 'Platform metrics are temporarily unavailable.');
        }
    }

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
}
