# ARCHITECTURE DECISION — Transformation « Business OS »

> Date : 2026-09-26 · Statut : **DÉCISION** · Base : `00_AUDIT_ETAT_REEL.md`, `01_GAP_ANALYSIS.md`
> Cette décision se rattache à : P03 (positionnement suite métier), ADR-0012 (FOCUS), ADR-0004 (open-core/marketplace), FREEZE_SCOPE_60J (#5147), BOUNDED-CONTEXT-REGISTRY.

---

## Verdict

# ✅ ARCHITECTURE ACCEPTÉE AVEC MODIFICATIONS — sous forme de CONSOLIDATION, pas de transformation

La vision « Business OS + AI Platform + Public Commerce » est **compatible avec le Leopardo actuel parce qu'elle est déjà majoritairement incarnée dans le code**. La décision n'est donc pas « transformer » mais :

1. **Consolider** ce qui existe (dettes critiques D1–D5) ;
2. **Converger** ce qui est dupliqué (manifests, outbox, webhooks, frontières) ;
3. **Étendre** chirurgicalement (registry unifié, router LLM minimal, Léa, company-switch) ;
4. **Refuser** tout nouveau concept structurel (Organization, Workspace, RAG, 10 providers) ;
5. **Reporter** ce qui n'a pas de preuve de besoin (marketplace unifié, agents, knowledge).

Ordre de priorité respecté : **REUSE → EXTEND → REFACTOR → CREATE**. Aucune réécriture. Compatibilité ascendante totale : HR, Payroll, Attendance, mobile, API, tenants existants ne sont pas impactés structurellement par les phases 0–4.

---

## 1. Ce que j'ACCEPTE

| # | Décision | Justification |
|---|---|---|
| A1 | **La direction multi-métiers composable** (Core + verticales activables) | Déjà la réalité : 9 verticales, SolutionCatalogue, activation par feature flags. Valide P03 « suite métier » |
| A2 | **Le socle Core actuel comme fondation** (Tenant, Auth, RBAC, Solutions, Feature, Privacy) | Solide, fail-closed, gardé en CI. Aucune alternative ne justifie une refonte |
| A3 | **Le provisioning déterministe comme socle de l'onboarding intelligent** | CompanyProvisioningService + SolutionActivator + SetupInterviewPlanner sont exactement le moteur demandé par la mission |
| A4 | **L'architecture IA actuelle** (Orchestrator, tools typés, matrice fail-closed, confirmation humaine, audit) | Conforme security-by-design ; supérieure à ce que la mission demandait (l'IA ne génère jamais de SQL, tenant depuis la session) |
| A5 | **Le principe « Léa » : langage naturel → intent structuré → validation → provisioning déterministe** | Faisable à coût maîtrisé **parce que** le moteur déterministe existe. L'IA propose, le moteur valide et exécute — jamais l'inverse |
| A6 | **La séparation des concepts IA** (Model/Agent/Tool/Workflow/Memory/Knowledge/Business Data/Conversation) | Déjà respectée par le code ; à formaliser dans la doc, à préserver |
| A7 | **Le commerce public par verticale** avec API publiques dédiées (pas d'accès direct aux tables tenant) | Pattern déjà éprouvé ×3 (retail, travel, vitrine) |
| A8 | **Le spec-driven development existant** (specify → clarify → plan → tasks → implement, 248 specs) | Suffisant pour la nouvelle architecture. Aucune amélioration structurelle requise |

## 2. Ce que je MODIFIE (par rapport aux propositions de la mission)

| # | Proposition d'origine | Modification décidée | Pourquoi |
|---|---|---|---|
| M1 | « Module Registry » + « Solution Registry » nouveaux (tables, version, status…) | **Un seul registre unifié** obtenu par refactor des 3 sources existantes (feature-flags.php + KNOWN_MODULES + metadata.modules) vers une source de vérité unique versionnée en code, `SolutionManifest` étendu (industry, permissions câblées) | La triple source actuelle est la dette n°1 (désyncs répétées). Créer un 4e registre aggraverait le problème |
| M2 | « AI Platform » avec 7 providers (Local, Cloudflare, Gemini, Groq, OpenRouter, OpenAI, Anthropic) | **Router LLM minimal** : 3 providers existants + routage par tâche + fallback + circuit breaker + retry + streaming. Nouveaux providers uniquement sur cas d'usage prouvé (1 à la fois) | L'indépendance fournisseur est atteinte par l'abstraction `LLMClient` (déjà interchangeable) + fallback — pas par le nombre de drivers. Chaque provider = surface de test, coûts, sanitization à vérifier |
| M3 | « Léa » comme agent de la AI Platform | **Léa = pipeline, pas agent** : NL → `BusinessIntent` (LLM, sortie JSON validée par schéma) → validation métier déterministe → `SetupInterviewPlanner`/`SolutionActivator` existants. Aucun tool d'écriture pour l'IA ; le provisioning reste 100 % déterministe | Sécurité : l'IA ne devient jamais source de vérité. Coût : réutilise tout l'existant |
| M4 | « Marketplace » global agrégeant toutes les verticales | **Préparation architecturale sans portail** : conventions d'API publique communes + contrat `PublicOffer` partagé. Le portail unifié est une décision commerciale, pas technique — reportée | Construire un super-marketplace sans volume de tenants publiables = coquille vide |
| M5 | « Memory / Knowledge / RAG » comme couches de la plateforme | **Memory conversationnelle actuelle conservée** ; Knowledge/RAG conditionné à un trigger explicite (vertical avec corpus + pilote client) | pgvector sans cas d'usage = dette d'infra et de sécurité (fuite cross-tenant par similarité) |
| M6 | Modèle « Tenant → Organization → Locations » + Membership | **Conservation du modèle Company → sites/établissements** ; évolution limitée au company-switch en session et à la généralisation des resource_assignments | Le modèle actuel couvre déjà chaînes, multi-sites, multi-campus. Le niveau « groupe » sera ajouté le jour où un client groupe/holding/franchise signe |

## 3. Ce que je REFUSE

| # | Refus | Pourquoi |
|---|---|---|
| R1 | **Nouveau concept Organization / BusinessGroup / Workspace / Membership** | Doublon structurel avec Company + user_employee_links. Dette garantie, migration douloureuse, zéro valeur immédiate |
| R2 | **Réécriture ou changement du mode de tenancy** (retour schema-per-tenant, packages stancl/spatie) | Le mode schema est mort et verrouillé ; le global scope est fail-closed et audité. Risque immense, gain nul |
| R3 | **Intégration immédiate de 4+ providers LLM supplémentaires** | Contraire à « ne pas construire ce dont on n'aura peut-être jamais besoin » ; chaque provider exige sanitization, coûts, quotas, tests |
| R4 | **RAG / pgvector / knowledge base maintenant** | Aucun cas client ; risque de fuite cross-tenant par recherche vectorielle mal scopée ; à refaire le jour du besoin avec le scoping au centre |
| R5 | **Agents autonomes généralistes / « agent ecosystem »** | AgentRunner existe, aucun client ne l'utilise. Industrialiser du dormant = dette |
| R6 | **Moteur d'automation générique (déclencheur+conditions+actions)** | Les événements Laravel suffisent ; un moteur générique est un produit en soi |
| R7 | **L'IA comme source de vérité** (génération SQL métier, écriture directe, provisioning LLM-driven) | Ligne rouge sécurité. L'existant la respecte déjà — toute évolution qui la franchit est refusée par principe |
| R8 | **Expansion vers 14 verticales** (la liste complète de la mission) | La gouvernance existante (FOCUS/ADR-0012, FREEZE 60j) a raison : profondeur des verticales vendues > largeur du catalogue |

## 4. Ce que je REPORTE (avec triggers)

| # | Sujet | Trigger de réactivation |
|---|---|---|
| F1 | Marketplace unifié cross-vertical | ≥ N tenants actifs publiables dans ≥ 2 verticales + décision commerciale marque |
| F2 | Niveau « groupe/holding » au-dessus de Company | Premier client groupe/franchise signé |
| F3 | RAG / Knowledge | Vertical avec corpus documentaire réel + pilote client acceptant le cloud ou provider local |
| F4 | Provider LLM local (vLLM/Ollama) | Client refusant le cloud (le flag `ai_cloud_allowed` l'anticipe déjà) |
| F5 | Agents multi-étapes industrialisés | Un workflow agent utilisé par de vrais utilisateurs via l'AgentRunner actuel |
| F6 | Migration TS admin-dashboard (ADR-0023) | Budget front dédié — ne pas mélanger avec ce programme |
| F7 | Streaming SSE du chat | Avec le router LLM (Phase 3) si effort < 2 j, sinon Phase 5 |

## 5. Risques qui peuvent bloquer le programme

| Risque | Probabilité | Mitigation |
|---|---|---|
| Conflit avec FREEZE_SCOPE_60J / ADR-0012 (le programme peut être perçu comme « largeur ») | Moyenne | Phases 0–1 cadrées comme **consolidation** (réduction de dette), arbitrage explicite avec le owner ; les phases produit (Léa) présentées comme différenciation commerciale mesurable |
| Migration email unique global (pré-requis company-switch) | Moyenne | Phase 3 commence par une étude + POC de migration ; si trop risqué, company-switch limité aux comptes `User` liés (user_employee_links) sans fusion d'emails |
| Régression sur le cœur HR pendant la convergence (cycles d'imports) | Moyenne | Toucher aux cycles par petits pas gardés (la pratique allowlist existe déjà) ; aucune extraction de package tant que cycles > seuil |
| Prod free-tier pendant que la charge IA augmente | Élevée | D3 traité en Phase 0 (décision billing #7649) — non négociable avant toute montée en charge IA |
| Fuite PII IA exploitée avant correctif | Faible mais critique | D2 en toute première issue |
| Bus factor = 1 (mono-owner CODEOWNERS) | Structurel | Ce programme documente tout (issues atomiques, guides agents) — réduit la dépendance |

## 6. Comment vérifier que la transformation apporte de la valeur

| Indicateur | Mesure | Cible |
|---|---|---|
| Dette features | Nombre de sources de vérité à modifier pour ajouter un module | 3 → **1** (Phase 1) |
| Sécurité IA | Tool results sanitisés sur 100 % des branches provider | **100 %** (Phase 0) |
| Fiabilité prod | Tâches planifiées perdues / mois | **0** (Phase 0) |
| Confiance cœur | Tests Planning | 2 → **≥ 30** (Phase 2) |
| Activation verticale | Temps pour provisionner un tenant vertical complet (ex. restaurant) | ≤ 10 min, **1 commande** (existant — à mesurer et garder) |
| Différenciation | Taux de complétion onboarding via Léa vs formulaire | Mesuré en pilote (Phase 4) |
| Adoption IA | Clients avec IA active utilisant ≥ 1 tool/semaine | Baseline à établir Phase 3 |
| Non-régression | Suite de tests + coverage gate 65 % + golden tests paie | **Vert à chaque phase** |
