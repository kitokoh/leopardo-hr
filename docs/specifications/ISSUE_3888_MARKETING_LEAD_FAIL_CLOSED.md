# ISSUE-3888 — MarketingLeadController fail-closed

> Spec Kit — décision d'implémentation (audit 360° expert14, 2026-08-15).

## Contexte

`POST /api/v1/marketing/leads` est un endpoint **public et non authentifié** appelé
server-to-server par la vitrine Next.js (`front/web/src/app/api/forms/*`) pour
persister durablement les leads signup/demo/contact/newsletter.

État historique : #2688 (T027, P3) a introduit un fail-open **documenté** —
secret absent → `Log::warning` et ingestion acceptée. Livré par #2854.

## Décision

Passer en **fail-closed** (#3888) : secret non configuré → `503`
`Marketing lead webhook not configured.`, aucune écriture en base.

- Miroir d'`EmailBounceWebhookController` (#2616) et des webhooks Stripe/Chargily.
- Justification : un endpoint public non authentifiable est une surface d'attaque
  triviale (injection de fausses leads dans le pipeline CRM, volume illimité).

## Impact & prérequis de déploiement

- La vitrine envoie déjà `Authorization: Bearer ${MARKETING_LEAD_WEBHOOK_TOKEN}`
  (`front/web/src/app/api/forms/_lib/lead-capture.ts:buildForwardHeaders`).
- **Prérequis prod** : `MARKETING_LEAD_WEBHOOK_TOKEN` doit être renseigné dans
  l'environnement Render avant/avec ce déploiement, sinon les formulaires
  vitrine ne persisteront plus (les forwarders CRM/email, eux, sont indépendants).

## Tests

- `test_it_is_fail_closed_when_secret_is_not_configured` : 503 + `assertDatabaseMissing`.
- Tests existants : secret posé en `setUp`, header Bearer sur les cas positifs.
