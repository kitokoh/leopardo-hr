<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Probe l'état des fournisseurs d'infrastructure et recommande les drivers
 * « meilleur → pire » selon la disponibilité réelle (décision 2026-08-21).
 *
 * - Cache / Session : Redis (Upstash) si joignable, sinon `file` (fallback
 *   à vie, aucun quota).
 * - Queue : TOUJOURS `database` — c'est le « meilleur » choix compte tenu
 *   des quotas Upstash (500k req/mois brûlées par le polling, incident
 *   2026-08-19) et du drain GitHub Actions (#5204/#5205) : pas de quota,
 *   pas de split-brain, drainable même quand Render dort ou a épuisé ses
 *   750 h mensuelles.
 *
 * Utilisé par `api/docker-entrypoint.sh` AVANT `config:cache` pour figer des
 * valeurs cohérentes dans le cache de config au boot. Le mode dégradation
 * Upstash (quota épuisé) refuse les connexions rapidement → ping() échoue
 * vite, pas de blocage du boot.
 */
class ProbeAvailabilityCommand extends Command
{
    protected $signature = 'infra:probe-availability {--format=json|env : Format de sortie (json | env)}';

    protected $description = 'Probe Redis (Upstash) et recommande les drivers cache/session (redis → file), queue = database';

    public function handle(): int
    {
        $redisUp = $this->redisIsReachable();

        $result = [
            'redis' => $redisUp ? 'up' : 'down',
            // Meilleur → pire : Redis si dispo (perf), sinon file (0 quota).
            'CACHE_STORE' => $redisUp ? 'redis' : 'file',
            'SESSION_DRIVER' => $redisUp ? 'redis' : 'file',
            // Queue : database volontairement FIXE (voir docblock).
            'QUEUE_CONNECTION' => 'database',
        ];

        if ($this->option('format') === 'env') {
            foreach ($result as $key => $value) {
                $this->line($key.'='.$value);
            }

            return self::SUCCESS;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function redisIsReachable(): bool
    {
        try {
            // Quota épuisé (Upstash) = connexion refusée en < 1 s → exception
            // rapide. Un simple ping avec try/catch suffit pour le failover.
            return Redis::connection()->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
