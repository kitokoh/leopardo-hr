# Spec — Webhooks Paiement (Stripe + Paydunya)

## 1. Contexte et objectif

Leopardo RH doit recevoir des notifications en temps réel de la part des passerelles de paiement pour automatiser la gestion des abonnements et des factures. Deux passerelles sont supportées : **Stripe** (international) et **Paydunya** (Afrique de l'Ouest et Centrale : Sénégal, Côte d'Ivoire, Mali, Burkina Faso, Cameroun, Guinée).

Chaque webhook reçoit un événement signé par la passerelle, vérifie la signature pour prévenir les attaques de rejeu, puis traite l'événement de manière asynchrone via une file de jobs.

## 2. Endpoints

| Endpoint | Passerelle | Description |
|----------|-----------|-------------|
| `POST /webhooks/stripe` | Stripe | Réception des événements Stripe (Webhook API v2024) |
| `POST /webhooks/paydunya` | Paydunya | Réception des notifications Paydunya (IPN v2) |

Ces endpoints sont **publiques** (pas de middleware d'authentification employé), mais protégés par la **vérification de signature** de chaque passerelle.

## 3. Événements gérés

| Événement | Action déclenchée |
|-----------|-------------------|
| `payment.completed` | Marquer la facture associée comme **paid**, mettre à jour `paid_at`, prolonger `subscription_end` de l'entreprise selon la durée de l'abonnement |
| `payment.failed` | Notifier le Super Admin et l'administrateur de l'entreprise par email et notification in-app. Enregistrer la tentative dans `billing_transactions` |
| `subscription.cancelled` | Appliquer une **période de grâce** de 7 jours, puis passer le statut de l'entreprise à `suspended` si aucun nouveau paiement n'intervient |
| `subscription.renewed` | Prolonger `subscription_end` de l'entreprise et générer une nouvelle facture pour la période suivante |

## 4. Sécurité — Vérification des signatures

### Stripe
```php
$payload = $request->getContent();
$signature = $request->header('Stripe-Signature');
$expected = hash_hmac('sha256', $payload, config('services.stripe.webhook_secret'));
// Stripe-Signation header format: t=...,v1=...
// Utilisation du SDK officiel : \Stripe\Webhook::constructEvent()
```

### Paydunya (HMAC-SHA256)
```php
$payload = $request->getContent();
$hashHeader = $request->header('X-Paydunya-Signature'); // master_key hashed
$expectedHash = hash_hmac('sha256', $payload, config('services.paydunya.master_key'));
if (!hash_equals($expectedHash, $hashHeader)) {
    abort(403, 'Signature invalide');
}
```

## 5. Traitement asynchrone

Chaque webhook valide est dispatché vers un **job asynchrone** `WebhookProcessJob` pour éviter les timeouts HTTP. Le job est traité par la queue `webhooks` (connexion Redis dédiée).

**Stratégie de retry :** 3 tentatives maximum avec **backoff exponentiel** (60s, 300s, 900s). En cas d'échec final, l'événement est loggé dans `audit_logs` avec le statut `failed` pour investigation manuelle.

## 6. Mise à jour de `billing_transactions`

Pour chaque événement de paiement traité, la table `billing_transactions` est mise à jour :

```sql
UPDATE billing_transactions
SET gateway_ref = :gateway_ref,
    status      = :status        -- 'success' ou 'failed'
WHERE invoice_id = :invoice_id
  AND gateway    = :gateway;
```

- `gateway_ref` : identifiant de transaction unique retourné par la passerelle (ex: `pi_3Ns...` pour Stripe, `TRANSACTION_ID` pour Paydunya).
- `status` : mis à jour de `pending` à `success` ou `failed`.

## 7. Exemple de contrôleur

```php
<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\WebhookProcessJob;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly string $webhookSecret
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Vérification de la signature Stripe
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: payload invalide', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook: signature invalide', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Dispatch asynchrone
        WebhookProcessJob::dispatch('stripe', $event->type, $event->data->object->toArray());

        return response()->json(['received' => true]);
    }
}
```

```php
<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\WebhookProcessJob;
use Illuminate\Support\Facades\Log;

class PaydunyaWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $hashHeader = $request->header('X-Paydunya-Signature');
        $masterKey = config('services.paydunya.master_key');

        $expectedHash = hash_hmac('sha256', $payload, $masterKey);

        if (!hash_equals($expectedHash, $hashHeader)) {
            Log::warning('Paydunya webhook: signature invalide');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = $request->json()->all();
        WebhookProcessJob::dispatch('paydunya', $data['event'] ?? 'unknown', $data);

        return response()->json(['received' => true]);
    }
}
```
