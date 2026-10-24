<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\DTOs\RailResult;
use App\Modules\Paiements\Domain\DTOs\WebhookApplicationResult;
use App\Modules\Paiements\Domain\DTOs\WebhookPayload;
use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * BC-38 PAIEMENTS (#8742) — contrat d'application d'un webhook entrant.
 *
 * L'audit readiness a releve que le webhook **ne faisait progresser aucune
 * transaction** : il appelait le rail et renvoyait son statut, sans ecriture.
 * Aucun contrat n'existait pour un evenement hors sequence, en retard ou
 * duplique. Ce service porte ce contrat, en trois regles :
 *
 * 1. **Idempotence par la base.** L'unicite `(provider_instance_id, event_id)`
 *    est une contrainte, pas un `if` : deux livraisons concurrentes du meme
 *    evenement ne peuvent pas produire deux applications. Le perdant de la
 *    course rattrape la violation (23505) et retourne le resultat existant.
 *
 * 2. **Hors-sequence = journalise, jamais applique.** Un evenement qui
 *    contredirait un etat **terminal** (`succeeded`/`failed`/`cancelled`) est
 *    enregistre avec `outcome = out_of_sequence` et **n'est pas applique**.
 *    C'est le point le plus important : un `succeeded` recu apres un
 *    `cancelled` local ne doit pas ressusciter la transaction. La machine a
 *    etats (`PaymentTransactionStatus`) refuserait deja la transition, mais
 *    elle leverait — or un webhook ne doit pas repondre 500 a un fournisseur
 *    qui reessaiera indefiniment. On enregistre le refus et on acquitte.
 *
 * 3. **Aucune ecriture silencieuse.** Chaque evenement laisse une ligne, y
 *    compris ceux qui n'ont rien fait (`no_transaction`, `out_of_sequence`).
 *    Un webhook perdu se diagnostique donc en lisant la table.
 *
 * Le routage se fait par le **token** de l'endpoint (deja verifie par le
 * controleur) : ce service ne revalide pas la signature, il applique le
 * contrat une fois l'appelant authentifie.
 */
class WebhookEventProcessor
{
    /**
     * Applique un evenement de webhook et retourne son verdict.
     *
     * @param  array<string, mixed>  $body
     */
    public function apply(
        PaymentWebhookEndpoint $endpoint,
        WebhookPayload $payload,
        RailResult $result,
        array $body,
    ): WebhookApplicationResult {
        $instanceId = (int) $endpoint->provider_instance_id;
        $eventId = $this->resolveEventId($body);
        $payloadHash = PaymentWebhookEvent::fingerprint($body);

        // 1. Rejeu : l'evenement a deja ete traite. On retourne la ligne
        //    existante — c'est un no-op, pas une seconde application. Le
        //    drapeau `replayed` est ce qui permet au fournisseur de cesser de
        //    reessayer, meme si l'evenement d'origine avait ete refuse.
        $existing = PaymentWebhookEvent::query()
            ->forInstance($instanceId)
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return new WebhookApplicationResult($existing, true);
        }

        $transaction = $this->findTransaction($payload->externalReference);

        // 2. Aucune transaction : on journalise sans rien appliquer. Un
        //    evenement pour une reference inconnue n'est pas une erreur du
        //    fournisseur — il peut preceder la creation locale.
        if ($transaction === null) {
            return $this->record(
                endpoint: $endpoint,
                eventId: $eventId,
                payloadHash: $payloadHash,
                payload: $payload,
                outcome: PaymentWebhookEvent::OUTCOME_NO_TRANSACTION,
                requestedStatus: $result->status,
                appliedStatus: null,
                transactionId: null,
            );
        }

        // 3. Hors-sequence : la transaction est dans un etat terminal. On
        //    journalise le refus et on acquitte — jamais de 500, jamais de
        //    resurrection d'un etat terminal.
        if ($transaction->status->isTerminal()) {
            return $this->record(
                endpoint: $endpoint,
                eventId: $eventId,
                payloadHash: $payloadHash,
                payload: $payload,
                outcome: PaymentWebhookEvent::OUTCOME_OUT_OF_SEQUENCE,
                requestedStatus: $result->status,
                appliedStatus: $transaction->status,
                transactionId: (int) $transaction->id,
            );
        }

        // 4. Application : la transition passe par la machine a etats du
        //    modele, jamais par une ecriture directe de `status`.
        $applied = $result->status;

