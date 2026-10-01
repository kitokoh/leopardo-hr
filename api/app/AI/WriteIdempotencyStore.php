<?php

declare(strict_types=1);

namespace App\AI;

use Illuminate\Support\Facades\DB;

/**
 * BOS-032 (#8222) — idempotence MÉTIER des write-tools IA.
 *
 * Le PendingActionStore protège du double-clic (one-shot, TTL), mais pas du
 * retry métier : une confirmation rejouée (retry réseau après consommation
 * du pending, ou nouvelle proposition confirmée pour la MÊME intention dans
 * la MÊME conversation) produisait un second effet (deux absences identiques,
 * deux annonces…). Ce store persiste le résultat d'une intention exécutée :
 *
 *  - clé = sha256(company_id | conversation_id | tool | empreinte(args)) —
 *    « même intention confirmée deux fois = un seul effet » ;
 *  - le résultat initial est retourné au rejeu avec le marqueur
 *    `idempotent_replay` (jamais de ré-exécution) ;
 *  - `pending_action_id` est indexé pour servir le retry réseau sur le MÊME
 *    pending même après consommation du store one-shot ;
 *  - TTL (défaut 24 h, `ai.write_idempotency_ttl_hours`) : la table ne gonfle
 *    pas ; une intention reconfirmée après expiration est une NOUVELLE
 *    intention (le TTL des pending actions est de 15 min).
 *
 * Seuls les résultats SANS erreur sont persistés : une erreur n'a produit
 * aucun effet métier, un retry légitime doit pouvoir retenter.
 */
class WriteIdempotencyStore
{
    private const TABLE = 'ai_write_idempotency';

    /**
     * Empreinte stable des arguments : tri récursif des clés puis JSON —
     * deux payloads structurellement identiques donnent la même empreinte
     * quel que soit l'ordre des clés produit par le LLM.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function argumentsHash(array $arguments): string
    {
        return hash('sha256', json_encode($this->canonicalize($arguments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * Clé d'idempotence d'une intention : la conversation identifie le fil
     * d'intention (à défaut d'UUID dédié, `ai_conversations.id` est la
     * référence stable du fil — voir décision dans la PR #8222) ; sans
     * conversation connue, on retombe sur le pending_action_id (unique par
     * proposition), ce qui couvre au minimum le retry réseau.
     */
    public function makeKey(string $companyId, ?int $conversationId, ?string $pendingActionId, string $tool, string $argumentsHash): string
    {
        $thread = $conversationId !== null
            ? 'conversation:'.$conversationId
            : 'pending:'.($pendingActionId ?? 'none');

        return hash('sha256', implode('|', [$companyId, $thread, $tool, $argumentsHash]));
    }

    /**
     * Résultat persisté pour une clé (non expiré), ou null.
     *
     * @return array{tool: string, result: array<string, mixed>, pending_action_id: string|null, conversation_id: int|null}|null
     */
    public function find(string $companyId, string $idempotencyKey): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * Résultat persisté pour un pending_action_id (retry réseau après
     * consommation du pending one-shot), ou null.
     *
     * @return array{tool: string, result: array<string, mixed>, pending_action_id: string|null, conversation_id: int|null}|null
     */
    public function findByPendingActionId(string $companyId, string $pendingActionId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('company_id', $companyId)
            ->where('pending_action_id', $pendingActionId)
            ->where('expires_at', '>', now())
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * Persiste le résultat d'une exécution réussie. Course éventuelle (deux
     * confirmations concurrentes de la même intention) : l'unique violation
     * est rattrapée — le premier écrivain gagne, le second RELIT le résultat
     * initial au lieu d'en écraser un autre.
     *
     * @param  array<string, mixed>  $result
     */
    public function store(
        string $companyId,
        string $idempotencyKey,
        string $tool,
        string $argumentsHash,
        ?string $pendingActionId,
        ?int $conversationId,
        array $result,
    ): void {
        try {
            DB::table(self::TABLE)->insert([
                'company_id' => $companyId,
                'tool' => $tool,
                'idempotency_key' => $idempotencyKey,
                'arguments_hash' => $argumentsHash,
                'pending_action_id' => $pendingActionId,
                'conversation_id' => $conversationId,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE) ?: '{}',
                'expires_at' => now()->addHours($this->ttlHours()),
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Perdu la course : l'entrée du premier écrivain fait foi.
        }
    }

    /**
     * Purge opportuniste des entrées expirées (appelée à faible fréquence
     * depuis le flux de confirmation — pas de scheduler dédié pour une
     * table bornée par TTL).
     */
    public function purgeExpired(): int
    {
        return DB::table(self::TABLE)->where('expires_at', '<=', now())->delete();
    }

    private function ttlHours(): int
    {
        $configured = config('ai.write_idempotency_ttl_hours', 24);

        return max(1, is_numeric($configured) ? (int) $configured : 24);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{tool: string, result: array<string, mixed>, pending_action_id: string|null, conversation_id: int|null}
     */
    private function hydrate(array $row): array
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $row['result'], true);

        return [
            'tool' => (string) $row['tool'],
            'result' => is_array($decoded) ? $decoded : [],
            'pending_action_id' => $row['pending_action_id'] !== null ? (string) $row['pending_action_id'] : null,
            'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item) && $this->isAssoc($item)) {
                $value[$key] = $this->canonicalize($item);
            } elseif (is_array($item)) {
                $value[$key] = array_map(
                    fn (mixed $entry): mixed => is_array($entry) && $this->isAssoc($entry) ? $this->canonicalize($entry) : $entry,
                    $item,
                );
            }
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }
}
