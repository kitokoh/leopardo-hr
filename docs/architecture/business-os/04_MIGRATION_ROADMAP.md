# MIGRATION ROADMAP — Phases de consolidation et d'extension

> Date : 2026-09-26 · Base : `02_ARCHITECTURE_DECISION.md`
> Principe : chaque phase est **indépendamment livrable**, sans downtime, migrations additives uniquement. Les phases 0–2 sont de la **consolidation** (dette), 3–5 de l'**extension** (valeur produit). Ordre justifié par l'audit, différent de la proposition de la mission : la sécurité IA et la dette registry passent AVANT toute nouvelle capacité IA.

---

## Vue d'ensemble

```
Phase 0  Stabilisation critique          [2-3 sem]   sécurité IA, prod, dettes chirurgicales
   │
Phase 1  Registre unifié Module/Solution [3-4 sem]   UNE source de vérité (D1)
   │
Phase 2  Convergence transverse & cœur   [4-6 sem]   outbox/webhooks, tests Planning, cycles HR,
   │                                                 Application layer, frontières — PARALLÉLISABLE
   │
Phase 3  AI Gateway minimal + sécurité   [3-4 sem]   router LLM, idempotence, adoption front,
   │                                                 étude company-switch
   │
Phase 4  Léa — onboarding langage naturel[3-4 sem]   NL → intent → provisioning déterministe
   │
Phase 5  Commerce public convergé        [2-3 sem]   PublicOffer contract, arbitrage surfaces
   │
 (F1-F7 — CONDITIONNELS, hors roadmap : marketplace unifié, RAG, provider local, agents, groupe)
```

