# GAP ANALYSIS — Existant vs Cible « Business OS / AI Platform »

> Date : 2026-09-26 · Base : `00_AUDIT_ETAT_REEL.md`
> Lecture : pour chaque proposition de la mission, ce qui existe, ce qui manque, et le verdict (REUSE / EXTEND / REFACTOR / CREATE / REJECT / DEFER).

---

## 1. Vision globale « Business OS + AI Platform + Public Commerce »

| Composant proposé | Existe ? | Écart réel | Verdict |
|---|---|---|---|
| Socle Core commun (Identity, Tenant, RBAC, Locations) | **Oui** — Core/Tenant, Core/Auth, sites + resource_assignments | Multi-org session, email unique global | **REUSE + EXTEND** (ciblé) |
| Verticales composables | **Oui** — 9 verticales enregistrées | Convergence (manifests, patterns dupliqués) | **REUSE + REFACTOR** |
| Provisioning Engine | **Oui** — CompanyProvisioningService + SolutionActivator + SetupInterviewPlanner | Unification feature registry, permissions manifests | **REUSE + EXTEND** |
| Public Commerce | **Oui** — marketplace retail, travel-web, vitrines ×3 surfaces | Frontières entre surfaces à arbitrer | **REUSE + REFACTOR** |
| Marketplace multi-tenant | **Partiel** — agrégation inter-tenant par verticale (retail, travel) | Pas de marketplace unifié cross-vertical | **DEFER** (voir §6) |
| AI Platform multi-provider | **Partiel** — 3 providers, 1 driver actif, pas de router | Router minimal, fallback, sécurité PII | **EXTEND** (minimal) |
| Agents IA | **Partiel** — AgentRunner basique, 0 client | Pas de besoin produit prouvé au-delà du chat outillé | **DEFER** |
| Memory / Knowledge / RAG | **Non** | Aucun besoin utilisateur documenté | **DEFER** (trigger défini) |
| Onboarding IA « Léa » | **Partiel** — SetupInterviewPlanner déterministe existe | Interface langage naturel au-dessus du moteur existant | **EXTEND** — la seule vraie nouveauté IA recommandée |

---

## 2. Modèle Tenant / Organization / Location

