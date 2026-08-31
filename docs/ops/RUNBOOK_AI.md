# Runbook — Exploitation BC-23 AI Assistive Services (issue #6240, BC-23-D11)

- **Statut :** actif — procédures opérationnelles du contexte IA
- **Date :** 2026-08-30
- **Composants :** `api/app/AI` (Orchestrator, AgentRunner, ToolRegistry, IntentEngine, WriteToolPolicy, TokenBudgetGuard, AIAuditLogger, MemoryManager), routes `/api/v1/ai/*` (chat, agent, workflows, analytics, exports), file `ai` (`ExportAiConversationJob`), dead-letter queue `ai_dead_letter_queue` (replay `ai:dlq:replay`), tables tenant `ai_conversations` / `ai_audit_logs` / `ai_tool_registry` / `ai_exports`
- **Feature flags / kill switch :** `AI_ENABLED` (config `ai.enabled`) — désactivation coupante : toute route `/api/v1/ai/*` répond alors `feature_disabled` (middleware `AIFeatureCheck`)

---

## 1. Architecture (en une minute)

- **Chat / agent** : `POST /ai/chat` et `POST /ai/agent/run` → Orchestrator → LLM provider (OpenAI ou Claude, config `ai.providers.*`) → outils (read via IntentEngine, write via WriteToolPolicy + confirmation humaine).
- **Permissions** : matrice versionnée `ai.tool_permissions` + `ai.role_permissions` (BC-23-D05) — refus fail-closed `AI_TOOL_PERMISSION_DENIED`.
- **Budgets** : `ai.budgets.*` (BC-23-D10) — dépassement → 422 `AI_TOKEN_BUDGET_EXCEEDED`, tracé dans `ai_audit_logs.error`.
- **Asynchrone** : export de conversation (BC-23-D07) → job `ExportAiConversationJob` (file `ai`, 3 retries) → échec définitif consigné en DLQ `ai_dead_letter_queue`.
- **Traçabilité** : chaque échange LLM est consigné dans `ai_audit_logs` (prompt/réponse tronqués à 10 000 chars, tokens, coût estimé, erreur, workflow).

## 2. Supervision

| Métrique / alerte | Source | Seuil conseillé | Action |
|---|---|---|---|
| Taux d'erreur provider | `GET /ai/analytics/errors` (success_rate) | < 95 % sur 30 j | Voir §3.1 |
| Coût mensuel | `GET /ai/analytics/costs` | budget tenant (quotas `ai.quotas`) | Alerter le tenant, réduire quotas |
| p95 tokens/requête | `GET /ai/analytics/usage` (`p95_tokens_per_request`) | > 80 % du budget requête | Voir §3.2 (prompt trop long, outils trop verbeux) |
| File `ai` en retard | queue worker (`queue:ai`) | lag > 15 min | Vérifier le worker, voir §4 |
| DLQ non vide | `ai_dead_letter_queue` status `open` | > 0 depuis > 1 h | Voir §4 (revue + replay) |
| Latence LLM | `ai_audit_logs.duration_ms` | p95 > 30 s | Provider lent / modèle trop gros |
| Quota tenant épuisé | `AI_QUOTA_EXCEEDED` (422) | — | Communiquer avec le tenant |

## 3. Incidents connus

### 3.1 Provider LLM down (OpenAI/Anthropic)

- **Symptôme :** erreurs en masse dans `/ai/analytics/errors`, `ai_audit_logs.error` renseigné (`API error: 5xx`), chat/agent en échec.
- **Actions :** vérifier le statut du provider (status page) ; le code ne retente pas côté app (1 appel) → les clients réessayent ; les **write-tools ne sont jamais exécutés sans confirmation** (aucun effet de bord automatique) ; si l'incident dure : désactiver `AI_ENABLED=false` (kill switch) ou basculer de provider (`AI_PROVIDER=claude|openai`) — les deux clés doivent être configurées pour la bascule.
- **Restauration :** rétablir le provider, vérifier `success_rate` qui remonte ; les jobs d'export (`ai_exports` failed) se relancent via `ai:dlq:replay` après correction.

### 3.2 Dépassement de budget de tokens

- **Symptôme :** 422 `AI_TOKEN_BUDGET_EXCEEDED` (requête, contexte de conversation, ou workflow agent).
- **Cause fréquente :** historique de conversation trop long (budget de contexte) → **nouvelle conversation** ; prompts très longs ; agent trop d'étapes.
- **Actions :** vérifier `ai_audit_logs.error` (détail serveur) ; ajuster `AI_BUDGET_MAX_*` (limites par défaut raisonnables : requête 4096, contexte 32768, workflow 16384) ; p95 dans `/ai/analytics/usage` pour calibrer.

