<?php

declare(strict_types=1);

namespace App\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Store one-shot (TTL 15 min) des actions IA en attente de confirmation.
 *
 * BOS-032 (#8222) — le backend est choisi par `ai.pending_action_store` :
 *  - `cache` : comportement historique (rapide, suffisant avec un driver
 *    PARTAGÉ entre workers : redis, memcached, database, dynamodb) ;
 *  - `database` : table `ai_pending_actions` — avec un driver non partagé
 *    (file, array), une action sensible en attente pouvait « disparaître »
 *    quand la confirmation arrivait sur un autre worker ;
 *  - `auto` (défaut) : database si `cache.default` n'est pas un driver
 *    partagé, cache sinon. Décision documentée dans la PR #8222.
 *
 * Le payload transporte `conversation_id` (quand il est connu) : il alimente
 * la clé d'idempotence métier au moment de la confirmation
 * (WriteIdempotencyStore).
 */
class PendingActionStore
{
    private const CACHE_PREFIX = 'ai_pending_action:';

    /** Drivers de cache dont les données sont partagées entre workers/processus. */
    private const SHARED_CACHE_DRIVERS = ['redis', 'memcached', 'database', 'dynamodb', 'octane'];

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function store(string $companyId, int $userId, string $toolName, array $arguments, ?int $conversationId = null): string
    {
        $id = (string) Str::uuid();

        $payload = [
            'company_id' => $companyId,
            'user_id' => $userId,
            'tool' => $toolName,
            'arguments' => $arguments,
            'conversation_id' => $conversationId,
        ];

        if ($this->useDatabase()) {
            DB::table('ai_pending_actions')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'user_id' => $userId,
                'tool' => $toolName,
                'arguments' => json_encode($arguments, JSON_UNESCAPED_UNICODE) ?: '{}',
                'conversation_id' => $conversationId,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
                'created_at' => now(),
            ]);

            return $id;
        }

        Cache::put($this->cacheKey($id), $payload, now()->addMinutes($this->ttlMinutes()));

        return $id;
    }

    /**
     * Consomme (one-shot) une action en attente : retourne son payload puis
     * la supprime. Isolation stricte : company_id ET user_id doivent
     * correspondre, sinon l'action est introuvable (fail-closed).
     *
     * @return array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>, conversation_id: int|null}|null
     */
    public function pull(string $id, string $companyId, int $userId): ?array
    {
        if ($this->useDatabase()) {
            return $this->pullFromDatabase($id, $companyId, $userId);
        }

        /** @var array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>, conversation_id?: int|null}|null $payload */
        $payload = Cache::get($this->cacheKey($id));

        if ($payload === null) {
            return null;
        }

        if ($payload['company_id'] !== $companyId || $payload['user_id'] !== $userId) {
            return null;
        }

        Cache::forget($this->cacheKey($id));

        return $this->normalize($payload);
    }

    public function forget(string $id): void
    {
        if ($this->useDatabase()) {
            DB::table('ai_pending_actions')->where('id', $id)->delete();

            return;
        }

        Cache::forget($this->cacheKey($id));
    }

    /**
     * @return array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>, conversation_id: int|null}|null
     */
    private function pullFromDatabase(string $id, string $companyId, int $userId): ?array
    {
        $row = DB::table('ai_pending_actions')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        // One-shot : la consommation supprime l'entrée (même sémantique que
        // le Cache::forget historique), y compris en cas de lecture
        // concurrente — le delete est idempotent.
        DB::table('ai_pending_actions')->where('id', $id)->delete();

        /** @var array<string, mixed> $record */
        $record = (array) $row;

        /** @var array<string, mixed>|null $arguments */
        $arguments = json_decode((string) $record['arguments'], true);

        return [
            'company_id' => (string) $record['company_id'],
            'user_id' => (int) $record['user_id'],
            'tool' => (string) $record['tool'],
            'arguments' => is_array($arguments) ? $arguments : [],
            'conversation_id' => $record['conversation_id'] !== null ? (int) $record['conversation_id'] : null,
        ];
    }

    /**
     * Normalise un payload cache (le champ conversation_id est apparu avec
     * BOS-032 : absent des entrées écrites avant le déploiement).
     *
     * @param  array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>, conversation_id?: int|null}  $payload
     * @return array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>, conversation_id: int|null}
     */
    private function normalize(array $payload): array
    {
        $conversationId = $payload['conversation_id'] ?? null;

        return [
            'company_id' => $payload['company_id'],
            'user_id' => $payload['user_id'],
            'tool' => $payload['tool'],
            'arguments' => $payload['arguments'],
            'conversation_id' => is_numeric($conversationId) ? (int) $conversationId : null,
        ];
    }

    /**
     * Backend effectif : `database` quand le driver de cache n'est pas
     * partagé entre workers (décision BOS-032, mode `auto`).
     */
    private function useDatabase(): bool
    {
        $configured = config('ai.pending_action_store', 'auto');

        if ($configured === 'database') {
            return true;
        }

        if ($configured === 'cache') {
            return false;
        }

        $default = config('cache.default', '');

        return ! is_string($default) || ! in_array($default, self::SHARED_CACHE_DRIVERS, true);
    }

    private function cacheKey(string $id): string
    {
        return self::CACHE_PREFIX.$id;
    }

    private function ttlMinutes(): int
    {
        $configured = config('ai.pending_action_ttl_minutes', 15);

        return max(1, is_numeric($configured) ? (int) $configured : 15);
    }
}