| Proposition mission | Réalité | Verdict |
|---|---|---|
| Tenant → Organization/Business Group → Locations | Tenant=Company → sites/établissements verticaux. **Pas de niveau « groupe ».** | **REJECT** le nouveau modèle. Le niveau groupe n'est justifié que par les franchises/holdings — **DEFER** jusqu'au premier client groupe réel |
| User → Membership → Organization | `user_employee_links` existe (données) mais session mono-tenant | **EXTEND** : company-switch en session (Phase 3, après étude email unique) |
| Role → Scope (par établissement) | `employee_resource_assignments` (view/operate/manage) | **EXTEND** : généraliser la consommation par les policies (6/~40 aujourd'hui) |
| Workspace comme produit du provisioning | La company provisionnée + feature flags + onboarding **est** le workspace | **REJECT** le nouveau concept ; renommer dans le vocabulaire produit si besoin |

---

## 3. Registres (Module Registry / Solution Registry)

| Capacité demandée | Existant | Écart | Verdict |
|---|---|---|---|
| module_id, version, status, dependencies, capabilities | `KNOWN_MODULES` + feature-flags.php + manifests (partiel) | Pas de registry unifié versionné | **REFACTOR** : une seule source (registre PHP versionné + cache), les 3 sources actuelles deviennent des vues |
| solution_id, industry, modules, roles, permissions, workflows, navigation | `SolutionManifest` (code, maturity, modules, sensitiveData, permissions non câblées) | Pas d'industry, permissions non enforceées, navigation absente | **EXTEND** le manifest existant |
| Activation déterministe | `SolutionActivator` (idempotent, fail-closed, audité) | Rien de structurel | **REUSE** |
| Données d'amorçage | `DemoDataKit`, `SectorTemplateService` | — | **REUSE** |

---

## 4. AI Platform

| Proposition mission | Existant | Verdict |
|---|---|---|
| AI Gateway | `AIGatewayController` + middlewares (feature check, tenant injector, rate limiter, quotas) | **REUSE** — le gateway HTTP existe ; il manque le routage modèle |
| Model Router | Inexistant (1 driver actif) | **CREATE — minimal** : router interne (tâche → provider) + fallback + circuit breaker. Pas un produit |
| Providers : OpenAI, Anthropic, Groq | **Intégrés** | **REUSE** |
| Providers : Gemini, OpenRouter, Cloudflare, vLLM, local | Absents | **DEFER** — ajouter 1 provider uniquement si un cas d'usage l'exige (coût/latence/souveraineté). Le flag `ai_cloud_allowed=OFF` n'a aujourd'hui que `fake` comme non-cloud : un provider local n'est justifié que par un client qui refuse le cloud |
| Tool calling sécurisé | Mature (matrice fail-closed, confirmation humaine, parité services canoniques) | **REUSE** + corriger D2/D11 |
| Memory | Conversation JSON 50 messages | **REUSE** — suffisant tant que pas de cas RAG |
| Knowledge / RAG / pgvector | Inexistant | **DEFER** — trigger : un vertical avec corpus documentaire réel (ex. protocoles pharma, règlements scolaires) ET un pilote client |
| Agents autonomes | AgentRunner basique sans client | **DEFER** — ne pas industrialiser sans use case |
| Streaming / retry / circuit breaker | Absents | **CREATE — faible effort, forte valeur UX/robustesse** |
| « Léa » onboarding NL | SetupInterviewPlanner déterministe + SolutionActivator | **EXTEND** : NL → Business Intent (LLM) → validation déterministe → plan existant. L'IA propose, le moteur décide |

### Séparation des concepts IA (frontières recommandées)

La séparation proposée par la mission est correcte et **déjà respectée** par le code existant. Formalisation :

- **Model** = `LLMClient` + drivers (inférence, interchangeable).
- **Agent** = configuration (prompt + tools autorisés + politiques) exécutée par Orchestrator/AgentRunner. *Aujourd'hui : un seul agent implicite. Léa serait le 2e — introduire le concept `AgentDefinition` seulement à ce moment-là (YAGNI avant).*
- **Tool** = `AIToolDefinition` (typé, autorisé par matrice, audité, tenant-aware). Handlers fixes — jamais de SQL généré.
- **Workflow** = `app/AI/Workflows` (déterministe, SQL) — à ne pas confondre avec les automatisations.
- **Automation** = événements Laravel + listeners (déclencheur + action). *Pas de moteur conditions générique — ne pas en créer un sans besoin.*
- **Memory** = `ai_conversations` (contexte de session).
- **Knowledge** = *n'existe pas — futur RAG éventuel, table séparée des données métier.*
- **Business Data** = tables métier scopées `company_id` — seule source de vérité ; l'IA n'y accède que via tools.
- **Conversation** = interaction utilisateur (chat), distincte de la memory qui la persiste.

**Correction à la proposition** : ne pas créer « Automation » comme couche IA. Les automatisations sont du Laravel événementiel classique ; les sortir du périmètre IA évite la confusion déterministe/probabiliste.

---

## 5. Sécurité by design

| Exigence | État | Écart |
|---|---|---|
| Tenant isolation | Global scope fail-closed + forcing company_id | OK — garder les gardes |
| IA sans accès DB libre | Handlers fixes, pas de SQL généré | OK |
| IA sans contournement RBAC | Matrice fail-closed, re-vérification à la confirmation | OK |
| Cross-tenant IA | Tenant depuis session uniquement | OK |
| Actions sans autorisation | Write tools whitelistés + confirmation humaine | OK |
| Tools typés/audités/idempotents | Typés ✓ audités ✓ ; idempotence métier absente des write-handlers | **Gap mineur** — clés d'idempotence sur writes (P3) |
| PII vers cloud | Sanitizer incomplet (branche Claude, tool_result tableaux) | **Gap critique D2** |
| Rétention logs IA | Absente | **Gap moyen** |

---

## 6. Public Commerce / Marketplace

| Proposition | Réalité | Verdict |
|---|---|---|
| Tenant → Commerce (catalog, availability, orders, reservations, payments, storefront) | Existe **par verticale** : Retail (marketplace+POS), Travel (billetterie), Restaurant, Hospitality (dispo publique), Showcase (vitrine), Catalog (B2B→CRM) | **REUSE** — ne pas créer de « module Commerce » transverse |
| Marketplace agrégeant plusieurs tenants | Existe pour retail (`front/marketplace`) et travel (`front/travel-web`) — sans accès anarchique (API publiques dédiées, tokens boutique, idempotence) | **REUSE** le pattern |
| Marketplace unifié cross-vertical (hôtels + restaurants + retail dans un seul portail) | Inexistant | **DEFER** — décision commerciale avant d'être technique. Trigger : volume de tenants actifs publiables dans ≥2 verticales + stratégie marque. Préparer architecturalement : conventions d'API publique communes (`/public/<vertical>/marketplace/*`), contrat `PublicOffer` partagé — **sans construire le portail** |

---

## 7. Synthèse des écarts

**Ce qui manque vraiment (à construire)** :
1. Registre unifié features/modules/solutions (refactor de l'existant). 
2. Router LLM minimal + fallback + retry + circuit breaker (+ correctif PII Claude).
3. « Léa » : couche NL au-dessus du provisioning déterministe existant.
4. Company-switch en session (si multi-org confirmé) — précédé de l'étude email unique.
5. Clés d'idempotence métier sur les write-tools IA.
6. Conventions d'API publique commune (préparation marketplace, sans portail).

**Ce qui existe et ne doit PAS être reconstruit** : tenancy, RBAC 3 couches, provisioning, solution registry, activation, onboarding déterministe, tools IA + politiques, audit IA, marketplaces verticales, spec-kit, gouvernance BC.

**Ce qui ne doit PAS être construit (maintenant ou jamais)** : Organization/BusinessGroup/Workspace/Membership ; schema-per-tenant ; 10 providers LLM ; RAG/pgvector sans cas client ; moteur d'automation générique ; agents autonomes généralistes ; super-marketplace unifié ; ERP universel (14 verticales de la mission — la gouvernance FOCUS/ADR-0012 a raison : profondeur > largeur).