        if (! $transaction->status->canTransitionTo($applied)) {
            // Transition non listee (ex. `processing` -> `pending`) : meme
            // traitement que le hors-sequence — journalise, non applique.
            return $this->record(
                endpoint: $endpoint,
                eventId: $eventId,
                payloadHash: $payloadHash,
                payload: $payload,
                outcome: PaymentWebhookEvent::OUTCOME_OUT_OF_SEQUENCE,
                requestedStatus: $applied,
                appliedStatus: $transaction->status,
                transactionId: (int) $transaction->id,
            );
        }

        $transaction->transitionTo($applied);

        return $this->record(
            endpoint: $endpoint,
            eventId: $eventId,
            payloadHash: $payloadHash,
            payload: $payload,
            outcome: PaymentWebhookEvent::OUTCOME_PROCESSED,
            requestedStatus: $applied,
            appliedStatus: $applied,
            transactionId: (int) $transaction->id,
        );
    }

    /**
     * Identifiant d'evenement : celui du fournisseur s'il en fournit un, sinon
     * l'empreinte deterministe du corps.
     *
     * Le repli par empreinte est ce qui rend l'idempotence utilisable avec un
     * fournisseur qui n'envoie pas d'identifiant : deux envois du meme corps
     * sont reconnus comme un rejeu.
     *
     * @param  array<string, mixed>  $body
     */
    private function resolveEventId(array $body): string
    {
        foreach (['event_id', 'id', 'eventId', 'notification_id'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return substr($value, 0, 190);
            }
        }

        return PaymentWebhookEvent::fingerprint($body);
    }

    /**
     * Retrouve la transaction visee par la reference externe.
     *
     * Lecture **cross-tenant assumee** : un webhook arrive hors contexte tenant
     * (l'appelant est un fournisseur, pas un utilisateur), donc le scope
     * fail-closed rendrait toute transaction introuvable. On passe par le
     * wrapper documente `crossTenantForSystemTask` (#7960) — jamais par un
     * `withoutGlobalScope` brut, qui serait refuse par la garde CI.
     */
    private function findTransaction(string $externalReference): ?PaymentTransaction
    {
        if ($externalReference === '') {
            return null;
        }

        return PaymentTransaction::query()
            ->crossTenantForSystemTask('webhook entrant BC-38 : routage par reference externe, hors contexte tenant (#8742)')
            ->where('external_reference', $externalReference)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Enregistre l'evenement. La contrainte d'unicite est la garantie reelle :
     * si une livraison concurrente a gagne la course, on retourne SA ligne.
     *
     * @param  array<string, mixed>  $payload
     */
    private function record(
        PaymentWebhookEndpoint $endpoint,
        string $eventId,
        string $payloadHash,
        WebhookPayload $payload,
        string $outcome,
        ?PaymentTransactionStatus $requestedStatus,
        ?PaymentTransactionStatus $appliedStatus,
        ?int $transactionId,
    ): WebhookApplicationResult {
        try {
            $event = DB::transaction(function () use (
                $endpoint,
                $eventId,
                $payloadHash,
                $payload,
                $outcome,
                $requestedStatus,
                $appliedStatus,
                $transactionId,
            ): PaymentWebhookEvent {
                return PaymentWebhookEvent::query()->create([
                    'company_id' => $endpoint->company_id,
                    'provider_instance_id' => (int) $endpoint->provider_instance_id,
                    'webhook_endpoint_id' => (int) $endpoint->id,
                    'event_id' => $eventId,
                    'external_reference' => $payload->externalReference !== '' ? $payload->externalReference : null,
                    'event_type' => $payload->eventType,
                    'outcome' => $outcome,
                    'requested_status' => $requestedStatus?->value,
                    'applied_status' => $appliedStatus?->value,
                    'transaction_id' => $transactionId,
                    'payload_hash' => $payloadHash,
                    'received_at' => now(),
                ]);
            });

            return new WebhookApplicationResult($event, false);
        } catch (QueryException $e) {
            // 23505 = violation d'unicite : une livraison concurrente du meme
            // evenement a gagne. On retourne SA ligne — le resultat est le
            // meme, et l'appelant ne doit pas voir d'erreur.
            if ($this->isUniqueViolation($e)) {
                $winner = PaymentWebhookEvent::query()
                    ->forInstance((int) $endpoint->provider_instance_id)
                    ->where('event_id', $eventId)
                    ->first();

                if ($winner !== null) {
                    return new WebhookApplicationResult($winner, true);
                }
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505'
            || str_contains($e->getMessage(), 'payment_webhook_events_instance_event_unique');
    }
}
