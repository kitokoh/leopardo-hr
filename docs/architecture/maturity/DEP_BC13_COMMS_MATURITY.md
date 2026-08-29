# DEP-BC13 — Rapport de maturité BC-13 COMMS

> **Issue :** [DEP-BC13 #5889](https://github.com/kitokoh/leopardo-hr/issues/5889)
> **Contexte :** BC-13 — Notifications & Communications (email, SMS, WhatsApp, préférences, templates, inbox/outbox, délivrabilité)
> **Date :** 2026-08-28
> **Statut :** **Rapport phase 1** — corrections en PRs courtes de suivi.

## 1. Cartographie

| Composant | Emplacement | Volume |
|---|---|---|
| Module Notification (DDD) | `api/app/Modules/Notification` | 39 fichiers PHP |
| Contrats providers | `api/app/Contracts/Communication` | `MessageProviderInterface`, `RetryableMessageProviderInterface` |
| Préférences utilisateur | table `notification_preferences` (tenant) + `CommunicationService` | orchestrateur unique |
| Événements d'audit | `communication_events` | traçabilité multi-canal |
| Heures calmes / quotas | `CommunicationService` (heures calmes canaux externes, quotas mensuels SMS/WhatsApp, 0 = illimité) | appliqués |
| Analytics | `GET /api/v1/communication/analytics` (manager principal/rh) | observabilité |
| Tests | `api/tests/Feature/*Notification*` | 11 fichiers |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Domaine | ✅ | Vocabulaire Communication/Notification documenté (registre BC, conventions v4.16.122+) |
| Données | ✅ | `notification_preferences` tenant-scoped, backfill readiness v4.16.245 |
| Tenant | ✅ | Préférences/événements tenant-scoped, canal par adaptateur sans PII |
| API | ✅ | `/notification-preferences`, `/notifications`, `/communication/analytics` versionnés + OpenAPI |
| Autorisation | ✅ | `api.manager`/policies ; analytics réservé principal/rh |
| Transactions | 🟡 | Publication outbox à généraliser pour les événements communication |
| Asynchronisme | ✅ | Jobs notification (SendPushNotificationJob…), idempotence gardée (backend-jobs-ci) |
| Sécurité | ✅ | Threat model WhatsApp (MAT-017), secrets provider hors logs, préférences/consentement |
| Frontend | ✅ | Écrans Compte (préférences) employee/manager, notifications actionnables |
| Performance | 🟡 | Budgets à poser sur `/notifications` et analytics |
| Exploitation | ✅ | Runbook plateforme + registre MAT-015 ; dead-letter webhooks |
| Produit | ✅ | CommunicationService orchestrateur unique, canaux SMS/WhatsApp audit-only |

**Bilan : 9/12 présents, 3 partiels (transactions, performance, exploitation fine).**

## 3. Risques

1. Publication des événements `communication_events` non transactionnelle (outbox) — risque de perte si crash entre effet et événement (MAT-008).
2. Pas de budget de performance sur la liste `/notifications` (volumétrie).
3. Providers SMS/WhatsApp en audit-only : activation production nécessite signatures webhook + quotas (déjà contraints).

## 4. Plan de corrections

| Priorité | Correction | Suivi |
|---|---|---|
| P1 | Outbox pour `communication_events` (publication après commit) | dépend MAT-008 |
| P2 | Budget p95 sur `/notifications` (MAT-014) | DEP-BC13-followup-1 |
| P2 | Drill de délivrabilité (bounce/quota) tracé | DEP-BC13-followup-2 |

## 5. Preuves

- Gardes verts : threat models (MAT-017), runbooks (MAT-015), budgets (MAT-014), golden journeys (GJ-02 inclut notification).
- Tests : 11 fichiers Feature Notification + `backend-jobs-ci.yml` (jobs critiques).