### 3.3 Fuite de prompt entre tenants (scénario interdit)

- **Symptôme :** réponse contenant des données d'un autre tenant.
- **Garde en place :** `AITenantInjector` + scope `company_id` systématique ; la **sortie exige qu'un prompt tenant A ne soit jamais réutilisé pour B**.
- **Actions :** ouvrir un incident P1 (runbook `RUNBOOK_INCIDENT_P1.md`) ; vérifier `ai_conversations.company_id` / `ai_audit_logs.company_id` ; purge RGPD ciblée (§5) ; revue du prompt système (`ai.system_prompt_path`) pour absence de données tenant.

## 4. File AI et dead-letter queue

- **File** : jobs d'export sur la queue `ai` (`queue:ai`), 3 retries, timeout 120 s, tenant scoped (`EnsureTenantContext`).
- **DLQ** : échec définitif → entrée `ai_dead_letter_queue` (status `open`, unicité par `dedup_key`) + `ai_exports.status = failed`.
- **Revue DLQ** :
  ```bash
  # Lister les entrées ouvertes
  SELECT id, company_id, job_class, error, attempts, created_at
    FROM ai_dead_letter_queue WHERE status = 'open' ORDER BY id;
  ```
- **Replay contrôlé** (après correction de la cause) :
  ```bash
  php artisan ai:dlq:replay --limit=10            # 10 entrées les plus anciennes
  php artisan ai:dlq:replay --company-id=<uuid>    # un tenant précis
  php artisan ai:dlq:replay --id=<dlq_id>          # une entrée précise
  ```
  Le replay remet l'export `pending` et re-dispatch le job ; succès → DLQ `resolved`.
- **Idempotence** : jamais de doublon d'export (contrainte unique `dedup_key` sur `ai_exports`).

## 5. RGPD — purge des prompts / conversations

- Les prompts et réponses sont stockés dans `ai_conversations.messages` et `ai_audit_logs` (tronqués à 10 000 chars). Toute suppression de conversation (`DELETE /ai/chat/{conversationId}`) n'efface **pas** l'audit (traçabilité) — la purge d'audit suit la politique de rétention du tenant.
- **Purge ciblée (exercice de droit / demande RGPD) :**
  ```sql
  -- Sur le schéma du tenant concerné (jamais en prod sans validation + backup)
  DELETE FROM ai_conversations WHERE company_id = '<tenant>' AND updated_at < now() - interval '<rétention>';
  DELETE FROM ai_audit_logs      WHERE company_id = '<tenant>' AND created_at  < now() - interval '<rétention>';
  DELETE FROM ai_exports         WHERE company_id = '<tenant>' AND created_at  < now() - interval '<rétention>';
  ```
  `ai_exports` porte une FK `conversation_id → ai_conversations (ON DELETE CASCADE)` : supprimer les conversations purge les exports orphelins (les fichiers, eux, sont purgés par la politique du disque `local`).
- **Aucun secret dans les logs** : les clés LLM vivent en env/Pulumi (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`) ; les logs applicatifs ne logguent ni prompt complet, ni réponse, ni clé.

## 6. Kill switch / désactivation

- **Coupure totale** : `AI_ENABLED=false` (env) → toutes les routes `/api/v1/ai/*` répondent `feature_disabled` (fail-closed, aucune requête LLM, aucun coût).
- **Coupure par quota** : `ai.quotas.*` (trial 10, starter 50, business 200, enterprise illimité) → 422 `AI_QUOTA_EXCEEDED`.
- **Restriction d'outil** : matrice `ai.tool_permissions` (désactiver un outil = retirer son entrée) — garde CI `ToolPermissionMatrixCoverageTest` impose de maintenir l'alignement avec `ai_tool_registry`.

## 7. Backup / restore / rollback

- **Backup** : les tables AI sont des tables tenant → couvertes par le dump schémas tenant du runbook plateforme `RUNBOOK_BACKUP_RESTORE.md` (RPO < 24 h, RTO < 4 h).
- **Rollback** : déploiements additifs uniquement (migrations `ai_*` additives, config additive) → rollback = redeploy de la version précédente (`RUNBOOK_ROLLBACK.md`), aucune migration destructive à annuler.
- **Migrations** : `ai_exports` / `ai_dead_letter_queue` / `ai_audit_logs.workflow` — réentrantes (`schemaTableExists`), `down()` sans perte irrécupérable (colonnes additifs).

## 8. Contacts & escalade

- **Propriétaire BC-23 :** Agent 23 — BC-AI (registre `dev-hub/governance/bounded-context-registry.json`).
- **Incidents P1 :** runbook `RUNBOOK_INCIDENT_P1.md` ; Sentry (tag `ai`) ; logs structurés (`StructuredLogging`) avec `request_id`/`correlation_id`.
