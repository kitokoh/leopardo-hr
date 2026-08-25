# Webhooks entrants — matrice de garanties

> Issue #5444 — idempotence persistée + résistance au rejeu + fail-closed.
> Complète `docs/security/STABILIZATION_PLAN.md` (§ webhooks) et le lot #5291
> (signatures Stripe). Registre d'idempotence : table publique
> `webhook_events` (unique `source + event_id`), service
> `WebhookEventRegistry` (Platform module).

## Matrice

| Webhook | Route | Source | Authentification | Clé d'idempotence | Effets de bord |
|---|---|---|---|---|---|
| Stripe | `POST /api/v1/webhooks/stripe` | Stripe | Signature HMAC `Stripe-Signature` (fail-closed 400, #5291) | `event.id` (`evt_…`), sinon `sha256(payload)` | Statut invoice/subscription, création `payments`, commission |
| Chargily | `POST /api/v1/webhooks/chargily` | Chargily | Signature HMAC `X-Chargily-Signature` (fail-closed 400) | `data.id` (événement), sinon `data.data.id` (checkout), sinon `sha256(payload)` | `invoice.status = paid`, création `payments` |
| Email bounce | `POST /api/v1/webhooks/email-bounce` | Postmark/SES/Mailgun | Secret partagé `X-Bounce-Webhook-Secret` (fail-closed 400/503, #2616) | `sha256(payload)` (pas d'id fournisseur) | Stamping `email_bounced_at`, ligne `communication_events` |
| Marketing lead | `POST /api/v1/marketing/leads` | Vitrine Next.js (server-to-server) | Secret partagé Bearer/`X-Marketing-Lead-Token` (fail-closed 400/503, #3888) | `sha256(payload)` (pas d'id fournisseur) | Création `marketing_leads` (dédupliquée aussi par `external_id`) |

## Garanties (toutes webhooks)

1. **Fail-closed** : payload malformé / signature invalide / secret manquant →
   `400` (ou `503` si secret non configuré) **sans aucun effet de bord**.
   Aucun traitement avant vérification d'authenticité.
2. **Idempotence persistée** : le premier traitement réserve
   `(source, event_id)` avec `response_code = 0` (verrou atomique via la
   contrainte unique). `WebhookEventRegistry::begin()` :
   - réservation gagnée → traitement ;
   - `response_code > 0` → **rejeu** : réponse mémorisée rejouée telle quelle
     (200/201), **zéro effet double** ;
   - `response_code == 0` → livraison concurrente en cours → `202` sans effet.
3. **Résistance au rejeu** : même payload rejoué → 200/201 idempotent.
   Pour les sources sans identifiant d'événement, la clé est le hash du
   payload brut : une redelivrance *identique* est dédupliquée ; un événement
   *distinct* (payload différent) passe.
4. **Échec de traitement** : `WebhookEventRegistry::release()` supprime la
   réservation → la redelivrance du fournisseur re-traite l'événement
   (sémantique 500 « Stripe doit réessayer », #2668). Le 500 n'est jamais
   mémorisé comme réponse finale.
5. **Isolation tenant** : le tenant est résolu depuis le payload APRÈS
   vérification de signature ; un payload forgé non signé n'atteint jamais le
   code de résolution (fail-closed).
6. **Limites** : `throttle:webhooks-inbound` sur toutes les routes ; taille
   de payload bornée par le serveur.

## Contrôleurs câblés (#5444)

- `api/app/Modules/Billing/Interfaces/Api/V1/StripeWebhookController.php`
- `api/app/Modules/Billing/Interfaces/Api/V1/PaymentWebhookController.php` (Chargily)
- `api/app/Modules/Notification/Interfaces/Api/V1/Controllers/EmailBounceWebhookController.php`
- `api/app/Modules/Marketing/Interfaces/Api/V1/Controllers/MarketingLeadController.php`

## Tests

`api/tests/Feature/Security/WebhookIdempotenceTest.php` : rejeu sans effet
double ×4 webhooks (comptages effectifs : `payments`, `communication_events`,
`marketing_leads`), clé hash sans id fournisseur, signature invalide → 400
sans effet ni réservation, assertions `webhook_events`.

## Évolutions futures

- Champ `idempotency-key` côté client (vitrine) pour le marketing lead (au
  lieu du hash) quand le payload gagnera un identifiant stable.
- Purge/archivage des lignes `webhook_events` (rétention, cf. #5439 audit
  global) — volume faible (une ligne par événement), index unique
  `(source, event_id)`.
