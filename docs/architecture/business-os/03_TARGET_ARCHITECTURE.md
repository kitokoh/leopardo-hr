# TARGET ARCHITECTURE — Existant → Transition → Cible

> Date : 2026-09-26 · Base : décision `02_ARCHITECTURE_DECISION.md`
> Principe : **aucune réécriture**. Chaque flèche est une évolution du code existant, jamais un composant nouveau plaqué par-dessus.

---

## 1. Vue d'ensemble : EXISTANT → TRANSITION → TARGET

```
EXISTANT (2026-09)                          TRANSITION (Phases 0-2)                     TARGET (Phases 3-5)
─────────────────────                       ──────────────────────────                  ──────────────────────────

API Laravel 12                              API Laravel (inchangé)                      API Laravel
├─ Core/Tenant (Company, scope)      ══►    ├─ idem (nettoyage schema mort)      ══►    ├─ Core/Tenant + company-switch
├─ Core/Auth (RBAC 3 couches)        ══►    ├─ idem                                  ═►    ├─ Core/Auth + resource scopes généralisés
├─ Features ×3 sources             ─ FIX ─► ├─ REGISTRE UNIFIÉ (1 source)          ══►    ├─ ModuleRegistry (unique, versionné)
├─ Core/Solutions (catalogue+activ.) ─ EXT ─► ├─ + industry, permissions câblées   ══►    ├─ SolutionRegistry étendu
├─ 32 Modules (9 verticales)         ══►    ├─ idem + convergence patterns         ══►    ├─ Modules + noyau transverse Integration
│   └─ cycles cœur HR              ─ REF ─► │   └─ cycles réduits, Application OK  ══►    │   └─ isolation sans allowlist massive
├─ Provisioning déterministe       ══►    ├─ idem                                  ══►    ├─ Provisioning (inchangé)
│   └─ SetupInterviewPlanner       ─ EXT ─► │   └─ inchangé                        ─ EXT ─► │   └─ + LÉA (NL → intent → moteur)
├─ app/AI (orchestrator, tools)    ─ FIX ─► ├─ PII fix, idempotence writes         ══►    ├─ app/AI durci
│   └─ LLMClient 1 driver          ─ EXT ─► │   └─ (Phase 3) ModelRouter minimal   ══►    │   └─ ModelRouter + fallback + CB
├─ Commerce public ×3 surfaces     ══►    ├─ conventions API publique communes   ══►    ├─ PublicOffer contract partagé
│                                                                              (F1) ─ ─ ► │   └─ (marketplace unifié : CONDITIONNEL)
├─ Marketplaces verticales ×2      ══►    ├─ idem                                  ══►    ├─ idem
└─ Edge on-prem (sync, kiosk, vidéo)══►    └─ idem                                  ══►    └─ idem
```

**Ce qui ne change JAMAIS** : Company comme tenant, global scope fail-closed, RBAC 3 couches, provisioning déterministe, tools IA whitelists + confirmation humaine, spec-kit, découpage BC.

---

