# Runtime messaging fiable — inbox, outbox, queues (MAT-008)

- **Statut :** ratifié — modèle d'exécution asynchrone du monorepo
- **Date :** 2026-08-28
- **Périmètre :** webhooks / intégrations / événements de domaine

## Modèle unifié

| Brique | Composant | Garantie |
|---|---|---|
| **Inbox (entrant)** | `WebhookEventRegistry` (`#5444`) | idempotence persistée par `(source, event_id)` — rejeu renvoie la réponse mémorisée, zéro effet double |
| **Idempotence API** | `IdempotencyMiddleware` (`#5277`) | rejeu sûr des POST/PUT/PATCH (clé + corps), 24 h |
| **Outbox (sortant)** | `crm_outbox_events` + `CrmOutboxPublisher` / `CrmOutboxConsumerRegistry` (`#5741`) | publication APRÈS commit, consommation asynchrone idempotente, zéro perte au crash, zéro doublon au replay |
| **Queues (jobs)** | Laravel queue `database` + `TenantScopedJob` / `EnsureTenantContext` (`#5578`, #5706) | contexte tenant garanti, retry borné |
| **Scheduler** | `crm:outbox-dispatch` (worker/minuteur) | claim atomique pending→processing, lot borné (backpressure) |

## Garanties croisées

1. **Idempotence** : clé `(company_id, idempotency_key)` unique dans l'outbox ;
   consommateurs idempotents (vérifient l'état avant d'appliquer). Un rejeu ne
   duplique jamais un effet.
2. **Déduplication** : publication avec clé dérivée `sha256(event_type|payload)`
   ou fournie — un double publish est absorbé par la contrainte unique.
3. **Retries** : erreur transitoire → backoff exponentiel + jitter (10 s →
   ~300 s max), borné par `MAX_ATTEMPTS` (5).
4. **DLQ** : erreur permanente (payload invalide, no consumer) ou attempts épuisés
   → statut `failed` (dead-letter). **Jamais de purge automatique** : revue
   humaine puis replay contrôlé ou purge documentée (runbook).
5. **Replay contrôlé** : `php artisan crm:outbox-replay` (filtres `--company`,
   `--event-type`, `--limit`, `--dry-run`) — remet `failed → pending` sans
   toucher à l'idempotence.
6. **Backpressure** : `--limit` borne chaque passe ; le reste reste `pending`
   (lag borné, testé en pic).
7. **Observabilité** : `php artisan crm:outbox-status` (compteurs par statut +
   échantillon DLQ **redacted**) ; alertes runbook (dead-letter rate).

## Scénarios d'échec couverts par les tests

| Scénario | Garantie | Test |
|---|---|---|
| Pic simulé (200 événements) | zéro perte, zéro doublon, lag borné | `CrmOutboxTest::test_load_pic_zero_loss_zero_duplicate_with_bounded_lag` |
| Crash entre publish et dispatch | zéro perte | `test_crash_between_publish_and_dispatch_loses_nothing` |
| Crash après effet | rejeu sans doublon | `test_crash_after_effect_does_not_duplicate_on_replay` |
| Erreur transitoire | retry + backoff puis succès | `test_transient_errors_retry_with_backoff_and_succeed` |
| Erreur permanente / no consumer | dead-letter immédiate | `test_permanent_errors_go_to_dead_letter_immediately` |
| Replay contrôlé d'un DLQ | failed → pending → sent, effet unique | `CrmOutboxRuntimeTest::test_replay_requeues_dead_letter_and_dispatch_succeeds` |
| Replay dry-run | aucune modification | `test_replay_dry_run_changes_nothing` |
| Replay ciblé (company/type) | filtres respectés | `test_replay_respects_company_and_event_type_filters` |
| Replay d'un effet déjà appliqué | zéro doublon | `test_replay_never_duplicates_an_applied_effect` |
| Backpressure | lot borné, reste en pending | `test_dispatch_limit_bounds_batch_backpressure` |
| Observabilité | compteurs + DLQ redacted | `test_status_command_reports_counts_and_dlq_sample_redacted` |

## Runbook

`docs/ops/RUNBOOK_FILES_CRM.md` : pause d'urgence, replay (commandes),
revue DLQ, purge contrôlée, alertes.
