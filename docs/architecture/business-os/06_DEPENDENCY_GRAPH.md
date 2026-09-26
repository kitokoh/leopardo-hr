# DEPENDENCY GRAPH — Issues Business OS

> Date : 2026-09-26 · Base : `05_GITHUB_ISSUE_PLAN.md`

## 1. Graphe des dépendances

```
PHASE 0 (toutes parallèles, aucune dépendance)
  BOS-001 (PII) ─────────────────────────────┐
  BOS-002 (rétention logs) ◄── BOS-003 (workers prod) [décision owner BLOQUANTE]
  BOS-004 (write-tool legacy)                 │
  BOS-005 (nettoyage schema)                  │
                                              │
PHASE 1                                       │
  BOS-010 (spec registre) ◄── (aucune)        │
       │                                      │
       ├─► BOS-011 (ModuleRegistry) ─► BOS-012 (consolidation données)
       │        │                             │
       │        ├─► BOS-015 (renommage)       │
       │        ├─► BOS-026 (façades)         │
       │        └─► BOS-027 (abonnement)      │
       │                                      │
       └─► BOS-013 (Manifest v2) ─► BOS-014 (convergence manifests)
                                              │
PHASE 2 (parallèle maximal)                   │
  BOS-020 (outbox) ─► BOS-021 (webhooks)      │
  BOS-022 (tests Planning)  [indépendante]    │
  BOS-023 (cycles HR)       [indépendante]    │
  BOS-024a-g (Application ×7) [indépendantes] │
  BOS-025 (Notif/Comm)      [indépendante]    │
                                              │
PHASE 3                                       │
  BOS-030 (spec router) ─► BOS-031 (ModelRouter) ◄── BOS-001
       │                      │
       │                      ├─► BOS-033 (streaming)
       │                      └─► BOS-042 … (Léa endpoint, Phase 4)
  BOS-032 (idempotence) ◄── BOS-001
  BOS-034 (anti-injection) ◄── BOS-001
  BOS-035 (assistant web)   [indépendante — API existante]
  BOS-036 (ADR multi-org)   [indépendante]

PHASE 4
  BOS-040 (spec Léa) ◄── BOS-011 + BOS-013
       │
       └─► BOS-041 (BusinessIntent) ─► BOS-042 (endpoint) ◄── BOS-031
                                     ─► BOS-043 (UI, parallèle via contrat mocké)
                                     ─► BOS-044 (pilote) ◄── BOS-042 + BOS-043

PHASE 5 (indépendantes, peuvent glisser plus tôt si capacité)
  BOS-050 (PublicOffer)   [indépendante]
  BOS-051 (ADR surfaces)  [indépendante]
```

## 2. Chemin critique

```
BOS-010 → BOS-011 → BOS-013 → BOS-040 → BOS-041 → BOS-042 → BOS-044
```
(= registre unifié → manifest v2 → spec Léa → intent → endpoint → pilote). C'est la chaîne la plus longue vers la différenciation produit. BOS-031 (router) la rejoint à BOS-042.

## 3. Blockers identifiés

| Blocage | Bloque | Action |
|---|---|---|
| **Décision budgétaire owner** (workers prod) | BOS-003, puis fiabilité de tout job planifié (BOS-002 effectif en prod) | À trancher en semaine 1 |
| BOS-001 (PII) | BOS-031, BOS-032, BOS-034 | Première issue à merger |
| BOS-011 (registre) | BOS-012, BOS-015, BOS-026, BOS-027, BOS-040 | Ressource senior, pas de parallélisation sur ce fichier |
| BOS-031 (router) | BOS-033, BOS-042 | — |

## 4. Vagues de parallélisation recommandées

| Vague | Issues en parallèle | Agents |
|---|---|---|
| **W1 (sem. 1–3)** | BOS-001, BOS-002, BOS-003, BOS-004, BOS-005, BOS-010, BOS-022, BOS-030 | 4–5 (backend×2, devops, security, architecte) |
| **W2 (sem. 4–7)** | BOS-011 (seul sur Core/Feature) ∥ BOS-013 ∥ BOS-020 (spec+socle) ∥ BOS-024a–c ∥ BOS-025 ∥ BOS-036 | 5–6 |
| **W3 (sem. 8–13)** | BOS-012, BOS-014, BOS-015, BOS-021, BOS-023, BOS-024d–g, BOS-026, BOS-027, BOS-031, BOS-032, BOS-034, BOS-035 | 6–8 (max) |
| **W4 (sem. 14–17)** | BOS-033, fin E2, BOS-040 (spec), BOS-050, BOS-051 | 4–5 |
| **W5 (sem. 18–21)** | BOS-041, BOS-042, BOS-043 (∥ mocké), BOS-044 | 3–4 |

## 5. Risques de conflits de fichiers

| Zone chaude | Issues en tension | Règle |
|---|---|---|
| `api/app/Core/Feature`, `Company.php`, `config/feature-flags.php` | BOS-011, BOS-012, BOS-013, BOS-026, BOS-027 | **Séquentiel** — un seul agent à la fois sur Core |
| `api/app/AI/Orchestrator.php`, `config/ai.php` | BOS-001, BOS-031, BOS-033, BOS-034 | BOS-001 d'abord ; puis 1 agent à la fois |
| `api/app/Modules/Planning/*` | BOS-022, BOS-024 (HR), BOS-026, BOS-023 | BOS-022 en W1 seul ; BOS-026 après BOS-011 |
| `api/app/Modules/{Travel,Restaurant,Edu,Platform}/*Outbox*` | BOS-020 sous-tâches | 1 module migré = 1 PR, jamais 2 modules en même temps par le même agent |
| Migrations `database/migrations/tenant` | Toutes | Règle repo existante : migrations additives, garde CI anti-duplication |

## 6. Migrations sensibles

| Migration | Issue | Risque | Stratégie |
|---|---|---|---|
| Backfill `companies.features`/`metadata.modules` | BOS-012 | Désync feature map | `--dry-run`, rapport diff, test de parité, dual-read 1 release |
| Nettoyage `ai_tool_registry` (retrait tool legacy) | BOS-004 | Tool orphelin appelé par conversation en cours | TTL pending actions 15 min → déployer hors pics |
| Table `ai_write_idempotency` | BOS-032 | Aucun (additive) | — |
| Unicité email (POC uniquement) | BOS-036 | **Élevé** — login, kiosk, invitations | Aucune migration prod dans cette issue ; si go : dual-write + backfill + feature flag, plan de rollback testé |
| Colonnes registre solution | BOS-013 | Aucun (code + config) | — |
