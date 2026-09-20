<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Contracts;

use App\Modules\Retail\Domain\Enums\RetailPaymentIntentStatus;
use App\Modules\Retail\Domain\Models\RetailOnlinePaymentIntent;
use App\Modules\Retail\Domain\Payments\RetailPaymentIntentResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentRefundResult;
use App\Modules\Retail\Domain\Payments\RetailPaymentWebhookEvent;

/**
 * Contrat de provider de paiement en ligne du module Retail
 * (BC-17 RETAIL, #7812 — pattern PaymentGatewayInterface Accounting #5272
 * et RestaurantManager RESTO-406).
 *
 * Abstraction VOLONTAIREMENT locale au module : la selection se fait par
 * `config('retail.payments.provider')` + credentials env (aucun secret en
 * dur). Le chantier BC-21 « profils de paiement tenant » (PR #7732, non
 * merge) fournira a terme la resolution des credentials PAR TENANT — ce
 * contrat est concu pour brancher cette resolution sans changer les
 * appelants (RetailPaymentService).
 *
 * Regles :
 * - montants en minor units UNIQUEMENT ;
 * - `verifyWebhookSignature` est FAIL-CLOSED : secret absent = rejet ;
 * - erreurs normalisees via RetailPaymentProviderException.
 */
interface RetailPaymentProviderInterface
{
    /**
     * Code court du provider (`chargily`, `mock`) — utilise dans l'URL de
     * webhook et la colonne `provider` des intents.
     */
    public function providerCode(): string;

    /**
     * Cree la session de paiement chez le PSP pour l'intent donne
     * (reference locale transmise en metadata pour le rapprochement
     * webhook/reconciliation).
     *
     * @throws \App\Modules\Retail\Domain\Exceptions\RetailPaymentProviderException
     */
    public function createIntent(RetailOnlinePaymentIntent $intent): RetailPaymentIntentResult;

    /**
     * Verifie la signature du webhook (HMAC sur le corps BRUT) —
     * fail-closed : secret non configure ou signature invalide → false.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool;

    /**
     * Normalise le payload webhook (deja verifie) en evenement interne —
     * null si le payload est illisible.
     */
    public function parseWebhookEvent(string $payload): ?RetailPaymentWebhookEvent;

    /**
     * Re-interroge le provider sur l'etat reel d'un intent (reconciliation
     * `retail:payments:reconcile`) — null si l'etat est inconnu/inchange.
     */
    public function verifyIntent(RetailOnlinePaymentIntent $intent): ?RetailPaymentIntentStatus;

    /**
     * Demande le remboursement TOTAL d'un intent `succeeded`.
     *
     * @throws \App\Modules\Retail\Domain\Exceptions\RetailPaymentProviderException
     */
    public function refund(RetailOnlinePaymentIntent $intent): RetailPaymentRefundResult;
}
