# Sous-système `app/AI` — exception documentée à la convention Modules

**Statut** : exception documentée (#7986) — la convention « tout métier dans `app/Modules/*` »
(~447 controllers et les modules DDD) admet **un** écart assumé : le moteur agent IA.

## Pourquoi hors `app/Modules`

`app/AI` (52 fichiers) est un **runtime transverse** : il sert plusieurs verticales
(Communication, RH, Analytics…) au lieu d'en être une. Le faire rentrer dans un module
(`app/Modules/AI`) créerait un module « plateau » consommé par tous les autres — la même
position que `app/Core` et `app/Shared`, avec une frontière unique : c'est le seul
sous-système applicatif hors `Modules/` (verrou : ne pas en créer d'autre sans ADR ici).

## Carte (2026-09)

| Élément | Rôle |
|---|---|
| `IntentEngine.php` | classification d'intention → outil **read** (`supportedReadTools()`) |
| `ToolRegistry.php` | registre déclaratif des outils IA ; couverture verrouillée par `ToolRegistryCoverageTest` (tout outil actif a un handler) |
| `WriteActionRunner.php` / `WriteToolPolicy.php` | exécution des outils **write** avec politique d'autorisation |
| `ToolPermissionPolicy.php` | permissions outil × rôle |
| `LLMClient.php` | client fournisseur LLM (timeouts, budget) |
| `TokenBudgetGuard.php` | garde de budget tokens par contexte |
| `Orchestrator.php` / `AgentRunner.php` | orchestration multi-étapes et exécution d'agent |
| `MemoryManager.php` | mémoire conversationnelle bornée |
| `AIAuditLogger.php` | piste d'audit des décisions/outils |
| `PendingActionStore.php` | actions en attente de confirmation humaine |
| `Jobs/` `Workflows/` | exécution asynchrone (file tenant-aware) |
| `Interfaces/Api/` | contrôleurs V1 (`AIGatewayController`, `ConversationExportController`, `VoiceController`…) |
| `Privacy/` | minimisation/PII avant envoi fournisseur |
| `Planning/` `Predictions/` | planification et modèles prédictifs internes |

## Règles

1. Tout nouvel outil IA = entrée registre **+** handler (`IntentEngine` ou `WriteActionRunner`)
   dans le même commit — le test de couverture échoue sinon (cf. #8004 pour le cas
   `email_classify` enregistré sans handler).
2. Aucun appel LLM sans passer par `LLMClient` + `TokenBudgetGuard` (budget et timeouts
   centralisés).
3. Toute donnée utilisateur vers un fournisseur externe transite par `Privacy/`.
4. Exception unique : ne pas créer d'autre sous-système racine (`app/<Foo>`) sans documenter
   ici l'exception et sa justification.