## 2. Architecture cible détaillée

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                              SURFACES (inchangées)                            │
│  front/web (portail tenant+vitines) · marketplace · travel-web ·              │
│  admin-dashboard (super-admin) · 8 apps Flutter · kiosk · web-offline (edge)  │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ HTTPS /api/v1 (Sanctum) · /api/v1/public/* (invité)
┌──────────────────────────────────▼───────────────────────────────────────────┐
│                              API LARAVEL — MONOLITHE MODULAIRE                │
│                                                                              │
│  ┌─ CORE (socle stable) ──────────────────────────────────────────────────┐  │
│  │ Tenant : Company · TenantManager · BelongsToCompany (fail-closed)       │  │
│  │          [+] CompanySwitchService (session multi-org, Phase 3)          │  │
│  │ Auth   : Employee/User/SuperAdmin · RBAC 3 couches                      │  │
│  │          [+] resource_assignments consommés par toutes les policies     │  │
│  │ Registry: ModuleRegistry (SOURCE UNIQUE: modules, features, flags,      │  │
│  │           kill switch) · SolutionCatalogue+Manifest (industry,          │  │
│  │           permissions enforceées) · SolutionActivator (idempotent)      │  │
│  │ Privacy · Seed · Http                                                   │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌─ TRANSVERSE (capacités partagées) ─────────────────────────────────────┐  │
│  │ Billing (subs, paiements, AI credits) · Accounting · CRM · Notification │  │
│  │ Communication · Cabinet (GED) · Platform (console, provisioning)        │  │
│  │ [NEW] Integration (outbox + webhooks unifiés — converge les ×4/×3)      │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌─ VERTICALES (activables par Solution) ────────────────────────────────┐   │
│  │ HR+Payroll+Attendance+Planning (cœur historique) · RestaurantManager   │   │
│  │ TravelAgency · EduManager · HealthManager · Pharmacy · FuelStation     │   │
│  │ HospitalityManager · Retail · Delivery · (+ futures via manifest)      │   │
│  │ Règle: 1 verticale = 1 manifest = 1 feature flag = isolation CI        │   │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌─ AI (api/app/AI) ──────────────────────────────────────────────────────┐  │
│  │ AIGateway (HTTP) → Orchestrator → ModelRouter ─┬─ OpenAI               │  │
│  │   │ budgets/quotas/audit/PII-sanitizer (toutes │─ Anthropic            │  │
│  │   │ branches, tool_result inclus)              │─ Groq                 │  │
│  │   │                                            └─ (futur: 1 provider   │  │
│  │   ├─ ToolRegistry → matrice fail-closed        │   sur trigger F4)     │  │
│  │   ├─ Read tools (handlers fixes, company_id)   │ fallback+circuit-break│  │
│  │   └─ Write tools → confirmation humaine →      │ retry+streaming       │  │
│  │       WriteActionRunner (parité services REST, │                       │  │
│  │       [+] clés idempotence métier)             │                       │  │
│  │   ├─ LÉA : NL → BusinessIntent(JSON schéma) →  │                       │  │
│  │   │   validation déterministe → SetupInterview │                       │  │
│  │   │   Planner → SolutionActivator              │                       │  │
│  │   └─ Core/AI ports (STT, Face, OCR) inchangés  │                       │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌─ PUBLIC COMMERCE (par verticale) ──────────────────────────────────────┐  │
│  │ /public/retail/marketplace · /public/travel/marketplace · vitrines      │  │
│  │ [+] PublicOfferContract (convention commune: catalog, availability,     │  │
│  │     pricing, order/reservation) — SANS portail unifié (trigger F1)      │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
        │                                    │
┌───────▼────────┐                 ┌─────────▼──────────┐
│ PostgreSQL      │                │ EDGE on-prem        │
│ public (registry│                │ sync offline, kiosk,│
│ companies, plans)│               │ vidéo — inchangé    │
│ shared_tenants  │                └─────────────────────┘
│ (company_id partout)│
└─────────────────┘
```

---

## 3. Responsabilités et frontières

| Couche | Possède | Ne possède jamais |
|---|---|---|
| **Core** | Identité, tenant, RBAC, registre modules/solutions, activation, privacy | Logique métier, données verticales |
| **Transverse** | Capacités multi-métiers (billing, compta, CRM, notif, intégration) | Données d'une seule verticale |
| **Verticales** | Leur domaine, leurs établissements, leur commerce public | Imports directs d'autres verticales (contrats `Shared/Contracts` uniquement) |
| **AI** | Orchestration LLM, tools, budgets, audit, Léa (intent) | Vérité métier, SQL généré, écriture sans confirmation, décision de provisioning |
| **Public Commerce** | Exposition read/write invité via API dédiées + tokens | Accès direct aux tables internes d'autres tenants |
| **Edge** | Continuité offline, devices | IA, logique métier cloud |

### Frontières IA (décision A6 — formalisation)

```
Model        = LLMClient + drivers + ModelRouter (inférence interchangeable, stateless)
Agent        = (futur, 2e usage = Léa) AgentDefinition: prompt + tools + politiques. 1 seul agent implicite jusqu'à Léa.
Tool         = AIToolDefinition typée + matrice RBAC fail-closed + audit + tenant depuis session
Workflow     = app/AI/Workflows — DÉTERMINISTE, SQL, zéro LLM (ne pas renommer « AI workflow »)
Automation   = événements Laravel hors périmètre IA (correction de la taxonomie mission)
Memory       = ai_conversations (session, 50 msgs) — suffisante
Knowledge    = N'EXISTE PAS (F3). Si créé un jour: tables séparées, scoping company_id + ACL au centre du design
BusinessData = tables métier scopées — SEULE source de vérité, accès IA uniquement via tools
Conversation = interaction utilisateur (endpoints /ai/chat)
```

---

## 4. Flux Léa (cible, Phase 4)

```
Utilisateur: « Je dirige une école de 600 élèves, 40 enseignants,
              je veux gérer classes, paiements, enseignants, parents. »
      │
      ▼
POST /api/v1/onboarding/lea/interpret   (public, rate-limited, auth trial)
      │
      ▼
┌─ LeaIntentService ─────────────────────────────────────────┐
│ 1. LLM (json_schema strict) → BusinessIntent DTO           │
│    { industry, business_type, locations, capabilities[] }  │
│ 2. Validation DÉTERMINISTE:                                │
│    - industry ∈ allowlist SolutionCatalogue                │
│    - capabilities ∈ capacités déclarées des manifests      │
│    - inconnu → question de clarification (max 2 rounds)    │
│ 3. Mapping capabilities → solutions + outils horizontaux   │
│    (table de correspondance versionnée en code)            │
└────────────────────────────────────────────────────────────┘
      │ intent validé (ou clarifié)
      ▼
SetupInterviewPlanner (EXISTANT) → plan {solutions, tools}
      ▼
SolutionActivator (EXISTANT, idempotent, fail-closed, audité)
      ▼
Workspace provisionné — l'IA n'a JAMAIS écrit quoi que ce soit.
```

Sécurité : sortie LLM contrainte par JSON schema ; aucune clé libre ; allowlist fermée ; plan final identique en structure à celui de l'interview actuel ; audit `lea.intent.interpreted` ; fallback automatique vers l'interview déterministe si le LLM échoue (dégradation gracieuse, jamais bloquant).

---

## 5. Compatibilité ascendante (garanties par phase)

| Phase | Impact données | Impact API | Impact tenants existants | Rollback |
|---|---|---|---|---|
| 0 — Stabilisation | Aucune migration destructive | Aucun | Aucun | Revert commits |
| 1 — Registre unifié | Migration de consolidation `metadata.modules`/`features` → dérivés du registre (backfill) | `/auth/me` inchangé (même sortie) | Aucun (feature map reconstruite à l'identique, test de parité) | Registre dual-read 1 release |
| 2 — Convergence | Aucune | Aucun | Aucun | Par module, indépendant |
| 3 — AI router + company-switch | Étude email unique AVANT toute migration ; resource_assignments progressives (règle historique conservée) | Nouveaux champs optionnels | Aucun avant bascule explicite | Flag |
| 4 — Léa | Aucune (réutilise provisioning) | Nouveaux endpoints additifs | Aucun | Flag `onboarding.lea.enabled` |
| 5 — Commerce conventions | Aucune | Additif | Aucun | N/A |

Downtime : **0** visé sur toutes les phases (migrations additives uniquement, dual-read quand nécessaire).
