<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

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
        // #5576 : le garde doit voir la table PLATEFORME (schéma public) même
        // quand la session pointe sur un autre schéma (search_path tenant) —
        // `Schema::hasTable()` suit `current_schema()` et ratait la table.
        if (! $this->webhookEventsTableExists()) {
            return null;
        }

        try {
            WebhookEvent::query()->create([
                'source' => $source,
                'event_id' => $eventId,
                'payload_hash' => $payloadHash,
                'response_code' => 0,
            ]);

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
     * La table `webhook_events` existe-t-elle (schéma plateforme `public`) ?
     *
     * Postgres : `Schema::hasTable()` interroge `current_schema()` (premier
     * schéma du search_path de session, ex. `shared_tenants` en test) et
     * ratait la table publique — on vérifie donc explicitement
     * `public.webhook_events` (avec repli sur le search_path).
     */
    private function webhookEventsTableExists(): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            $qualified = DB::selectOne("select to_regclass('public.webhook_events') as table_name");

            if ($qualified !== null && $qualified->table_name !== null) {
                return true;
            }
        }

        return Schema::hasTable('webhook_events');
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
