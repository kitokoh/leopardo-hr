<?php

declare(strict_types=1);

namespace App\Core\Http\Idempotency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BOS-O-05 / #8626 — magasin d'idempotence des écritures PUBLIQUES.
 *
 * Complète `IdempotencyMiddleware` (#5277), qui ne couvre que les requêtes
 * authentifiées (clé de cache scopée par le token). Ici la clé est CLIENTE
 * (`Idempotency-Key`) et persistée en base : elle survit à un redémarrage du
 * cache et la contrainte unique `(scope, idempotency_key)` est la garantie de
 * non-duplication, y compris sous requêtes concurrentes.
 *
 * Cycle de vie d'une clé :
 *   begin()    → 'started' (la clé est réservée, statut in_progress)
 *              → 'replay'  (réponse 2xx déjà mémorisée, à rejouer telle quelle)
 *              → 'in_progress' (une requête concurrente détient la clé)
 *              → 'conflict' (même clé, corps DIFFÉRENT → intention autre)
 *   complete() → mémorise la réponse 2xx (statut + corps)
 *   release()  → libère la clé après un échec (un retry légitime doit pouvoir
 *                réessayer : un échec n'est pas une intention aboutie)
 *
 * Toutes les opérations d'écriture sont best-effort : une panne du magasin ne
 * doit jamais casser l'écriture métier qu'il protège.
 */
class IdempotencyStore
{
    private const TTL_SECONDS = 86400; // 24 h

    /**
     * @return array{state: 'started', id: int}
     *       | array{state: 'replay', status: int, body: array<string, mixed>}
     *       | array{state: 'in_progress'}
     *       | array{state: 'conflict'}
     */
    public function begin(string $scope, string $key, string $requestHash): array
    {
        $this->purgeExpired();

        $existing = $this->find($scope, $key);

        if ($existing !== null) {
            return $this->classify($existing, $requestHash);
        }

        try {
            $id = DB::table('public.idempotency_keys')->insertGetId([
                'scope' => $scope,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'in_progress',
                'expires_at' => now()->addSeconds(self::TTL_SECONDS),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['state' => 'started', 'id' => (int) $id];
        } catch (Throwable $e) {
            // Course : une requête concurrente a inséré la même clé (23505).
            // On relit la ligne gagnante et on applique la même règle — jamais
            // de 500 pour un simple double clic.
            $this->purgeExpired();
            $winner = $this->find($scope, $key);

            if ($winner === null) {
                throw $e;
            }

            return $this->classify($winner, $requestHash);
        }
    }

    /**
     * Mémorise la réponse 2xx d'une intention aboutie.
     *
     * @param  array<string, mixed>  $body
     */
    public function complete(int $id, int $status, array $body): void
    {
        try {
            DB::table('public.idempotency_keys')
                ->where('id', $id)
                ->update([
                    'status' => 'completed',
                    'response_status' => $status,
                    'response_body' => json_encode($body, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            // Best-effort : l'écriture métier a réussi, on ne la casse pas.
            Log::warning('idempotency.complete_failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Libère une clé dont l'intention n'a pas abouti (échec, exception) : le
     * retry suivant doit pouvoir réessayer au lieu de recevoir un 409.
     */
    public function release(int $id): void
    {
        try {
            DB::table('public.idempotency_keys')->where('id', $id)->delete();
        } catch (Throwable $e) {
            Log::warning('idempotency.release_failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{state: 'replay', status: int, body: array<string, mixed>}|array{state: 'in_progress'}|array{state: 'conflict'}
     */
    private function classify(array $row, string $requestHash): array
    {
        if ((string) ($row['request_hash'] ?? '') !== $requestHash) {
            return ['state' => 'conflict'];
        }

        if ((string) ($row['status'] ?? '') === 'completed') {
            $rawBody = $row['response_body'] ?? null;
            /** @var array<string, mixed> $body */
            $body = is_string($rawBody)
                ? (json_decode($rawBody, true) ?? [])
                : (array) $rawBody;

            return [
                'state' => 'replay',
                'status' => (int) ($row['response_status'] ?? 0),
                'body' => $body,
            ];
        }

        return ['state' => 'in_progress'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(string $scope, string $key): ?array
    {
        $row = DB::table('public.idempotency_keys')
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        return $row === null ? null : (array) $row;
    }

    private function purgeExpired(): void
    {
        try {
            DB::table('public.idempotency_keys')->where('expires_at', '<=', now())->delete();
        } catch (Throwable $e) {
            Log::warning('idempotency.purge_failed', ['error' => $e->getMessage()]);
        }
    }
}
