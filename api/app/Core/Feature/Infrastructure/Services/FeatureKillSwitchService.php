<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Feature\Domain\Models\FeatureKillSwitch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MAT-010 (#5868) — feature flags & kill switch.
 *
 * Interrupteur global par feature/module : une bascule active stoppe la
 * feature pour toute la plateforme, sans suppression de données. La
 * résolution est fail-closed (état actif → feature coupée, quel que soit le
 * tenant) et mise en cache 60 s (une seule requête pour toutes les clés).
 *
 * Le point d'intégration unique est `Company::hasFeature()` : tous les gates
 * (middleware module, FeatureFlag, resources) héritent du kill switch sans
 * modification.
 *
 * Toute bascule est idempotente, horodatée (`toggled_by`/`toggled_at`/
 * `reason` en base) et journalisée sur le canal d'audit JSON.
 */
final class FeatureKillSwitchService
{
    private const CACHE_KEY = 'feature_kill_switches.active';

    private const CACHE_TTL_SECONDS = 60;

    public function isKilled(string $key): bool
    {
        return in_array($key, $this->activeKilledKeys(), true);
    }

    /**
     * Active le kill switch pour une feature (idempotent).
     */
    public function kill(string $key, string $reason, ?string $actor = null): void
    {
        $switch = FeatureKillSwitch::query()->firstOrNew(['feature_key' => $key]);
        $wasActive = (bool) $switch->is_active;

        $switch->is_active = true;
        $switch->reason = $reason !== '' ? $reason : null;
        $switch->toggled_by = $actor;
        $switch->toggled_at = Carbon::now();
        $switch->save();

        if (! $wasActive) {
            $this->forgetCache();
            $this->audit('feature_kill_switch.activated', $key, $actor, $reason);
        }
    }

    /**
     * Désactive le kill switch (idempotent : ne fait rien si déjà inactif).
     */
    public function revive(string $key, ?string $actor = null): void
    {
        $switch = FeatureKillSwitch::query()->where('feature_key', $key)->first();

        if ($switch === null || ! (bool) $switch->is_active) {
            return;
        }

        $switch->is_active = false;
        $switch->toggled_by = $actor;
        $switch->toggled_at = Carbon::now();
        $switch->save();

        $this->forgetCache();
        $this->audit('feature_kill_switch.revoked', $key, $actor, null);
    }

    /**
     * État complet des interrupteurs (actifs et inactifs), trié par clé.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return FeatureKillSwitch::query()
            ->orderBy('feature_key')
            ->get()
            ->map(function (FeatureKillSwitch $switch): array {
                return [
                    'feature_key' => $switch->feature_key,
                    'is_active' => (bool) $switch->is_active,
                    'reason' => $switch->reason,
                    'toggled_by' => $switch->toggled_by,
                    'toggled_at' => $switch->toggled_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function activeKilledKeys(): array
    {
        $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            if (! schemaTableExists('feature_kill_switches')) {
                return [];
            }

            $keys = [];

            foreach (FeatureKillSwitch::query()->where('is_active', true)->get() as $switch) {
                $keys[] = $switch->feature_key;
            }

            return $keys;
        });

        return is_array($cached) ? $cached : [];
    }

    private function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function audit(string $action, string $key, ?string $actor, ?string $reason): void
    {
        Log::channel('audit')->info($action, [
            'feature_key' => $key,
            'actor' => $actor ?? 'unknown',
            'reason' => $reason,
            'correlation_id' => correlation_id(),
        ]);
    }
}