**Pourquoi cet ordre et pas celui de la mission** : la mission proposait Core/tenancy en phase 2 et AI Gateway en phase 5. Inversé parce que (1) le Core/tenancy **existe déjà** — il n'y a rien à construire, seulement à consolider ; (2) la fuite PII IA est un risque **actuel en production**, pas un pré-requis d'architecture ; (3) Léa ne peut pas précéder le registre unifié (son mapping capabilities→solutions dépend d'une source de vérité unique) ni le router (elle ajoute de la charge LLM).

---

## Phase 0 — Stabilisation critique *(sem. 1–3)*

**Objectif** : éliminer les risques actifs avant tout investissement. Aucune nouvelle feature.

| Livrable | Dette | Issue |
|---|---|---|
| Sanitization PII sur toutes les branches provider (tool_result tableaux inclus) | D2 | BOS-001 |
| Politique de rétention + minimisation `ai_audit_logs` | — | BOS-002 |
| Workers/scheduler dédiés en prod (décision billing #7649 exécutée) | D3 | BOS-003 |
| Write-tool legacy absences → Action canonique (événements restaurés) | D11 | BOS-004 |
| Nettoyage mode schema mort + search_path vestigiel | D14 | BOS-005 |

**Critère de sortie** : 0 fuite PII connue ; cron/queue survivent au spin-down ; suite verte.

## Phase 1 — Registre unifié Module/Solution *(sem. 4–7)*

**Objectif** : une seule source de vérité pour modules, features, solutions. C'est **le** prérequis de toute la suite (Léa, permissions, provisioning).

| Livrable | Issue |
|---|---|
| Spec registre unifié (spec-kit) | BOS-010 |
| `ModuleRegistry` source unique + parité `/auth/me` (dual-read puis bascule) | BOS-011 |
| Migration de consolidation `metadata.modules`/`features` + test de parité | BOS-012 |
| `SolutionManifest` v2 : `industry`, `permissions()` câblées à l'enforcement | BOS-013 |
| Convergence manifests legacy (Delivery, RestaurantManager, double manifest restaurant) | BOS-014 |
| Clarification nommage Feature Registry (inventaire API mobile) | BOS-015 |

**Critère de sortie** : ajouter un module = **1 enregistrement** ; test de parité feature map vert sur tous les tenants ; kill switch fonctionnel via le registre.

## Phase 2 — Convergence transverse & cœur *(sem. 8–13, largement parallèle)*

**Objectif** : arrêter la dérive de duplication avant d'ajouter quoi que ce soit.

| Livrable | Issue |
|---|---|
| Module `Integration` : outbox unifié (×4 → 1), migration progressive par module | BOS-020 |
| Webhooks unifiés (×3 → 1) | BOS-021 |
| Rattrapage tests Planning (2 → ≥30, propriétaire absences/frais) | BOS-022 |
| Réduction cycles cœur HR par contrats `Shared` (allowlist 55 → ≤35) | BOS-023 |
| Couche Application pour les 7 modules à 0 (1 issue par module, parallélisable) | BOS-024 |
| ADR frontière Notification/Communication + déplacement conversations | BOS-025 |
| Achèvement façades : Growth→Billing, Absence→Planning | BOS-026 |
| Unification représentation abonnement (3 → 1) | BOS-027 |

**Critère de sortie** : nouvelle verticale = outbox/webhooks/POS réutilisés, pas recréés ; Planning couvert ; allowlist cycles en baisse mesurée.

## Phase 3 — AI Gateway minimal + sécurité *(sem. 14–17)*

**Objectif** : robustesse multi-provider (pas multiplication des providers) et adoption des capacités dormantes.

| Livrable | Issue |
|---|---|
| Spec router LLM | BOS-030 |
| `ModelRouter` : routage par tâche, fallback, circuit breaker, retry/backoff | BOS-031 |
| Clés d'idempotence métier sur write-tools | BOS-032 |
| Streaming SSE chat (si ≤2 j, sinon F7) | BOS-033 |
| Anti-prompt-injection chat + validation runtime `outputSchema` | BOS-034 |
| Front web assistant + UI confirm/reject (capacités dormantes → utilisables) | BOS-035 |
| **Étude** company-switch + email unique global (ADR + POC, pas d'implémentation) | BOS-036 |

**Critère de sortie** : panne d'un provider ≠ panne de l'assistant ; le portail web a un assistant fonctionnel avec confirmation d'écriture ; ADR multi-org décidé (go/no-go).

## Phase 4 — Léa : onboarding en langage naturel *(sem. 18–21)*

**Prérequis** : Phase 1 (registre = source du mapping) et Phase 3 (router = charge LLM).

| Livrable | Issue |
|---|---|
| Spec Léa (constitution : IA propose, moteur décide) | BOS-040 |
| `BusinessIntent` DTO + JSON schema + validation déterministe + mapping capabilities→solutions | BOS-041 |
| Endpoint `/onboarding/lea/interpret` + rounds de clarification + fallback interview | BOS-042 |
| UI onboarding Léa (front/web), parcours hybride NL ↔ formulaire | BOS-043 |
| Pilote mesuré : complétion, fallback, audit | BOS-044 |

**Critère de sortie** : un tenant « école » et un tenant « restaurant » provisionnés de bout en bout via NL en pilote, avec plan identique en structure à l'interview déterministe ; taux de fallback < 30 %.

## Phase 5 — Commerce public convergé *(sem. 22–24)*

| Livrable | Issue |
|---|---|
| `PublicOfferContract` + conventions `/public/<vertical>/marketplace/*` | BOS-050 |
| ADR produit frontières front/web vs front/marketplace | BOS-051 |

**Critère de sortie** : une nouvelle verticale peut exposer une offre publique en implémentant 1 contrat ; décision documentée sur les surfaces.

---

## Jalons de valeur mesurable

| Jalon | Mesure |
|---|---|
| Fin P0 | Risques actifs = 0 |
| Fin P1 | 1 source de vérité feature (audit : modifier 1 fichier pour ajouter un module) |
| Fin P2 | Coverage Planning ≥ cible ; allowlist cycles ≤ 35 ; outbox unique adoptée par ≥ 2 modules |
| Fin P3 | Failover provider démontré en staging ; assistant web utilisé (baseline adoption) |
| Fin P4 | 2 pilotes Léa réussis, métriques publiées |
| Fin P5 | Contrat public implémenté par ≥ 2 verticales |
