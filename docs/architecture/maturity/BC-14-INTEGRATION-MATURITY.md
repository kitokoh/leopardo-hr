# BC-14 — Integration Runtime — Rapport de maturité (DEP-BC14)

- **Statut :** PARTIAL → corrections livrées (#5890)
- **Date :** 2026-08-29
- **Agent propriétaire :** 14 (Integration Runtime)
- **Référentiel :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` §BC-14
- **Périmètre :** inbox, outbox, queues, webhooks, déduplication, retries, DLQ, replay, backpressure, métriques

## Cartographie de l'existant

| Brique | Composant | Issue |
|---|---|---|
| Inbox (entrant) | `WebhookEventRegistry` (idempotence persistée `(source, event_id)`, rejeu → réponse mémorisée, 202 concurrent) | #5444 |
| Idempotence API | `IdempotencyMiddleware` (POST/PUT/PATCH, clé + corps, 24 h, verrou anti-course) | #5277 |
| Outbox (sortant) | `crm_outbox_events` + `CrmOutboxPublisher` (après commit, clé unique `(company_id, idempotency_key)`) + `CrmOutboxConsumerRegistry` + `crm:outbox-dispatch` | #5741 |
| Replay contrôlé | `crm:outbox-replay` (filtres company/type/limit, `--dry-run`) | #5866 |
| Observabilité | `crm:outbox-status` (compteurs + DLQ redacted), `QueueObservabilityController`, workflow `queue-supervision.yml` | #5866 |
| Queues | Jobs Laravel `database`, `TenantScopedJob`, `EnsureTenantContext`, `QueueHealthCheck` | #5578/#5706 |

## Audit des douze dimensions

| Dim | Statut | Preuve / Lacune |
|---|---|---|
| D1 Domaine | **PRESENT** | Vocabulaire documenté (`docs/architecture/RUNTIME_MESSAGING.md`, runbook `docs/ops/RUNBOOK_FILES_CRM.md`) |
| D2 Données | **PRESENT** | Migration tenant `crm_outbox_events` (unique `(company_id, idempotency_key)`, index `(company_id, status, available_at)`, CHECK statuts) |
| D3 Tenant | **PRESENT** | `TenantManager::withinTenant()` dans le dispatch, `BelongsToCompany`, tests cross-tenant (`CrmOutboxTest`, `AccountingTenantIsolationTest`) |
| D4 API | **PARTIAL** | Pas d'API REST dédiée outbox (CLI/runbook) — acceptable pour un runtime interne ; webhooks documentés OpenAPI |
| D5 Autorisation | **PARTIAL** | Commandes artisan (accès shell) ; surfaces webhooks publiques signées/vérifiées par contrôleur |
| D6 Transactions | **PRESENT** | Publication APRÈS commit ; dédup par contrainte unique + savepoint ; claim atomique |
| D7 Asynchronisme | **PARTIAL→CORRIGÉ** | Retry backoff + jitter, DLQ, replay, backpressure `--limit`, métriques. **Lacune corrigée : lease `processing`** — un worker crash laissait les événements bloqués à vie (aucun reclaim). Ajout du reclaim des leases expirées (15 min) + préservation du budget de tentatives |
| D8 Sécurité | **PARTIAL** | Redaction des erreurs (truncation 120), pas de payload/PII dans les logs ; threat model webhooks (#5740) |
| D9 Frontends | **N/A** | Runtime backend — surface d'exploitation CLI |
| D10 Performance | **PRESENT** | Pic 200 événements zéro perte/doublon avec lag borné (test), lot borné |
| D11 Exploitation | **PRESENT** | Runbook (pause, replay, revue DLQ, purge contrôlée, alertes), `crm:outbox-status`, supervision queue |
| D12 Produit | **PARTIAL** | Golden journey = runbook de bout en bout (publish → dispatch → DLQ → replay → purge) |

## Corrections livrées dans cette PR

1. **Lease des événements `processing` (D7)** — `CrmOutboxDispatchCommand` :
   - les événements `processing` dont `updated_at` est antérieur à 15 min
     (lease expirée — worker crash) sont re-claimés par le prochain worker ;
   - le budget de tentatives est **préservé** (une boucle crash-reclaim reste
     bornée par `MAX_ATTEMPTS` → dead-letter) ;
   - un événement dans sa lease n'est **jamais** volé (zéro double
     traitement).
   Tests `CrmOutboxLeaseTest` (3) : reclaim après lease expirée (effet unique),
   non-reclaim dans la lease, préservation du budget de tentatives.

## Sortie exigée par le backlog

- [x] Test de pic avec zéro perte et zéro doublon métier (`CrmOutboxTest` 200 événements)
- [x] Récupération après crash worker (**nouveau** : reclaim des leases expirées)
- [x] Preuve que le contexte tenant est restauré pour chaque job (`withinTenant` + tests)

## Reste à faire (hors périmètre de cette PR courte)

- Quotas/rate limits par provider (webhooks sortants)
- Shutdown propre des workers (SIGTERM → fin de lot)
- Poison-message handling avancé (payload corrompu → analyse automatisée)
