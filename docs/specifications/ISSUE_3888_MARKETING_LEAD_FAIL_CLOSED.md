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

## Mise à jour #7301 (2026-09-14) — le prérequis de déploiement n'a pas été tenu

Constat production (#7301) : `MARKETING_LEAD_WEBHOOK_TOKEN` **absent** de
l'environnement API → l'endpoint répondait `503 MARKETING_WEBHOOK_NOT_CONFIGURED`
pour **chaque** lead, et `marketing_leads` restait vide. Les deux forwarders
externes de la vitrine (`MARKETING_CRM_WEBHOOK_URL`,
`MARKETING_EMAIL_WEBHOOK_URL`) étant vides par défaut, **aucun lead n'était
persisté nulle part** (data-loss BC-11 CRM) pendant que le funnel affichait un
succès.

La décision fail-closed ci-dessus est donc **assouplie** pour ce seul cas :

- **secret configuré** : vérification inchangée (#3888) — secret invalide ou
  absent → `400 Invalid signature`, aucune écriture ;
- **secret absent** : le lead est **persisté** (`201`) et une **alerte** est
  émise (`marketing.lead.ingest_unauthenticated` : log `critical` + relais
  best-effort `MARKETING_ALERT_WEBHOOK_URL`). Le risque d'injection reste borné
  par `throttle:webhooks-inbound` ; il est préféré au risque certain de perdre
  les leads d'acquisition.

Tests associés (`api/tests/Feature/Marketing/MarketingLeadControllerTest.php`) :
`test_it_persists_the_lead_when_the_shared_secret_is_not_configured`,
`test_it_alerts_when_the_shared_secret_is_not_configured`,
`test_it_relays_the_alert_to_the_configured_webhook`,
`test_it_does_not_alert_when_the_secret_is_configured` et le refus d'un secret
invalide (`400`, aucune écriture).
