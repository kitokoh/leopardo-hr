<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * #5444 — Registre d'idempotence persistée des webhooks entrants.
 *
 * Protocole (verrou atomique via la contrainte unique `(source, event_id)`) :
 *
 * 1. `begin()` réserve l'événement (`response_code = 0`). Si la réservation
 *    réussit, le traitement peut avoir lieu. Si un événement du même
 *    `(source, event_id)` existe déjà :
 *    - `response_code > 0` → rejeu : on renvoie la réponse mémorisée,
 *      AUCUN traitement (zéro effet double — paiement, relance, mail, lead) ;
 *    - `response_code == 0` → événement en cours de traitement par une
 *      livraison concurrente : on répond 202 sans traiter.
 * 2. `complete()` mémorise la réponse finale (code + corps) après succès.
 * 3. `release()` supprime la réservation après un échec de traitement : la
 *    redelivrance du fournisseur re-réserve et re-traite (sémantique 500
 *    « Stripe doit réessayer », issue #2668).
 *
 * Sources sans identifiant d'événement (email-bounce, marketing-lead) :
 * `event_id = sha256(payload)` — une redelivrance identique est dédupliquée ;
 * un événement distinct (payload différent) passe.
 */
final class WebhookEventRegistry
{
    /**
     * Réserve un événement. Retourne `null` si le traitement doit avoir lieu,
     * ou `['code' => int, 'body' => string|null]` si l'événement est un rejeu.
     *
     * @return array{code: int, body: string|null}|null
     */
    public function begin(string $source, string $eventId, string $payloadHash): ?array
    {
        // Garde de schéma partiel (pattern `ensurePunchPhotoProvided` #5265) :
        // en environnement de test sans la table (CreatesMvpSchema), on
        // traite sans déduplication — en production la migration existe.
        if (! Schema::hasTable('public.webhook_events')) {
            return null;
        }

        try {
            // #5629 : la violation d'unicité ABORTE la transaction courante en
            // PostgreSQL (SQLSTATE 25P02 sur toute redelivrance). L'INSERT est
            // isolé dans une sous-transaction (savepoint en test — les suites
            // RefreshTenantDatabase vivent dans une transaction globale ;
            // transaction réelle en prod) : sur conflit, seul le savepoint est
            // annulé et la relecture ci-dessous reste exécutable.
            DB::transaction(function () use ($source, $eventId, $payloadHash): void {
                WebhookEvent::query()->create([
                    'source' => $source,
                    'event_id' => $eventId,
                    'payload_hash' => $payloadHash,
                    'response_code' => 0,
                ]);
            });

            return null;
        } catch (UniqueConstraintViolationException $e) {
            /** @var WebhookEvent|null $existing */
            $existing = WebhookEvent::query()
                ->where('source', $source)
                ->where('event_id', $eventId)
                ->first();

            if ($existing !== null && $existing->response_code > 0) {
                return [
                    'code' => (int) $existing->response_code,
                    'body' => $existing->response_body,
                ];
            }

            // En cours de traitement (livraison concurrente) : 202, pas d'effet.
            return ['code' => 202, 'body' => null];
        }
    }

    /**
     * Mémorise la réponse finale d'un événement traité avec succès.
     */
    public function complete(string $source, string $eventId, int $code, ?string $body = null): void
    {
        // Même garde de schéma partiel que begin() : sans la table (état
        // d'infra de test après migrate:fresh), complete() est un no-op —
        // jamais de SQLSTATE 42P01/25P02 sur les webhooks.
        if (! Schema::hasTable('public.webhook_events')) {
            return;
        }

        WebhookEvent::query()
            ->where('source', $source)
            ->where('event_id', $eventId)
            ->update([
                'response_code' => $code,
                'response_body' => $body,
                'updated_at' => now(),
            ]);
    }

    /**
     * Libère la réservation après un échec de traitement : la prochaine
     * redelivrance du fournisseur pourra re-traiter l'événement.
     */
    public function release(string $source, string $eventId): void
    {
        // Même garde de schéma partiel que begin() : no-op sans la table.
        if (! Schema::hasTable('public.webhook_events')) {
            return;
        }

        WebhookEvent::query()
            ->where('source', $source)
            ->where('event_id', $eventId)
            ->delete();
    }

    /**
     * Identifiant d'événement stable : `$providerId` si présent, sinon le
     * hash du payload brut (sources sans id, ex. email-bounce / marketing-lead).
     */
    public function eventId(string $payload, ?string $providerId = null): string
    {
        if (is_string($providerId) && $providerId !== '') {
            return $providerId;
        }

        return hash('sha256', $payload);
    }

    /**
     * Réponse à rejouer (corps JSON) — utilisé par les contrôleurs webhook.
     *
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public function replayBody(?string $storedBody, array $fallback): array
    {
        if ($storedBody === null || $storedBody === '') {
            return $fallback;
        }

        $decoded = json_decode($storedBody, true);

        return is_array($decoded) ? $decoded : $fallback;
    }

    /**
     * Journalise un rejeu détecté (observabilité, sans bruit excessif).
     */
    public function logReplay(string $source, string $eventId, int $code): void
    {
        Log::info('Webhook rejoué (idempotence)', [
            'source' => $source,
            'event_id' => $eventId,
            'replayed_code' => $code,
        ]);
    }
}
