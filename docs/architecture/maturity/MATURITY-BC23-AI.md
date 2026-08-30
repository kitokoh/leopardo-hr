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
| D5 | Autorisation | 🟡 PARTIEL | Gardes employé/manager ; **WriteToolPolicy** (actions d'écriture bornées + confirmation humaine) — solide ; pas de matrice de permission fine par outil AI (recommandation 1). |
| D6 | Transactions | 🟢 PRÉSENT | **Écriture IA = confirmation humaine obligatoire** (AIWriteActionConfirmationTest) — garde anti-actions non désirées. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Workflows IA synchrones ou par jobs ; **file AI + DLQ dédiée** (issue #6239) : exports asynchrones de conversations (job `ExportAiConversationJob` tenant-scoped, file `ai`, idempotent par `dedup_key`), dead-letter queue `ai_dead_letter_queue` + replay contrôlé `ai:dlq:replay`, corrélation workflow `conversation_export` dans l'audit. |
| D8 | Sécurité | 🟢 PRÉSENT | **AIAuditLogger** (traçabilité des décisions), prompts bornés, pas de secret provider en clair (clé LLM via env/Pulumi). |
| D9 | Frontend | 🟢 PRÉSENT | Assistant web + apps mobile (chat, confirmations d'actions). |
| D10 | Performance | 🟡 PARTIEL | Throttle AI dédié (`ai-sensitive`, `ai-plan`) ; budgets de tokens non versionnés. |
| D11 | Exploitation | 🟢 PRÉSENT | Analytics IA (AIGatewayAndAnalyticsTest), logs structurés, audit trail complet. |
| D12 | Produit | 🟡 PARTIEL | Parcours chat → intent → action → confirmation testé (17 tests locaux verts) ; pas de golden journey IA end-to-end ni seed pilote dédié. |

## Vérification locale (preuve)

```
php artisan test --filter="AIWriteActionConfirmationTest|AIWorkflowTest|AIGatewayAndAnalyticsTest"
→ 17 passed (93 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Matrice de permissions par outil** (D5) : versionner la liste des
   write-tools autorisés par rôle (WriteToolPolicy) avec tests négatifs par
   rôle.
2. **Budgets de tokens** (D10) : verrouiller les limites par requête/workflow
   (p95) une fois le référentiel MAT-014 mergé.
3. **Golden journey** (D12) : seed pilote IA (intents + outils simulés,
   aucune donnée réelle) + test end-to-end chat → action → confirmation →
   audit.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
