# Spec — Webhooks : idempotence persistée + résistance au rejeu (#5444)

## Problème
Le plan de stabilisation sécurité (`docs/security/STABILIZATION_PLAN.md`) exige l'idempotence persistée et la résistance au rejeu pour TOUS les webhooks. Le lot #5291 n'a durci que Stripe (signatures fail-closed). Webhooks présents : Stripe (Billing), PaymentWebhook (Billing), EmailBounce (Notification), MarketingLead (conversion).

## Solution
1. **Inventaire** : tableau webhook × (source, signature, effets de bord) dans `docs/security/WEBHOOKS.md`.
2. **Idempotence persistée** : migration tenant `webhook_events` (event_id unique par source, payload_hash, response_code, response_body, created_at) + trait `InteractsWithWebhookEvents` (ou service `WebhookEventRegistry`) :
   - `alreadyProcessed(source, eventId)` → retourner la réponse mémorisée (200 idempotent, zéro effet) ;
   - `markProcessed(...)` après succès.
   - Clé unique (source, event_id) — upsert atomique, fail-closed sur erreur DB.
3. **Durcissement par webhook** :
   - Stripe : signature HMAC fail-closed (déjà en place) + rejeu via `idempotency` de l'événement Stripe (id d'événement `evt_...`) ;
   - PaymentWebhook (Chargily) : vérifier le mécanisme de signature existant + idempotence sur l'id de l'événement ;
   - EmailBounce (SES) : vérifier la signature SNS (ou secret) + idempotence sur MessageId ;
   - MarketingLead : secret partagé existant + idempotence sur un id de lead (ou hash payload).
   - Payload size limit + timeouts (read timeout).
4. **Isolation tenant** : résoudre le tenant depuis le payload APRÈS vérification de signature ; jamais de lookup croisé via payload forgé non signé.
5. **Tests Feature par webhook** : signature valide ✅ (effet), invalide ❌ (400/401, AUCUN effet), malformée ❌, rejeu (même event_id → 200 sans effet double), payload altéré ❌, isolation tenant.
6. **DoD** : chaque webhook a un test rejeu ; signature invalide → 400/401 sans effet ; `WEBHOOKS.md` matrice.

## Notes anti-collision
- `api/app/Modules/Billing/**` + `Notification/**` : #5291 (PR ouverte) durcit Stripe — NE PAS refactorer ses fichiers, uniquement AJOUTER l'idempotence par-dessus.
- Coordination : si #5291 merge entre-temps, rebaser.
