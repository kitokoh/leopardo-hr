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
    // {--format=json|env} (syntaxe invalide) créait un ALIAS 'env' en
    // collision avec l'option globale --env → PHPStan « option env already
    // exists » (erreur interne). Syntaxe corrigée : défaut 'json', valeur
    // validée en début de handle().
    protected $signature = 'infra:probe-availability {--format=json : Format de sortie (json | env)}';

    protected $description = 'Probe Redis (Upstash) et recommande les drivers cache/session (redis → file), queue = database';

    public function handle(): int
    {
        if (! in_array($this->option('format'), ['json', 'env'], true)) {
            $this->error('Format invalide : attendez json ou env.');

            return self::INVALID;
        }

        $redisUp = $this->redisIsReachable();

        // #6557 (audit fiabilité) : le repli CACHE_STORE=file ci-dessous est
        // VOLONTAIRE et documenté — en mode dégradé Redis injoignable, la
        // déduplication idempotente (IdempotencyMiddleware) n'est plus
        // garantie ENTRE instances (store local par instance) ; le middleware
        // absorbe la panne en mode dégradé (jamais de 500) et la fenêtre est
        // documentée dans son docblock. Ne pas retirer ce repli : sans lui,
        // un quota Upstash épuisé gèlerait le boot (cf. incident 2026-08-19).

        $result = [
            'redis' => $redisUp ? 'up' : 'down',
            // Meilleur → pire : Redis si dispo (perf), sinon file (0 quota).
            'CACHE_STORE' => $redisUp ? 'redis' : 'file',
            // #6557 — la déduplication d'idempotence (RTMX #5277) repose sur un
            // cache PARTAGÉ : CACHE_STORE=file la casse silencieusement en
            // multi-instance. Le flag explicite permet au boot/ops de détecter
            // la dégradation au lieu d'un fallback discret.
            'CACHE_DEGRADED' => $redisUp ? '0' : '1',
            'SESSION_DRIVER' => $redisUp ? 'redis' : 'file',
            // Queue : database volontairement FIXE (voir docblock).
            'QUEUE_CONNECTION' => 'database',
        ];

        if (! $redisUp) {
            $this->warn('[infra] Redis injoignable — CACHE_STORE=file : la déduplication idempotence est DÉSACTIVÉE (multi-instance).');
        }

        if ($this->option('format') === 'env') {
            foreach ($result as $key => $value) {
                $this->line($key.'='.$value);
            }

            return self::SUCCESS;
        }

        $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function redisIsReachable(): bool
    {
        try {
            $connection = Redis::connection();

            // ⚠️ Un `ping()` ne suffit PAS (constaté le 2026-09-11).
            // Un Upstash dont le quota de requêtes est épuisé ACCEPTE la
            // connexion et répond à PING ; ce sont les commandes suivantes qui
            // échouent avec « ERR max requests limit exceeded. Limit: 500000 ».
            // Le probe annonçait donc Redis « up » → CACHE_STORE restait `redis`
            // → le boot prenait un verrou Redis pour les migrations
            // (CacheCommandMutex → RedisLock→acquire() → SET) → exception →
            // « Final migration failure » → conteneur jamais sain → Render
            // `update_failed` (21 des 30 derniers déploiements dev). Le repli
            // `file` ne s'engageait jamais.
            //
            // On teste donc une ÉCRITURE réelle, exactement ce que feront le
            // cache et les verrous : si l'écriture échoue, Redis n'est pas
            // utilisable et l'environnement doit dégrader vers `file`.
            $probeKey = 'leopardo:availability-probe';
            // Deux commandes (portable phpredis/Predis, pas d'options variadiques) :
            // SET puis EXPIRE. La première qui échoue prouve que Redis est inutilisable.
            $connection->set($probeKey, (string) time());
            $connection->expire($probeKey, 10);

            // Si l'écriture n'a pas levé, Redis est utilisable (le retour varie
            // selon le client : « OK » via Predis, objet via phpredis).
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
