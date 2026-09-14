<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsCatalog;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * #7385 — suivi de l'assistant IA, **tous tenants**.
 *
 * Les données existaient déjà (`ai_audit_logs`, `ai_tool_executions`) mais
 * seule une vue PAR TENANT existait (`AIAnalyticsController`). Ici on agrège
 * côté plateforme, pour répondre à : « est-ce que ça tourne, combien ça coûte,
 * qu'est-ce qui casse, et chez qui ».
 *
 * Aucune donnée de prompt/réponse n'est exposée : uniquement des compteurs, des
 * agrégats et des messages d'erreur tronqués — un écran de supervision n'a pas
 * besoin du contenu des conversations.
 */
class PlatformAiMonitoringController extends Controller
{
    private const MAX_RECENT_ERRORS = 20;

    public function __construct(
        private readonly PlatformAiSettingsRepository $settingsRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = is_string($validated['from'] ?? null)
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = is_string($validated['to'] ?? null)
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $logs = DB::table('ai_audit_logs')->whereBetween('created_at', [$from, $to]);

        $totals = (object) (array) $logs
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(input_tokens), 0) as input_tokens')
            ->selectRaw('coalesce(sum(output_tokens), 0) as output_tokens')
            ->selectRaw('coalesce(sum(cost_cents), 0) as cost_cents')
            ->selectRaw('count(*) filter (where error is not null) as errors')
            ->selectRaw('coalesce(round(avg(duration_ms))::int, 0) as avg_duration_ms')
            ->selectRaw('coalesce(round(percentile_disc(0.95) within group (order by duration_ms))::int, 0) as p95_duration_ms')
            ->first();

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'totals' => [
                    'requests' => (int) ($totals->requests ?? 0),
                    'input_tokens' => (int) ($totals->input_tokens ?? 0),
                    'output_tokens' => (int) ($totals->output_tokens ?? 0),
                    'cost_cents' => (int) ($totals->cost_cents ?? 0),
                    'errors' => (int) ($totals->errors ?? 0),
                    'avg_duration_ms' => (int) ($totals->avg_duration_ms ?? 0),
                    'p95_duration_ms' => (int) ($totals->p95_duration_ms ?? 0),
                    'error_rate' => $this->errorRate($totals),
                ],
                'by_tenant' => $this->byTenant($from, $to),
                'by_tool' => $this->byTool($from, $to),
                'by_workflow' => $this->byWorkflow($from, $to),
                'recent_errors' => $this->recentErrors($from, $to),
            ],
        ]);
    }

    /**
     * État réel du dispositif : c'est ce qu'un responsable regarde en premier
     * avant d'annoncer « l'assistant est disponible ».
     */
    public function health(): JsonResponse
    {
        $driver = (string) config('ai.driver');
        $settings = $this->settingsRepository->all();

        $configuredKeys = [];
        foreach (PlatformAiSettingsCatalog::all() as $key => $definition) {
            if ($definition['secret']) {
                $configuredKeys[$key] = (bool) ($settings[$key]['has_value'] ?? false);
            }
        }

        $since = Carbon::now()->subDay();
        $last24h = DB::table('ai_audit_logs')->where('created_at', '>=', $since);

        $lastSuccess = (object) (array) DB::table('ai_audit_logs')
            ->whereNull('error')
            // Pas de orderBy ici : trier sur `created_at` hors agrégat fait
            // échouer Postgres (SQLSTATE 42803) puisque la requête agrège.
            ->selectRaw('max(created_at) as last_success_at')
            ->first();

        $lastError = (object) (array) DB::table('ai_audit_logs')
            ->whereNotNull('error')
            ->selectRaw('max(created_at) as last_error_at')
            ->first();

        $recent = (object) (array) $last24h
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where error is not null) as errors')
            ->first();

        return response()->json([
            'data' => [
                'enabled' => (bool) config('ai.enabled'),
                'driver' => $driver,
                'model' => $this->effectiveModel($driver),
                'provider_key_configured' => $this->providerKeyConfigured($driver, $configuredKeys),
                'configured_keys' => $configuredKeys,
                'last_24h' => [
                    'requests' => (int) ($recent->requests ?? 0),
                    'errors' => (int) ($recent->errors ?? 0),
                ],
                'last_success_at' => $lastSuccess->last_success_at ?? null,
                'last_error_at' => $lastError->last_error_at ?? null,
            ],
        ]);
    }

    private function errorRate(object $totals): float
    {
        $requests = (int) ($totals->requests ?? 0);
        if ($requests === 0) {
            return 0.0;
        }

        return round(((int) ($totals->errors ?? 0) / $requests) * 100, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byTenant(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('ai_audit_logs')
            ->leftJoin('companies', 'companies.id', '=', 'ai_audit_logs.company_id')
            ->whereBetween('ai_audit_logs.created_at', [$from, $to])
            ->groupBy('ai_audit_logs.company_id', 'companies.name')
            ->selectRaw('ai_audit_logs.company_id as company_id')
            ->selectRaw('companies.name as company_name')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(ai_audit_logs.input_tokens + ai_audit_logs.output_tokens), 0) as tokens')
            ->selectRaw('coalesce(sum(ai_audit_logs.cost_cents), 0) as cost_cents')
            ->selectRaw('count(*) filter (where ai_audit_logs.error is not null) as errors')
            ->orderByDesc('requests')
            ->limit(50)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $result[] = [
                'company_id' => $item['company_id'] ?? null,
                'company_name' => $item['company_name'] ?? null,
                'requests' => (int) ($item['requests'] ?? 0),
                'tokens' => (int) ($item['tokens'] ?? 0),
                'cost_cents' => (int) ($item['cost_cents'] ?? 0),
                'errors' => (int) ($item['errors'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byTool(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('ai_tool_executions')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('tool_name')
            ->selectRaw('tool_name')
            ->selectRaw('count(*) as calls')
            ->selectRaw('count(*) filter (where success = false) as failures')
            ->selectRaw('count(*) filter (where stage = \'confirmation_required\') as awaiting_confirmation')
            ->orderByDesc('calls')
            ->limit(50)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $result[] = [
                'tool_name' => $item['tool_name'] ?? null,
                'calls' => (int) ($item['calls'] ?? 0),
                'failures' => (int) ($item['failures'] ?? 0),
                'awaiting_confirmation' => (int) ($item['awaiting_confirmation'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byWorkflow(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('workflow')
            ->groupBy('workflow')
            ->selectRaw('workflow')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as tokens')
            ->orderByDesc('requests')
            ->limit(50)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $result[] = [
                'workflow' => $item['workflow'] ?? null,
                'requests' => (int) ($item['requests'] ?? 0),
                'tokens' => (int) ($item['tokens'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Messages d'erreur TRONQUÉS : on veut la cause, pas le contenu échangé.
     *
     * @return list<array<string, mixed>>
     */
    private function recentErrors(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('ai_audit_logs')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('error')
            ->orderByDesc('created_at')
            ->limit(self::MAX_RECENT_ERRORS)
            ->get(['company_id', 'provider', 'model', 'workflow', 'error', 'created_at']);

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $result[] = [
                'company_id' => $item['company_id'] ?? null,
                'provider' => $item['provider'] ?? null,
                'model' => $item['model'] ?? null,
                'workflow' => $item['workflow'] ?? null,
                'error' => mb_substr((string) ($item['error'] ?? ''), 0, 300),
                'created_at' => $item['created_at'] ?? null,
            ];
        }

        return $result;
    }

    private function effectiveModel(string $driver): ?string
    {
        return match ($driver) {
            'groq' => (string) config('ai.providers.groq.model'),
            'openai' => (string) config('ai.providers.openai.model'),
            'claude' => (string) config('ai.providers.claude.model'),
            default => null,
        };
    }

    /**
     * @param  array<string, bool>  $configuredKeys
     */
    private function providerKeyConfigured(string $driver, array $configuredKeys): bool
    {
        return match ($driver) {
            'groq' => (bool) ($configuredKeys['groq_api_key'] ?? false),
            'openai' => (bool) ($configuredKeys['openai_api_key'] ?? false),
            'claude' => (bool) ($configuredKeys['anthropic_api_key'] ?? false),
            // Le driver « fake » ne nécessite aucune clé.
            default => true,
        };
    }
}
