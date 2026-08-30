# Rapport de maturité — BC-23 AI

> **DEP-BC23 (issue #5899)** — Deep maturity, BC-23 AI Assistive Services.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 23.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-23).

## Périmètre

OCR, suggestions et assistants bornés, registry, consentement et traçabilité :
`api/app/AI` (AgentRunner, Orchestrator, IntentEngine, LLMClient, ToolRegistry,
WriteToolPolicy, PendingActionStore, Workflows, Predictions, Planning,
MemoryManager, AIAuditLogger), routes `/api/v1/ai/*` (chat, workflows,
actions à confirmer), gateway + analytics.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Structure claire (orchestrateur, agents, outils, workflows, prédictions, mémoire). Vocabulaire : intents, pending actions, write tools, confirmations. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (conversations, pending actions), index cohérents. |
| D3 | Tenant | 🟢 PRÉSENT | Contexte tenant injecté (AITenantInjector), conversations scopées, isolation par company. |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/ai/*` (chat, history, tools, actions confirm/reject, workflows), Requests validées, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Gardes employé/manager ; **WriteToolPolicy** (actions d'écriture bornées + confirmation humaine) ; **matrice de permissions par outil AI versionnée** (`ai.tool_permissions` + `ai.role_permissions`, issue #6237) : rôle minimal + permissions requises, enforce à l'exécution (lecture ET écriture, y compris à la confirmation), refus fail-closed `AI_TOOL_PERMISSION_DENIED`, exposition `/ai/tools` filtrée par rôle, garde anti-dérive config ↔ registre. |
| D6 | Transactions | 🟢 PRÉSENT | **Écriture IA = confirmation humaine obligatoire** (AIWriteActionConfirmationTest) — garde anti-actions non désirées. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Workflows IA synchrones ou par jobs ; **file AI + DLQ dédiée** (issue #6239) : exports asynchrones de conversations (job `ExportAiConversationJob` tenant-scoped, file `ai`, idempotent par `dedup_key`), dead-letter queue `ai_dead_letter_queue` + replay contrôlé `ai:dlq:replay`, corrélation workflow `conversation_export` dans l'audit. |
| D8 | Sécurité | 🟢 PRÉSENT | **AIAuditLogger** (traçabilité des décisions), prompts bornés, pas de secret provider en clair (clé LLM via env/Pulumi). |
| D9 | Frontend | 🟢 PRÉSENT | Assistant web + apps mobile (chat, confirmations d'actions). |
| D10 | Performance | 🟢 PRÉSENT | Throttle AI dédié (`ai-sensitive`, `ai-plan`) ; **budgets de tokens versionnés** (`ai.budgets.*`, issue #6238) : cumul par requête, par contexte de conversation et par exécution d'agent, fail-closed 422 `AI_TOKEN_BUDGET_EXCEEDED` + p95 par requête/workflow dans l'analytics. |
| | Exploitation | 🟢 PRÉSENT | Analytics IA (AIGatewayAndAnalyticsTest), logs structurés, audit trail complet ; **runbook d'exploitation dédié** `docs/ops/RUNBOOK_AI.md` (issue #6240 : supervision, incidents provider/budget, file AI + DLQ + replay, purge RGPD, kill switch, backup/rollback) enregistré dans `runbook-registry.json` (MAT-015) + preuve d'exercice datée. |
| D12 | Produit | 🟡 PARTIEL | Parcours chat → intent → action → confirmation testé (17 tests locaux verts) ; pas de golden journey IA end-to-end ni seed pilote dédié. |

## Vérification locale (preuve)

```
php artisan test --filter="AIWriteActionConfirmationTest|AIWorkflowTest|AIGatewayAndAnalyticsTest"
→ 17 passed (93 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. ~~**Matrice de permissions par outil** (D5)~~ **LIVRÉ (BC-23-D05, issue #6237)** :
   `ai.tool_permissions` + `ai.role_permissions` (config versionnée, alignée
   sur `ai_tool_registry`), `ToolPermissionPolicy` (rôle minimal + permissions
   requises, hiérarchie employee<manager<admin<super_admin), enforcement dans
   l'IntentEngine (lecture ET écriture, y compris à la confirmation — refus
   fail-closed `AI_TOOL_PERMISSION_DENIED`, aucune pending action ni effet de
   bord), `/ai/tools` filtré par rôle, garde `ToolPermissionMatrixCoverageTest`
   (config ↔ registre). 9 tests (`ToolPermissionMatrixTest`).
2. ~~**Budgets de tokens** (D10)~~ **LIVRÉ (BC-23-D10, issue #6238)** :
   `config/ai.php` → `ai.budgets.{max_tokens_per_request,max_context_tokens,
   max_tokens_per_workflow}` (env override), `TokenBudgetGuard` (fail-closed
   422 `AI_TOKEN_BUDGET_EXCEEDED`, tracé dans `ai_audit_logs.error`), cumul
   de contexte par conversation, budget workflow sur `agent_run`, colonne
   `ai_audit_logs.workflow` + p95 par requête/workflow dans
   `/ai/analytics/usage`. 9 tests (`TokenBudgetTest`).
3. **Golden journey** (D12) : seed pilote IA (intents + outils simulés,
   aucune donnée réelle) + test end-to-end chat → action → confirmation →
   audit.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
