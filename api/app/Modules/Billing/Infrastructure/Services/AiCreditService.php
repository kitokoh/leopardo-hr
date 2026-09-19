<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Modules\Billing\Domain\Exceptions\InsufficientAiCreditsException;
use App\Modules\Billing\Domain\Models\AiCreditLedger;
use App\Modules\Billing\Domain\Models\AiUsageCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Crédits IA achetables (#7764, spec MISSION_ESPACE_CLIENT §3.4).
 *
 * Source de vérité : le grand livre `ai_credit_ledger` (mouvements signés en
 * tokens) — le solde est la somme des `delta`. Les packs sont des constantes
 * VERSIONNÉES (`PACKS_VERSION`) : tout changement de contenu/prix incrémente
 * la version (jamais de mutation silencieuse d'un pack déjà vendu).
 *
 * Sémantique :
 *   - `credit()`  : idempotent par `reference` (id session Stripe / clé
 *     sandbox) — l'index unique partiel « purchase » + le pré-check rendent
 *     le rejeu de webhook sans effet double ;
 *   - `debit()`   : FAIL-CLOSED — solde insuffisant (ou table absente) →
 *     `InsufficientAiCreditsException` (422 AI_CREDITS_EXHAUSTED), aucun
 *     mouvement persisté (rollback) ;
 *   - compteur mensuel : `ai_usage_counters` (requêtes/mois, l'unité de
 *     `config('ai.quotas')`) — incrément atomique `ON CONFLICT` en PostgreSQL.
 */
class AiCreditService
{
    /**
     * Version des packs — à incrémenter à CHAQUE changement de contenu/prix.
     */
    public const PACKS_VERSION = '2026-09-19.1';

    /**
     * Packs de tokens achetables (prix en centimes d'euro, TTC simple v1).
     *
     * Ordres de grandeur : avec `ai.max_tokens` ≈ 1 024 et le débit forfaitaire
     * `TOKENS_PER_REQUEST`, le pack S couvre ~100 requêtes IA hors quota.
     */
    public const PACKS = [
        's' => ['tokens' => 100_000, 'price_eur_cents' => 900],
        'm' => ['tokens' => 500_000, 'price_eur_cents' => 3_900],
        'l' => ['tokens' => 2_000_000, 'price_eur_cents' => 12_900],
    ];

    /**
     * Débit forfaitaire (tokens) par requête IA au-delà du quota du plan.
     *
     * L'AIRateLimiter s'exécute AVANT l'appel LLM : le coût réel n'est pas
     * encore connu. On débite donc un forfait aligné sur le budget par
     * requête (`ai.max_tokens` ≈ 1 024) — simple, prévisible et documenté.
     */
    public const TOKENS_PER_REQUEST = 1_000;

    /**
     * Solde de crédits (tokens) d'une entreprise = somme du ledger.
     */
    public function balance(string $companyId): int
    {
        if (! schemaTableExists('ai_credit_ledger')) {
            return 0;
        }

        return (int) AiCreditLedger::query()
            ->where('company_id', $companyId)
            ->sum('delta');
    }

    /**
     * Crédite un achat de tokens — IDEMPOTENT par référence.
     *
     * Retourne `true` si le crédit a été appliqué, `false` si la référence
     * avait déjà été créditée (rejeu de webhook / double clic sandbox).
     */
    public function credit(string $companyId, int $tokens, string $reference, ?int $createdBy = null): bool
    {
        if ($tokens <= 0) {
            throw new \InvalidArgumentException('AI credit purchase must be a positive token amount.');
        }

        $alreadyCredited = AiCreditLedger::query()
            ->withoutGlobalScopes()
            ->where('reason', AiCreditLedger::REASON_PURCHASE)
            ->where('reference', $reference)
            ->exists();

        if ($alreadyCredited) {
            Log::info('AiCredit: achat déjà crédité — rejeu ignoré (idempotence)', [
                'company_id' => $companyId,
                'reference' => $reference,
            ]);

            return false;
        }

        try {
            // INSERT isolé dans une sous-transaction (savepoint) : en cas de
            // course sur la référence, la violation d'unicité n'aborte pas la
            // transaction englobante (SQLSTATE 25P02 — même pattern que
            // WebhookEventRegistry #5629).
            DB::transaction(function () use ($companyId, $tokens, $reference, $createdBy): void {
                AiCreditLedger::query()->create([
                    'company_id' => $companyId,
                    'delta' => $tokens,
                    'reason' => AiCreditLedger::REASON_PURCHASE,
                    'reference' => $reference,
                    'created_by' => $createdBy,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            Log::info('AiCredit: achat concurrent déjà crédité — rejeu ignoré (index unique)', [
                'company_id' => $companyId,
                'reference' => $reference,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Débite des tokens — FAIL-CLOSED si le solde est insuffisant.
     *
     * Le mouvement de consommation est inséré PUIS le solde recalculé dans la
     * même transaction : s'il devient négatif, tout est annulé et l'exception
     * 422 `AI_CREDITS_EXHAUSTED` remonte. Deux débits strictement concurrents
     * peuvent, au pire, laisser passer une requête de trop (READ COMMITTED) —
     * le solde devient alors négatif et la requête SUIVANTE est bloquée :
     * l'exposition est bornée à un forfait, jamais un trou ouvert.
     */
    public function debit(string $companyId, int $tokens, string $reason = AiCreditLedger::REASON_CONSUMPTION): void
    {
        if ($tokens <= 0) {
            throw new \InvalidArgumentException('AI credit debit must be a positive token amount.');
        }

        if (! schemaTableExists('ai_credit_ledger')) {
            // Fail-closed : sans ledger, aucun crédit n'existe — on ne laisse
            // jamais passer une consommation non comptabilisée.
            throw new InsufficientAiCreditsException('AI credit ledger unavailable — debit refused (fail-closed).');
        }

        DB::transaction(function () use ($companyId, $tokens, $reason): void {
            AiCreditLedger::query()->create([
                'company_id' => $companyId,
                'delta' => -$tokens,
                'reason' => $reason,
                'reference' => null,
                'created_by' => null,
            ]);

            $balance = (int) AiCreditLedger::query()
                ->where('company_id', $companyId)
                ->sum('delta');

            if ($balance < 0) {
                throw new InsufficientAiCreditsException(
                    "Insufficient AI credits for company {$companyId} (requested {$tokens} tokens)."
                );
            }
        });
    }

    /**
     * Incrémente le compteur mensuel PERSISTANT et retourne l'usage du mois
     * (requêtes) APRÈS incrément. Atomique en PostgreSQL (`ON CONFLICT`).
     */
    public function incrementMonthlyUsage(string $companyId, string $period): int
    {
        if (DB::getDriverName() === 'pgsql') {
            $row = DB::selectOne(
                'INSERT INTO ai_usage_counters (company_id, period, used, created_at, updated_at) '
                .'VALUES (?, ?, 1, NOW(), NOW()) '
                .'ON CONFLICT (company_id, period) '
                .'DO UPDATE SET used = ai_usage_counters.used + 1, updated_at = NOW() '
                .'RETURNING used',
                [$companyId, $period]
            );

            return $row !== null ? (int) $row->used : 1;
        }

        // Chemin non-PostgreSQL (jamais en CI/prod — conventions §2.6) :
        // read-modify-write best-effort.
        $counter = AiUsageCounter::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->first();

        if ($counter === null) {
            AiUsageCounter::query()->create([
                'company_id' => $companyId,
                'period' => $period,
                'used' => 1,
            ]);

            return 1;
        }

        $counter->increment('used');

        return (int) $counter->used;
    }

    /**
     * Usage IA du mois (requêtes) — lecture seule.
     */
    public function monthlyUsage(string $companyId, string $period): int
    {
        if (! schemaTableExists('ai_usage_counters')) {
            return 0;
        }

        /** @var AiUsageCounter|null $counter */
        $counter = AiUsageCounter::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->first();

        return $counter !== null ? (int) $counter->used : 0;
    }

    /**
     * Définition d'un pack achetable, ou `null` si le code est inconnu.
     *
     * @return array{tokens: int, price_eur_cents: int}|null
     */
    public function pack(string $code): ?array
    {
        return self::PACKS[$code] ?? null;
    }

    /**
     * Packs achetables — shape stable pour l'API et le front.
     *
     * @return list<array{code: string, tokens: int, price_eur_cents: int}>
     */
    public function packs(): array
    {
        $packs = [];
        foreach (self::PACKS as $code => $pack) {
            $packs[] = [
                'code' => $code,
                'tokens' => $pack['tokens'],
                'price_eur_cents' => $pack['price_eur_cents'],
            ];
        }

        return $packs;
    }
}
