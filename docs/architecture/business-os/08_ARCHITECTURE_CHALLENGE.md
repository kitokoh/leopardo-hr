# 08 — ARCHITECTURE CHALLENGE (Seconde passe)

> Date : 2026-09-26 · Auteur : PM/Architecte · Objet : revue critique obligatoire du premier passage (`00`–`07`)
> Méthode : re-vérification du code sur chaque conclusion sensible (provisioning, clients LLM, modèle Company, commerce public, dettes cachées, 4 verticales candidates). Ce document **prime** sur `00`–`07` en cas de contradiction.
> Résultat global : le diagnostic central est **confirmé** (consolider, ne pas transformer) mais **7 conclusions corrigées**, **2 dettes CRITICAL nouvelles**, **1 issue supprimée**, **7 modifiées**, **7 nouvelles**.

---

## 1. Corrections du premier audit (ce qui change)

| # | Conclusion du 1er passage | Verdict après re-vérification | Correction |
|---|---|---|---|
| C-1 | « Leopardo est à ~70 % un Business OS » | **Invalidée comme chiffre** | Remplacé par une matrice de maturité 25 domaines (§2). La moyenne simple ressort à ~68 % mais un chiffre unique est trompeur : il est **retiré du discours stratégique** au profit de la maturité par domaine |
| C-2 | « Léa : NL → BusinessIntent → mapping capabilities→solutions (table nouvelle) » | **Trop complexe — sur-architecture de ma part** | Le mapping déterministe **existe déjà** : `SetupInterviewPlanner::SECTOR_SOLUTIONS` + `PRIORITY_TOOLS`. Léa doit mapper le langage naturel vers **les réponses de l'interview existant** (sector, priorities, company_type, team_size, premises) — pas vers un nouveau modèle de capabilities. Économie : 1 table de mapping + 1 couche de validation en moins |
| C-3 | « Léa utilisera une sortie JSON schema strict » | **Techniquement impossible aujourd'hui** | `LLMClient::chat(messages, tools)` n'a **aucun support structured output** (vérifié : zéro `response_format`/`json_schema` dans `app/AI`, clients OpenAI/Claude/Groq = HTTP brut 30 s sans retry). Design corrigé : JSON par prompt + **validation déterministe** (qui était de toute façon la vraie garantie) ; support `response_format` ajouté en option à l'issue résilience |
| C-4 | « ModelRouter : routage par tâche + fallback + circuit breaker » | **Sur-dimensionné** | Le routage par tâche est du YAGNI (aucun cas d'usage multi-modèle aujourd'hui). Réduit à un **décorateur `ResilientLLMClient`** : retry/backoff + chaîne de fallback + circuit breaker. L'interface `LLMClient` existante suffit — le décorateur l'implémente, zéro changement consommateur |
| C-5 | « PublicOfferContract (contrat métier commun) » | **Prématuré — corrigé** | Vérifié : aucun contrat commun n'existe, et les domaines divergent trop (sièges datés vs panier vs date-range vs commande immédiate). Ce qui est réellement dupliqué à l'identique, ×4 à ×6, c'est le **plumbing** : résolution tenant public fail-closed (6 implémentations), idempotence invitée (4), secret de suivi hashé (3), buckets throttle (enregistrés en double — bug). On mutualise le plumbing, pas le modèle métier |
| C-6 | « BOS-033 streaming SSE du chat (si <2 j) » | **Supprimée** | Aucun client ne consomme même le flux confirm/reject aujourd'hui ; streamer vers des capacités dormantes est absurde. Classé F7 définitivement, hors plan |
| C-7 | Phase 0 = 5 issues | **Incomplète** | La chasse aux dettes a trouvé **2 CRITICAL en production** (double scheduler avec commandes contradictoires ; alertes d'expiration de contrat jamais envoyées) + une race condition sur les quotas IA. Phase 0 passe à 8 issues |

## 2. Matrice de maturité réévaluée (remplace le « 70 % »)

Échelle maturité : 0 absent → 5 production durcie. Qualité/risque : appréciation code relu.

| Domaine | État | Qualité | Risque résiduel | Maturité /5 |
|---|---|---|---|---|
| Core / Tenancy | Existant | Haute (fail-closed, audité) | Faible (vestiges schema) | 4.5 |
| RBAC | Existant | Haute (3 couches) | Moyen (enums SQL, scoping partiel ~6/40 policies) | 4 |
| Multi-company | Partiel | Données OK (`user_employee_links`), session mono-tenant | Bloqué par email unique global | 2 |
| Locations / établissements | Existant | Bonne (sites + 6 types verticaux) | Faible | 4 |
| Module system | Existant | Bonne (32/32 DDD, gardes CI) | Cycles cœur, Application absente ×8 | 3.5 |
| Solution system | Existant | Moyenne (manifests dupliqués, permissions non câblées) | Désync historique répétée | 3 |
| Provisioning | Existant | Haute (transaction, fail-closed, anti-race slug, audit) | Amorçage inégal par verticale | 4 |
| Vertical activation | Partiel | **Très inégale** : Travel complète, Restaurant bonne, Edu = flag seul, Retail = **aucun manifest** | Un tenant Edu/Retail activé atterrit sur un workspace vide | 2.5 |
| AI orchestration | Existant | Haute | God class IntentEngine (1140 l.) | 4 |
| AI security | Existant | Bonne **sauf** fuite PII Claude + race quota | 2 correctifs Phase 0 | 3.5 |
| AI tools | Existant | Haute (matrice, confirmation, parité services) | Idempotence métier absente | 4 |
| AI providers | Partiel | 3 clients mais : pas de retry, pas de fallback, pas de circuit breaker, **pas de structured output**, pas de streaming | Panne provider = panne assistant | 2.5 |
| Commerce (par verticale) | Existant | Bonne (4 verticales transactionnelles publiques) | Plumbing dupliqué ×4-6 | 4 |
| Marketplace | Partiel | 2 verticales cross-tenant (retail, travel), disciplinées | Pas unifié (par design assumé) | 3 |
| Onboarding | Existant | Bonne (checklist, interview, demo kit) | Couvre mal les verticales non-Travel | 3.5 |
| Public storefront | Existant | Bonne (5 surfaces publiques) | Frontières web/marketplace floues | 4 |
| Billing | Existant | Moyenne (abonnement ×3 représentations) | 402 worker non provisionné | 3 |
| Accounting | Existant | Bonne (46 tests, production-ready) | — | 4 |
| Workflow | Partiel | Workflows IA déterministes ; pas de moteur métier générique (choix assumé) | Nommage trompeur (« AI workflows » sans IA) | 2.5 |
| Notifications | Existant | Moyenne (frontière Communication non tranchée) | Threads dupliqués | 3.5 |
| Observability | Existant | Bonne (Sentry, RequestId, logs structurés, supervision queue) | Pas de métriques produit IA par provider | 3.5 |
| Testing | Partiel | **Très inégal** : Payroll 106/Travel 114 vs Planning 2, Fleet 1, Retail 9 | Domaines critiques sous-couverts | 3 |
| CI/CD | Existant | Haute (75 workflows, gardes, coverage gate) | Saturation file CI connue | 4 |
| **Production reliability** | **Partiel** | **Faible** : free-tier éphémère + **double scheduler contradictoire (nouveau, CRITICAL)** + alerte contrats no-op | Perte de crons, double-exécutions | 1.5 |
| Documentation | Existant | Bonne mais par endroits obsolète (≥5 modules) | Drift doc/code | 3.5 |

**Moyenne ≈ 3.4/5 (~68 %)** — cohérente avec l'intuition initiale, mais la vérité est dans la dispersion : **socle 4-4.5/5 ; chaîne d'activation verticale et fiabilité prod 1.5-2.5/5**. Le maillon faible du Business OS n'est pas la vision, c'est la **fiabilité production** et l'**inégalité d'amorçage des verticales**.

## 3. Re-vérification des « ne pas créer » (doublons)

| Concept refusé | Équivalent existant | Correspondance | Limites réelles de l'existant | Évolution nécessaire | Décision confirmée ? |
|---|---|---|---|---|---|
| Organization / BusinessGroup | `Company` (+`user_employee_links`) | Exacte au niveau entité | Pas de niveau groupe ; pas de consolidation cross-company | **Uniquement** quand un client groupe signe (F2) | ✅ Oui |
| Workspace | Company provisionnée + features + onboarding | Exacte (le workspace = le tenant configuré) | Amorçage inégal par verticale | Combler l'amorçage, pas créer le concept | ✅ Oui |
| Membership | `employees` (rôle) + `user_employee_links` (lien compte↔employé) + `employee_resource_assignments` (scope) | Quasi exacte | Lien données sans session unifiée | Company-switch (BOS-036 en ADR d'abord) | ✅ Oui |
| Nouveau Module Registry | `feature-flags.php` + `KNOWN_MODULES` + `metadata.modules` (3 sources) | **Le problème n'est PAS résolu** — « un similaire existe » mais en 3 exemplaires désynchronisés | Désyncs documentées (#7220, #7235, #7432, #7785, #7976) | **Unifier** (BOS-011) — c'est bien de la consolidation, pas une création | ✅ Oui, avec la nuance : l'existant est défaillant, l'unification est justifiée |
| Nouveau Solution Registry | `Core/Solutions` (Catalogue+Activator+Manifest) | Exacte | Manifests legacy dupliqués, permissions non câblées, pas d'`industry`/`capabilities` | Étendre le contrat (BOS-013/014) | ✅ Oui |
| Nouveau Provisioning Engine | `CompanyProvisioningService` + `SolutionActivator` | Exacte (voir §5) | Amorçage par verticale inégal | Complétude d'activation (BOS-009, BOS-016) | ✅ Oui |
| AI Platform parallèle | `app/AI` complet | Exacte | Résilience provider absente | Décorateur (BOS-031 modifiée) | ✅ Oui |

## 4. Test critique du modèle Company (cas A–H, vérifiés dans le code)

| Cas | Verdict | Preuve / explication |
|---|---|---|
| **A** — 1 personne, 1 entreprise | ✅ **SUPPORTED** | Chemin nominal : trial → company + manager principal |
| **B** — 1 personne possède plusieurs entreprises | 🟡 **PARTIALLY** | `user_employee_links` (UNIQUE user+company) permet le lien en données ; mais `employees.email` **unique global** (`2026_04_17_000105`) + `user_lookups.email` PK + aucun switch de session ⇒ en pratique : 2e email = 2e compte. Le 2e trial du même email est explicitement refusé (réponse uniforme, `SelfServiceTrialController`) |
| **C** — 1 entreprise, plusieurs établissements | ✅ **SUPPORTED** | `sites` (géofence) + établissements verticaux (branch, property, campus, station…) |
| **D** — 1 utilisateur travaille dans plusieurs entreprises | 🟡 **PARTIALLY** | Même mécanique que B : comptes employés distincts par company. Fonctionne mais sans UX unifiée |
| **E** — 1 utilisateur travaille dans plusieurs établissements | ✅ **SUPPORTED** (avec réserve) | `employees.site_id` = site d'attache + `employee_resource_assignments` = accès multi-établissements (view/operate/manage). Réserve : `manager_role` reste company-wide ; seules ~6 policies consomment le scoping |
| **F** — chaîne de 30 restaurants | ✅ **SUPPORTED** | `RestaurantBranch` ×N, `resource_type=restaurant_branch`, test dédié `RestaurantResourceScopedRbacTest` |
| **G** — holding (Hotel A/B + Restaurant A + Retail A) | 🟡 **PARTIALLY** | Deux sous-cas : **une seule entité légale** → une Company peut activer **plusieurs solutions** (`activateRequestedSolutions` boucle, feature flags cumulables) ⇒ multi-vertical mono-tenant SUPPORTED ; **plusieurs entités légales** → companies séparées, **aucune consolidation groupe** (reporting holding, partage) ⇒ NOT SUPPORTED. Le cas réel dépendra du client ; le niveau groupe reste F2 |
| **H** — cabinet comptable gérant des entreprises clientes | ❌ **NOT SUPPORTED** | Aucun accès cross-tenant pour un acteur externe ; `manager_role=comptable` est intra-company ; le cabinet aurait besoin d'un compte par cliente ou d'une console type `SuperAdmin` restreinte. **C'est le cas multi-org le plus demandé du marché cible (PME + fiduciaires)** — il renforce BOS-036 (ADR multi-org) et devra y être explicitement traité |

**Conclusion modèle** : Company tient A, C, E, F et G-mono-entité. Les manques (B, D, G-multi-entités, H) convergent tous vers **un seul chantier** : multi-org session + acteur externe — précédé de l'ADR (email unique). Aucune création de concept n'est nécessaire avant cette étude.

## 5. Re-validation du provisioning (vérifié ligne par ligne)

`CompanyProvisioningService::provisionSharedCompany()` (247 l.) + `SolutionActivator` (129 l.) :

| Exigence | État vérifié |
|---|---|
| Transactions | ✅ `DB::transaction` englobe company + manager + template + onboarding + activations ; savepoint anti-race slug (23505 → retry 1×) |
| Idempotence | 🟡 Provisioning d'une nouvelle company : non idempotent **par nature** (création), mais le chemin trial l'est (`ProvisionGuidedTrial` par email, claim anti double-provisioning `VerifyTrialSignup:67-72`) ; `SolutionActivator` idempotent (no-op si actif) |
| Rollback | ✅ rollback transactionnel sur échec ; 🟡 dépendances solution manquantes → refus tracé **sans** rollback (choix assumé : tenant créé, activation manuelle possible) |
| Audit | ✅ `AuditLog` `solution.activated` + `solution.dependencies_activated`, logs provisioning |
| Gestion d'erreurs | ✅ fail-closed (pays, allowlist solutions avant écriture), 422 explicites |
| Dépendances | ✅ modules requis activés avant la solution (`activateWithDependencies`) |
| Activation de modules | ✅ via feature flags (source unique à venir BOS-011) |
| Création de rôles | ❌ **aucun rôle/persona vertical provisionné** (manager principal seul ; `travel.manage/agent/checkin` déclarés mais personne ne les porte) |
| Création de permissions | ❌ `SolutionManifest::permissions()` déclaré, **jamais installé** |
| Configuration initiale | 🟡 inégale : Travel = géo 223 pays seedée ; Restaurant = référentiel (TVA, catégories) ; **Edu = rien** ; **Retail = rien (pas même un manifest)** |
| Migrations | ✅ N/A par design (schéma partagé — aucune migration par tenant) |
| Données démo | 🟡 DemoDataKit : Travel partiel (pas de trajet vendable), Restaurant riche, Edu/Retail absents |
| Multi-verticales | ✅ plusieurs solutions activables sur un tenant (boucle + flags cumulables) |

**Réponse à la question** : *le provisioning actuel peut-il construire automatiquement un workspace métier depuis une définition structurée ?* — **Oui pour la structure** (tenant + modules + solutions + dépendances + onboarding + audit, transactionnel), **non pour l'habillage** : il manque (1) l'installation des permissions/rôles du manifest, (2) l'amorçage métier pour Edu/Retail (et le manifest Retail lui-même), (3) la prise en compte de paramètres d'intent (nombre de sites, présences) au-delà du QCM existant. Ce sont exactement BOS-013, BOS-009, BOS-016.

## 6. Test conceptuel « Léa » — le pipeline trou par trou

Scénario mission : *« Je dirige une école de 600 élèves avec 40 enseignants. Je veux gérer les classes, les paiements, les enseignants et les parents. »*

| Étape du pipeline | Existe ? | Trou |
|---|---|---|
| Business Intent (NL → structuré) | ❌ | `SetupInterviewPlanner.FREE_TEXT` ne produit **aucune** activation (vérifié : le texte libre n'est jamais lu par `plan()`). Aucun appel LLM dans l'onboarding |
| Industry | ✅ | `sector='education'` ∈ allowlist de l'interview |
| Business Type | 🟡 | `company_type` (solo/team) existe ; pas de sous-type « school » — acceptable, non bloquant |
| Capabilities | 🟡 | `priorities` (6 choix) + `team_size` + `premises` — « classes/parents/fees » n'ont pas de case ; le mapping capabilities fines n'existe pas |
| Solution | ✅ | `SECTOR_SOLUTIONS['education'] => 'edumanager'` (mapping déterministe **déjà là**) |
| Modules | ✅ | Manifest Edu : required `[rh, documents, notifications]` — activés par `activateWithDependencies` |
| Configuration | ❌ | Activation Edu = **flag seul** : campus vide, année scolaire vide, aucune scolarité |
| Roles | ❌ | Aucun rôle enseignant/parent provisionné |
| Permissions | ❌ | `edu.admin/teacher/guardian/fees` déclarés, non installés |
| Provisioning Plan | ✅ | `plan()` de l'interview → `{solutions, tools}` exécuté par `CompleteSetupInterview` (testé E2E pour travel) |
| Workspace utilisable | 🟡 | Structurel oui ; un directeur d'école atterrit sur un EduManager vide à tout créer |

**Design Léa corrigé (C-2/C-3)** : le LLM produit les **réponses de l'interview** (`{company_type, team_size, sector, premises, priorities[]}` — schéma fermé déjà existant) à partir du texte libre ; validation = l'allowlist existante de `SetupInterviewPlanner` (fail-closed par construction) ; exécution = `CompleteSetupInterview` **inchangé**. Trous résiduels : JSON fiable sans structured output (mitigation : prompt JSON + validation + 1 retry + fallback interview), extension éventuelle des `priorities` (classes/parents/fees) — à spécifier dans BOS-040. **Aucune nouvelle couche de mapping. Aucune écriture IA.**

## 7. Vertical pilote : **TravelAgency** (choix justifié)

Comparaison chiffrée des 4 candidates (code vérifié) :

| Critère | Travel | Restaurant | Retail | Edu |
|---|---|---|---|---|
| Manifest catalogue | ✅ conforme | ✅ + doublon local non conforme | ❌ **absent** | ✅ |
| Survey | ❌ | ✅ (seul) | ❌ | ❌ |
| Listener activation + seed | ✅ géo 223 pays | ✅ référentiel | ❌ | ❌ (flag seul) |
| DemoDataKit | ✅ partiel | ✅ riche | ❌ | ❌ |
| Tests | **114** | 73 | 9 | 41 |
| E2E provisioning | ✅ interview | ✅ signup | ❌ | ❌ |
| Checklist onboarding métier | ✅ (seul) | ❌ | ❌ | ❌ |
| Commerce public | ✅ marketplace + e-billets + comptes clients | ✅ shop + kiosk | ✅ market | ❌ (hors scope) |
| Fronts | 3 (2 dédiés) | web | 2 | web |

**TravelAgency** est la seule verticale où description→activation→amorçage→checklist→usage public est **intégralement câblé et testé** (`SetupInterviewTravelVerticalTest`, `TravelAgencyActivationPersistsFlagTest`).

**Scénario complet démontré (gaps en gras)** : *« Je gère une agence de voyages avec 2 bureaux, je veux vendre des billets en ligne. »* → **[GAP 1 : rien ne traduit le NL en `sector=travel`]** → interview answers `{sector: travel, premises: multiple, priorities: […]}` → `plan()` → solution `travelagency` → `SolutionActivator` (modules requis + flag + audit) → géo seedée (223 pays) + checklist `travel_setup_network → travel_first_trip → travel_first_sale` → workspace agence → **[GAP 2 : aucun rôle/persona agence seedé — le manager doit tout créer]** → **[GAP 3 : demo kit sans trajet vendable — la checklist "premier trajet" n'a pas de raccourci]** → usage : 252 endpoints + marketplace publique + e-billets → **[GAP 4 : survey travel 404 — pas de mesure de conversion]** → **[GAP 5 : pas d'E2E trial-signup travel (seul l'E2E interview existe, qui n'asserte pas le seed géo)]** → **[GAP 6 : app guichet Flutter = 21 fichiers/3 tests]**.

Ces 6 gaps (corrigés de l'anti-pattern manifest via BOS-014) forment la nouvelle issue **BOS-009**.

## 8. Re-validation AI (router maintenant ou pas ?)

| Capacité | État vérifié |
|---|---|
| Abstraction provider | ✅ `LLMClient` (interface stable, 3 implémentations + Fake) |
| Timeout | ✅ 30 s partout |
| Retry / backoff | ❌ **aucun** |
| Fallback | ❌ driver unique (`AI_LLM_DRIVER`) |
| Circuit breaker | ❌ |
| Quotas | ✅ mais **race check-then-increment** (`AIRateLimiter:71-76` — nouveau M1) |
| Budgets | ✅ `TokenBudgetGuard` 3 niveaux fail-closed |
| Observabilité | 🟡 audit + analytics basiques ; coûts sur tarifs en dur |
| Prompt management | 🟡 1 fichier md, pas de versioning par agent |
| Tool calling | ✅ mature |
| Structured output | ❌ **absent de l'interface et des 3 clients** |
| Sécurité / tenant isolation | ✅ (sauf fuite Claude, BOS-001) |

**Décision** : l'abstraction existante **ne suffit plus** dès que Léa met le LLM sur le chemin critique du signup (une panne Groq = inscription dégradée). Mais le besoin réel = **résilience**, pas routage. D'où C-4 : `ResilientLLMClient` décorateur (retry 2× backoff, chaîne fallback config, circuit breaker par provider, respect `AiCloudPolicy` par candidat) + externalisation des tarifs + support optionnel `response_format`. **Confirmé pour Phase 3, en version réduite.** Le routage par tâche est supprimé du plan (YAGNI).

## 9. RAG / Knowledge / Memory — fondation suffisante pour demain ?

| Concept | Aujourd'hui | Prêt pour demain ? |
|---|---|---|
| Business Data | Tables scopées `company_id` | ✅ (invariant à préserver) |
| Conversation | `ai_conversations` (JSON, 50 msgs) | ✅ suffisant |
| Memory (long terme) | ❌ | Aucun obstacle ; à créer **avec** son cas d'usage |
| Knowledge / documents | `Cabinet` (GED : documents, dossiers, partages, audit) existe | ✅ fondation documentaire réelle ; PostgreSQL 16 (pgvector disponible sur Neon) ; rien de construit aujourd'hui n'en empêche l'ajout |

**Règle ajoutée (sans code)** — toute future table knowledge/embeddings devra naître avec : `company_id` + `BelongsToCompany`, ACL au centre (jamais de recherche vectorielle cross-scope), et rétention documentée. C'est une **ligne de la constitution spec-kit** à ajouter via BOS-040, pas une implémentation. RAG reste **DEFER (F3)** — confirmé.

## 10. Public Commerce — abstraction justifiée ou non

Vérifié (détail §1 C-5) : **aucun contrat `PublicOffer`** ; 5 surfaces publiques disciplinées sous un pattern copié. Invariants communs réels : routes sans Sanctum + throttle IP ; tenant résolu par slug/token → `withinTenant` + 404 uniforme ; opt-in + feature flag + company saine ; DTO strict (montants `*_minor`, prix relus serveur) ; écriture invitée idempotente + référence non énumérable. Divergences majeures : availability (4 modèles), cycle de vie (4 machines à états), preuve de suivi (restaurant = le plus faible : réf+slug seulement).

**Décision corrigée** : abstraction commune **uniquement sur le plumbing prouvé dupliqué** — `PublicTenantResolver` (6→1), `IdempotentGuestWrite` (4→1), `TrackingSecretService` (3→1), buckets throttle (dédoublonner le `shop-public` enregistré 2×) + conventions documentées. **Pas de contrat métier PublicOffer** — il serait une coquille vide sans consommateur commun. Renforcer aussi le suivi restaurant (secret hashé comme les 3 autres) — ajouté au scope de BOS-050 modifiée.

## 11. Dettes cachées — nouvelles trouvailles (2e passe)

| Gravité | Dette (preuve) |
|---|---|
| 🔴 **CRITICAL** | **Double scheduler** chargé à la fois par `bootstrap/app.php:55-126` et `routes/console.php:147-302` : 8 commandes planifiées en double avec horaires/paramètres contradictoires — `leave:accrue` daily **et** monthly (double acquisition de congés possible), `billing:generate-invoices` 02:00 **et** 03:00, `attendance:auto-close` dont une version **sans** `withoutOverlapping`, et **deux commandes concurrentes** expirent les mêmes bookings Travel en publiant **deux événements différents** (`travel.booking.expired.v1` vs `cancelled.v1`) |
| 🔴 **CRITICAL** | `contracts:alert-expiring` est une **coquille vide** (`AlertExpiringContracts.php` : description « Notify… 30/15/7 days » mais le corps ne fait que `Log::info` — **aucune notification n'est jamais envoyée**) + noms complets d'employés (PII) dans les logs, 2×/jour (à cause du doublon ci-dessus) |
| 🟠 HIGH | Cluster migrations Travel dupliquées + `down()` destructeur (droppe des colonnes créées par une autre migration) ; `schemaTableExists()` transforme toute création dupliquée en **no-op silencieux dans 337/416 migrations** = fabrique de drift invisible |
| 🟠 HIGH | God classes : `IntentEngine` 1140 l. (25 handlers inline), `PaySlipValueCalculator` 1051 l., `KioskController` 1004 l. (jonglage `SET search_path` en SQL brut), `Employee` 826 l./45 méthodes/17 relations |
| 🟠 HIGH | Logique métier en controllers : 165 controllers avec `$request->validate()` inline vs 488 FormRequests ; **1151 `response()->json()` vs 67 JsonResources** (contrats API fragiles) |
| 🟠 HIGH | PII (emails prospects) en clair dans les logs applicatifs (`SelfServiceTrialController:155,190,225,353`) |
| 🟠 HIGH | 9 listeners synchrones à effets de bord externes (reçu PDF facture envoyé par mail **dans** la requête — `SendInvoicePaymentReceipt` ; commission billing synchrone) |
| 🟡 MEDIUM | Race quota IA : check-then-increment sans verrou (`AIRateLimiter`) — dépassement de quota = coût provider direct |
| 🟡 MEDIUM | Jobs : **3/37** seulement `ShouldBeUnique` ; exports doublonnables par double-clic ; `GenerateDocumentPdf` sans `$tries` |
| 🟡 MEDIUM | 11 090 lignes de baseline PHPStan (5 configs) — dette de typage étouffée |
| 🟡 MEDIUM | Migration de données via modèle Eloquent chunké sans transaction (`encrypt_existing_sensitive_data`) — non rejouable |
| ⚪ LOW | Garde morte `method_exists($this,'withoutOverlapping')` ; drift pubspec inter-apps (dio, riverpod, secure_storage) ; migration enum marketing en double énoncé |

## 12. Re-validation des 26 issues

| Issue | Décision | Motif |
|---|---|---|
| BOS-001 PII Claude | **KEEP** | Confirmée CRITICAL, première à merger |
| BOS-002 rétention logs IA | **MODIFY** | Élargie : hygiène PII de **tous** les logs (incl. emails prospects `SelfServiceTrialController`, noms employés `AlertExpiringContracts`) — renommée « Confidentialité logs & rétention IA » |
| BOS-003 workers prod | **KEEP** | Toujours bloquée par décision budget owner ; ajouter : dépend de BOS-006 (il faut savoir **ce qui** tourne avant de le fiabiliser) |
| BOS-004 write-tool legacy | **KEEP** | Inchangée |
| BOS-005 nettoyage schema | **MODIFY** | Ajoute au scope : le jonglage `SET search_path` manuel de `KioskController` (SQL brut concaténé) et `PlatformCompanyHealthService` |
| BOS-010 spec registre | **KEEP** | Inchangée |
| BOS-011 ModuleRegistry | **KEEP** | Inchangée — confirmée cœur du chemin critique |
| BOS-012 consolidation données | **KEEP** | Inchangée |
| BOS-013 Manifest v2 | **MODIFY** | Scope ajusté au design Léa simplifié : `industry` + enforcement `permissions()` ; **pas** de champ `capabilities` nouveau (le mapping reste `SECTOR_SOLUTIONS`/`PRIORITY_TOOLS`, éventuellement étendu dans BOS-040) |
| BOS-014 manifests legacy | **KEEP** | Renforcée : le manifest local RestaurantManager non conforme **et non enregistré** est l'anti-pattern #7220-bis vivant — à verrouiller avant tout pilote |
| BOS-015 renommage Feature Registry | **KEEP** | Inchangée |
| BOS-020 outbox unifié | **MODIFY** | Descopée : socle + **1** module migré en référence (les 3 autres = issues filles ultérieures). Le pattern marche partout ; l'urgence est d'arrêter la croissance de la duplication, pas de migrer 4 modules d'un coup |
| BOS-021 webhooks unifiés | **KEEP** | Inchangée (suit BOS-020) |
| BOS-022 tests Planning | **KEEP** | Inchangée |
| BOS-023 cycles HR | **MODIFY** | Objectif reformulé : éliminer les **cycles** (HR↔Attendance, Planning↔Payroll…) via contrats Shared — cible : 0 cycle nouveau + allowlist ≤ 35 (indicateur, pas fin en soi) |
| BOS-024 Application layer ×7 | **KEEP** | Inchangée (parallélisation sûre confirmée) |
| BOS-025 Notif/Comm | **KEEP** | Inchangée |
| BOS-026 façades Growth/Absence | **KEEP** | Inchangée |
| BOS-027 abonnement ×3 | **DEFER** | Aucune anomalie fonctionnelle constatée ; reclassée SHOULD (fin de Phase 2, si capacité) |
| BOS-030 spec router | **MODIFY** | Devient spec « résilience LLM » (décorateur) — routage par tâche supprimé |
| BOS-031 ModelRouter | **MODIFY** | → `ResilientLLMClient` : retry+backoff, chaîne fallback, circuit breaker, tarifs en config, respect AiCloudPolicy par candidat, **`response_format` optionnel** (prépare Léa) |
| BOS-032 idempotence writes | **KEEP** | Ajout : évaluer le passage de `PendingActionStore` en table DB |
| BOS-033 streaming SSE | **DELETE** | Capacités dormantes d'abord (BOS-035) ; streaming classé F7 hors plan |
| BOS-034 anti-injection | **KEEP** | Inchangée |
| BOS-035 assistant web | **KEEP** | Montée en priorité produit : c'est la seule issue « adoption IA » — conditionne la mesure de valeur de tout le reste |
| BOS-036 ADR multi-org | **KEEP** | Renforcée par les cas B/D/G/H (§4) — le cas **cabinet comptable (H)** doit être un scénario explicite de l'ADR |
| BOS-040 spec Léa | **MODIFY** | Design simplifié : NL → **réponses d'interview existantes** → validation par allowlist existante → `CompleteSetupInterview` inchangé ; ajouter à la constitution la règle knowledge (§9) |
| BOS-041 BusinessIntent | **MODIFY** | Devient `InterviewAnswerExtractor` : LLM (JSON par prompt + validation + 1 retry) produisant le schéma d'answers existant ; aucune nouvelle table de mapping |
| BOS-042 endpoint Léa | **MODIFY** | S'appuie sur `CompleteSetupInterview` (zéro nouveau chemin d'exécution) ; inclut l'E2E pilote Travel |
| BOS-043 UI Léa | **KEEP** | Inchangée (contrat = answers d'interview + résumé éditable) |
| BOS-044 pilote Léa | **KEEP** | Cible = TravelAgency (pilote §7) |
| BOS-050 PublicOffer | **MODIFY** | → « Plumbing commerce public » : `PublicTenantResolver` + `IdempotentGuestWrite` + `TrackingSecretService` + throttle dédoublonné + suivi restaurant renforcé + conventions doc. **Avancée en Phase 2** (c'est de la convergence, pas de l'expansion) |
| BOS-051 ADR surfaces | **KEEP** | Inchangée |

## 13. Nouvelles issues (manquantes, issues de la 2e passe)

| Issue | Titre | Phase | Taille | Justification |
|---|---|---|---|---|
| **BOS-006** | Dédupliquer le scheduler (bootstrap/app.php vs routes/console.php) — une seule source, horaires unifiés, `withoutOverlapping` partout, supprimer la commande d'expiration Travel legacy | **0** | S | CRITICAL C1 — double-exécutions actives (congés, facturation, bookings) |
| **BOS-007** | `contracts:alert-expiring` : implémenter réellement les notifications (ou retirer la commande) + supprimer la PII des logs | **0** | S | CRITICAL C2 — feature annoncée absente |
| **BOS-008** | Quota IA atomique : trancher sur la valeur de retour de l'upsert (`AiCreditService`) ou `Cache::lock` | **0** | XS | M1 — fuite de coût provider |
| **BOS-009** | Complétude activation Travel (pilote) : survey travel, E2E trial-signup (+assert seed géo), trajet vendable au demo kit, template rôles agence | **4** (prérequis Léa) | M | Gaps 1-5 du scénario pilote (§7) |
| **BOS-016** | Complétude activation Retail & Edu : manifest Retail + enregistrement catalogue ; seed minimal Edu (campus, année scolaire) | **2** | M | Retail non provisionnable (422) ; Edu = workspace vide — bloque toute promesse « multi-métiers » sur 2 verticales vendues |
| **BOS-017** | Fiabilisation jobs & listeners : `ShouldBeUnique`/`$tries` sur exports, passer en queue les 3 listeners synchrones à effets de bord externes | **2** | M | M2/H5 — exports dupliqués, reçus PDF synchrones |
| **BOS-018** | Hygiène migrations : garde CI contre `schemaTableExists()` silencieux sur création dupliquée + correction des `down()` destructeurs Travel + suppression des correctifs commités en double | **2** | S | H1 — drift prod documenté (500 sur advert-types) |

BOS-019 et suivants : non créés — la dette de typage PHPStan (M3), les god classes (H2) et les contrats API (H3) sont des chantiers de fond déjà suivis par les gardes/baselines existantes ; les transformer en issues de ce programme serait de la dilution (traités en continue par le ratio fix/feat existant).

## 14. Parallélisme revalidé

| Groupe | Verdict | Détail |
|---|---|---|
| Phase 0 (BOS-001, 002, 004, 005, 006, 007, 008) | **PARALLEL SAFE** (fichiers disjoints : AI vs logs vs scheduler vs tenancy) ; BOS-003 (devops) **avec coordination** — dépend de la topologie figée par BOS-006 |
| Core registry (BOS-011, 012, 013, 026, 027) | **SEQUENTIAL** sur `app/Core` + `Company.php` (confirmé) ; BOS-013/014 peuvent se paralléliser avec BOS-011 si dev distinct **avec coordination** (fichiers manifests ≠ fichiers registre) |
| AI (BOS-031, 032, 034 après BOS-001/008) | **PARALLEL WITH COORDINATION** — `Orchestrator.php`/`config/ai.php` touchés par les trois : 1 PR à la fois sur ces 2 fichiers |
| Convergence (BOS-020, 021, 050) | **PARALLEL WITH COORDINATION** — zones `app/Shared` et middlewares publics communes ; séquencer : 050 (plumbing) peut précéder 020 sans conflit si répertoires distincts |
| BOS-024a–g (Application layer) | **PARALLEL SAFE** — 7 modules disjoints (confirmé) |
| BOS-016, 017, 018, 022, 023, 025 | **PARALLEL SAFE** entre elles (modules disjoints) ; BOS-016 (manifests Retail/Edu) **avec coordination** avec BOS-013/014 (même contrat manifest) |
| Fronts (BOS-035, 043) | **PARALLEL SAFE** (pages distinctes de `front/web`) avec design system partagé |
| Migrations tenant | **SEQUENTIAL** — pipeline unique, additives, garde CI anti-duplication (renforcée par BOS-018) |
| Léa (BOS-040→041→042→044) | **SEQUENTIAL** par nature ; BOS-043 en parallèle sur contrat mocké (confirmé) |

## 15. Classification finale (Impact × Risque × Dépendance × Effort × Valeur)

### MUST DO (indispensable)
- **BOS-006** (scheduler — corruption potentielle de données RH/billing en prod) 
- **BOS-001** (fuite PII active)
- **BOS-007** (alertes contrats inexistantes + PII logs)
- **BOS-008** (fuite coût quota IA)
- **BOS-003** (fiabilité prod — dès décision budget)
- **BOS-010 → BOS-011 → BOS-012** (registre unifié — la désync features est récurrente et bloque tout versioning propre)
- **BOS-004** (cohérence métier absences)
- **BOS-022** (Planning = absences/frais avec 2 tests — risque silencieux)

### SHOULD DO (important, non bloquant)
- BOS-002, BOS-005, BOS-013, BOS-014, BOS-015, BOS-016, BOS-017, BOS-018, BOS-020 (descopée), BOS-021, BOS-023, BOS-024, BOS-025, BOS-026, BOS-050 (plumbing), BOS-051
- BOS-030→031 (résilience LLM) — MUST seulement **si** Léa est engagée (elle met le LLM sur le chemin signup)
- BOS-032, BOS-034, BOS-035 (adoption IA), BOS-036 (ADR multi-org)

### SHOULD NOT DO NOW (reporté)
- BOS-027 (abonnement ×3 — cosmétique tant que rien ne casse)
- BOS-033 streaming (supprimée → F7)
- Léa complète (BOS-040→044) : SHOULD, pas MUST — valeur différenciante réelle mais conditionnée à Phase 1 + résilience ; **ne pas la lancer avant**
- F1–F7 : marketplace unifié, groupe/holding, RAG, provider local, agents, routage par tâche, migration TS admin

## 16. Sur-architecture — double question appliquée

| Abstraction | « Nécessaire aujourd'hui ? » | « Sa absence rend-elle l'évolution difficile ? » | Verdict |
|---|---|---|---|
| Routage LLM par tâche | Non | Non (l'interface permet de l'ajouter sans rupture) | **Supprimé** |
| PublicOffer métier | Non | Faiblement (le pattern copié est discipliné) | **Remplacé par plumbing** ; conventions doc = contrat léger |
| Capabilities registry (manifests) | Non (le QCM mapping suffit) | Possible si Léa v2 veut du fin-grain | **Contrat léger** : champ `industry` seul (BOS-013), le reste via extension des `priorities` documentée |
| Knowledge/RAG | Non | Non — Cabinet + PG16 n'empêchent rien | **Règle de conception** ajoutée (constitution), zéro code |
| Idempotence invitée mutualisée | **Oui** (4 copies à l'identique) | — | **Construite** (BOS-050 modifiée) |
| Résilience LLM (décorateur) | Oui dès Léa | Oui (signup dépendra du LLM) | **Construite, version réduite** |
| Niveau groupe/holding | Non | Moyennement (cas H réel mais aucun client) | **ADR d'abord** (BOS-036), code jamais avant |

## 17. Architecture finalement retenue

Inchangée dans ses fondations (monolithe modulaire, Core + transverse + verticales + IA + commerce par verticale). **Ajustements de la 2e passe** :

1. **Léa = traducteur NL → réponses d'interview existantes** (et non nouveau pipeline intent→capabilities) — le chemin d'exécution reste `CompleteSetupInterview` → `SolutionActivator`, inchangé et déjà testé.
2. **AI : décorateur `ResilientLLMClient`** (retry/fallback/circuit-breaker) — pas de router.
3. **Commerce public : mutualisation du plumbing** (résolution tenant, idempotence invitée, secrets de suivi, throttle) — pas de contrat métier.
4. **Phase 0 devient « fiabilité production & confidentialité »** (8 issues, incl. les 2 CRITICAL nouveaux).
5. **Pilote d'onboarding = TravelAgency**, avec issue de complétude dédiée (BOS-009) ; Retail/Edu comblées en Phase 2 (BOS-016) pour que la promesse multi-métiers soit vraie sur toutes les verticales vendues.

## 18. Roadmap corrigée

```
Phase 0 — Fiabilité production & confidentialité        [2-3 sem]  MUST
  BOS-006 (scheduler) → BOS-003 (workers, décision budget)
  BOS-001 ∥ BOS-002 ∥ BOS-004 ∥ BOS-005 ∥ BOS-007 ∥ BOS-008
  + BOS-022 (tests Planning) ∥ BOS-010 (spec registre)

Phase 1 — Registre unifié                                [3-4 sem]  MUST
  BOS-011 → BOS-012 ∥ BOS-013 → BOS-014 ∥ BOS-015

Phase 2 — Convergence & complétude                       [4-6 sem]  SHOULD
  BOS-016 (Retail/Edu) ∥ BOS-017 (jobs/listeners) ∥ BOS-018 (migrations)
  ∥ BOS-020→021 (integration) ∥ BOS-023 (cycles) ∥ BOS-024a-g ∥ BOS-025
  ∥ BOS-026 ∥ BOS-050 (plumbing public) ∥ BOS-051

Phase 3 — Résilience IA & adoption                       [2-3 sem]  SHOULD
  BOS-030→031 (décorateur) ∥ BOS-032 ∥ BOS-034 ∥ BOS-035 (web) ∥ BOS-036 (ADR)

Phase 4 — Léa sur pilote Travel                          [3-4 sem]  SHOULD (gated: P1+P3)
  BOS-009 (complétude Travel) ∥ BOS-040 (spec) → BOS-041 → BOS-042 ∥ BOS-043 → BOS-044

(Phase 5 absorbée : BOS-050/051 déplacées en Phase 2 — plus de Phase 5)
(DEFER : BOS-027, F1-F7)
```

Changements vs 1er passage : Phase 0 élargie (3 issues CRITICAL/HIGH ajoutées) ; Phase 5 absorbée dans Phase 2 ; Phase 3 réduite (2→3 sem, routage supprimé) ; Phase 4 conditionnée au pilote Travel et à la complétude BOS-009.

## 19. Risques supplémentaires (2e passe)

| Risque | Gravité | Mitigation |
|---|---|---|
| Double-exécution scheduler déjà en cours → données de congés/facturation déjà dérivées en prod | 🔴 | BOS-006 inclut un **audit d'impact rétrospectif** (doublons `leave_accruals`, factures en double) + correction de données si avérée |
| Le pattern « migration silencieuse no-op » (337 migrations) masque d'autres drifts prod inconnus | 🟠 | BOS-018 + inventaire des drift connus dans l'issue |
| Léa dépend d'un JSON fiable sans structured output provider | 🟡 | Prompt JSON + validation allowlist + 1 retry + fallback interview (jamais bloquant) ; `response_format` en option via BOS-031 |
| Cas H (cabinet comptable) non supporté alors que c'est un canal de distribution majeur PME | 🟡 | Scénario explicite dans l'ADR BOS-036 — possible quick-win : comptes multi-liens `user_employee_links` + switcher, sans refonte email |
| La complétude verticale (BOS-016/009) crée un précédent « amorçage obligatoire » pour toute future verticale | 🟢 | Formaliser dans la constitution : nouvelle verticale = manifest + seed minimal + E2E activation (règle déjà esquissée par Travel) |

---

# ARCHITECTURE FINAL DECISION

**ACCEPT**
1. La stratégie de consolidation (confirmée par la 2e passe — aucune décision structurelle du 1er passage n'est renversée).
2. Le modèle Company (cas A, C, E, F, G-mono-entité supportés ; manques convergent vers un seul chantier multi-org précédé d'un ADR).
3. Le provisioning existant comme moteur du Business OS (structure complète ; amorçage à compléter par verticale).
4. TravelAgency comme vertical pilote de l'onboarding intelligent.
5. Le refus de : Organization/Workspace/Membership, schema-per-tenant, RAG, marketplace unifié, providers LLM supplémentaires, agents autonomes, routage par tâche.

**MODIFY**
1. Léa : NL → **réponses d'interview existantes** (suppression du BusinessIntent→capabilities au profit du mapping déjà en place).
2. IA : **décorateur de résilience** au lieu du ModelRouter.
3. Commerce : **plumbing mutualisé** au lieu du contrat PublicOffer.
4. Phase 0 : élargie à la fiabilité production (scheduler, alertes contrats, quota IA) — 3 issues CRITICAL/HIGH ajoutées.
5. BOS-002, 005, 013, 020, 023, 030, 031, 040, 041, 042, 050 : scopes ajustés (§12).

**REJECT**
1. BOS-033 (streaming) — supprimée du plan.
2. Le chiffre « 70 % » comme argument stratégique — remplacé par la matrice (§2).
3. Toute implémentation avant la fin de Phase 0 (les CRITICAL changent la donne opérationnelle).

**DEFER**
1. BOS-027 (abonnement ×3) — fin Phase 2 si capacité.
2. F1–F7 inchangés ; Léa (BOS-040→044) reste **gated** sur Phase 1 + résilience LLM + BOS-009.

# NEXT EXECUTION STEP (premières tâches réelles pour les développeurs)

**Bloc 1 — immédiat, séquentiel sur 2 semaines :**
1. **BOS-006** — Dédupliquer le scheduler + audit d'impact rétrospectif des doubles exécutions (congés, factures, bookings). *Backend, S, PARALLEL SAFE.*
2. **BOS-001** — Sanitiser les tool_result sur toutes les branches provider. *Backend/Security, S.*
3. **BOS-007** — Implémenter (ou retirer) `contracts:alert-expiring` + PII logs. *Backend, S.*
4. **BOS-008** — Quota IA atomique. *Backend, XS.*

**Bloc 2 — en parallèle du bloc 1 (agents distincts) :**
5. **BOS-022** — Tests Planning (2→30). *Backend/QA, M, PARALLEL SAFE.*
6. **BOS-010** — Spec registre unifié (spec-kit). *Architecte, S — lance le chemin critique Phase 1.*
7. **BOS-002** — Rétention logs IA + hygiène PII logs. *Backend, S.*
8. **BOS-004** — Write-tool legacy absences. *Backend/AI, S.*

**Bloc 0 — hors code, semaine 1 :**
9. **Décision owner** — budget workers/scheduler prod (#7649) : sans elle, BOS-003 et la moitié de la valeur de BOS-006 restent théoriques.

*Aucune autre issue ne démarre avant la fin du bloc 1. Création GitHub des issues : uniquement après validation de ce document par le owner.*
