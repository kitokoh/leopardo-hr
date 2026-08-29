# DEP-BC14 — Rapport de maturité BC-14 INTEGRATION

> **Issue :** [DEP-BC14 #5890](https://github.com/kitokoh/leopardo-hr/issues/5890)
> **Contexte :** BC-14 — Integration Runtime (webhooks, queues, inbox durable, outbox transactionnelle, adaptateurs, replay)
> **Date :** 2026-08-28
> **Statut :** **Rapport phase 1** — corrections en PRs courtes de suivi.

## 1. Cartographie

| Composant | Emplacement | Volume |
|---|---|---|
| Jobs métier | `api/app/Jobs` | 14 fichiers PHP |
| Contrat queue tenant | `api/app/Contracts/Queue/TenantScopedJob` | middleware `EnsureTenantContext` |
| EdgeSync | `api/app/Modules/EdgeSync` | sync edge nodes |
| Webhooks | tables tenant `webhook_tables`/`webhook_deliveries` (dead-letter) + `WebhookEventRegistry` | entrée/sortie tracées |
| Outbox CRM | `crm_outbox_events` (unique `(company_id, idempotency_key)`) + `CrmOutboxPublisher`/`CrmOutboxConsumerRegistry` | pattern de référence |
| Files | `QUEUE_CONNECTION=database` (garde #5578) | fail-safe |
| Tests | `api/tests/Feature/*Queue*` + `*Webhook*` | 18 fichiers |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Domaine | ✅ | Webhooks/queues/outbox documentés (runbook Files CRM, ADR) |
| Données | ✅ | Tables tenant-scoped, idempotence par clé, DLQ |
| Tenant | ✅ | `TenantScopedJob` + `EnsureTenantContext` + garde queue-strategy |
| API | ✅ | `/webhooks`, `/edge-nodes`, `/device-tokens` versionnés + OpenAPI |
| Autorisation | ✅ | Webhook endpoints Policies (test/replay = principal), 404 cross-tenant |
| Transactions | ✅ | Outbox CRM publiée APRÈS commit (contrat #5741) |
| Asynchronisme | ✅ | Retry/backoff, DLQ, replay contrôlé, idempotence testée (CrmOutboxTest 8) |
| Sécurité | ✅ | Signatures provider fail-closed, secrets hors logs (MAT-017) |
| Frontend | — | Pas d'UI dédiée (runtime) — N/A |
| Performance | 🟡 | Lag borné testé sur pic 200 événements ; budgets globaux à poser |
| Exploitation | ✅ | `RUNBOOK_FILES_CRM.md` (pause, replay, DLQ, purge, alertes) + MAT-015 |
| Produit | ✅ | Pattern outbox CRM = référence pour les autres contextes (MAT-008) |

**Bilan : 10/12 présents (1 N/A), 1 partiel (performance).**

## 3. Risques

1. Généralisation de l'outbox aux événements plateforme/HR/COMMS (MAT-008) — le CRM est le seul contexte couvert.
2. Budgets de lag/volume à formaliser pour les webhooks sortants à fort débit.
3. Replay webhook manuel (DLQ) : drill d'exploitation à tracer dans `RUNBOOK_DRILLS_LOG.md`.

## 4. Plan de corrections

| Priorité | Correction | Suivi |
|---|---|---|
| P1 | Étendre le contrat outbox aux événements plateforme (CompanyCreated, SubscriptionPaid) | DEP-BC14-followup-1 (dépend MAT-008) |
| P2 | Budgets lag/volume webhooks (MAT-014) | DEP-BC14-followup-2 |
| P2 | Drill replay DLQ daté | DEP-BC14-followup-3 |

## 5. Preuves

- `backend-jobs-ci.yml` vert (QueueJobsTest + warmup PDF paie) ; `CrmOutboxTest` (8 scénarios : dédup, crash, retry, DLQ, pic 200).
- Gardes : threat models (MAT-017), runbooks (MAT-015), budgets (MAT-014), golden journeys.
