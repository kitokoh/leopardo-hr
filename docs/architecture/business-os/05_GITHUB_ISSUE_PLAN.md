# GITHUB ISSUE PLAN — Business OS

> Date : 2026-09-26 · Base : `04_MIGRATION_ROADMAP.md`
> Convention : EPIC → issues atomiques `BOS-xxx`. Labels à créer : `business-os`, `architecture`, `core`, `ai`, `security`, `provisioning`, `tenancy`, `vertical`, `commerce`, `frontend`, `backend`, `devops`, `database`, `testing`, `documentation` (+ existants : `enhancement`, `bug`, `Agent-Ready`). Sync déclarative recommandée (le repo n'a pas de `labels.yml` — dette connue).
> Chaque issue respecte les templates existants (.github/ISSUE_TEMPLATE) et le processus spec-kit quand indiqué. **Estimations** : XS <1j, S 1–2j, M 3–5j, L 1–2sem.

---

## EPIC E0 — Stabilisation critique (Phase 0)

### BOS-001 — [security][ai] Sanitiser les tool_result sur toutes les branches provider
- **Contexte** : `PrivacySanitizer::sanitizeMessages()` ne nettoie que les `content` string. Branche Claude (`Orchestrator.php` ~l.150-166) : les `tool_result` tableaux partent non sanitisés → noms/emails d'employés envoyés en clair à Anthropic.
- **Objectif** : 100 % des payloads sortants (messages + tool results, toutes branches) passent le sanitizer.
- **Pourquoi** : fuite PII active en prod, risque RGPD critique.
- **Scope** : `api/app/AI/Privacy/PrivacySanitizer.php`, `Orchestrator.php` (branches Claude/OpenAI/Groq), tests.
- **Hors scope** : refonte du sanitizer (regex v2), sanitization des montants.
- **Dépendances** : aucune. **Préconditions** : reproduire avec un test qui intercepte le payload Claude.
- **Approche** : sanitiser récursivement les structures (tableaux associatifs tool_result) avant envoi ; test de contrat par provider avec fixture PII (email/téléphone/ID) dans un résultat `get_employees`.
- **Critères d'acceptation** : test prouvant qu'aucun email/téléphone ne figure dans le payload HTTP sortant des 3 drivers ; pas de régression des réponses IA (tests existants verts).
- **Tests requis** : feature test par driver avec fake HTTP ; snapshot du payload sanitisé.
- **Sécurité** : c'est le fix sécurité. **Migration** : aucune. **Documentation** : note dans `docs/ai/AI_ARCHITECTURE.md` + CHANGELOG.
- **DoD** : tests verts CI, revue sécurité, CHANGELOG, pas de régression latence mesurée.
- **Agent** : Backend/Security · **Parallèle** : Oui · **Taille** : S

### BOS-002 — [security][ai] Rétention et minimisation des logs IA
- **Contexte** : `ai_audit_logs` stocke prompt + réponse en clair (10 000 car.) sans rétention.
- **Objectif** : politique de rétention (ex. 90 j) + purge planifiée + hachage/troncature optionnelle du libre-texte.
- **Scope** : migration (colonne `purged_at` ou job de purge), commande planifiée, config `ai.php`. **Hors scope** : chiffrement au repos.
- **Dépendances** : BOS-003 (scheduler fiable en prod) pour l'exécution planifiée effective.
- **Approche** : commande `ai:purge-audit-logs` idempotente, planifiée daily ; test avec données datées.
- **Critères** : purge vérifiable, config documentée, registre RGPD (`docs/RGPD_REGISTRE_TRAITEMENTS.md`) mis à jour.
- **Agent** : Backend · **Parallèle** : Oui (avec BOS-001) · **Taille** : S

### BOS-003 — [devops] Workers et scheduler dédiés en production
- **Contexte** : queue + scheduler tournent dans le conteneur web free Render qui s'endort → crons perdus, queue drainée au réveil ; décision #7649 documentée non exécutée ; prod restée 2 j derrière main (#8092).
- **Objectif** : services `worker` et `scheduler` séparés (ou équivalent payant minimal) + alerte de drift de déploiement.
- **Scope** : `render.prod.yaml`, gates `WEB_QUEUE_DRAIN`/`WEB_SCHEDULER_LOOP`, supervision existante. **Hors scope** : migration d'hébergeur.
- **Préconditions** : **décision budgétaire du owner** (bloquant — coût récurrent).
- **Critères** : job planifié de test exécuté à l'heure sur 7 j ; alerte rouge si prod derrière main > 1 h.
- **Agent** : DevOps · **Parallèle** : Oui · **Taille** : S (+ décision)

### BOS-004 — [ai][backend] Supprimer le write-tool legacy `approve_absence`
- **Contexte** : deux write-tools pour le même acte ; le legacy fait un `->update()` direct sans l'Action canonique ni les événements `AbsenceApproved`.
- **Objectif** : un seul chemin d'approbation (`absence_decision` → Action canonique).
- **Approche** : retirer le handler legacy de `WriteActionRunner`, matrice et `ai_tool_registry` (seeder) ; test de garde mis à jour.
- **Critères** : approbation via IA émet `AbsenceApproved` (test) ; aucun tool orphelin dans le registre DB (migration de nettoyage).
- **Migration** : delete ligne registry — additive seulement. **Agent** : Backend/AI · **Parallèle** : Oui · **Taille** : S

### BOS-005 — [core][database] Nettoyer le mode schema-per-tenant mort
- **Contexte** : `tenancy_type='schema'` verrouillé (abort 422), `search_path` vestigiel, pièges handlers kiosque, enum mort.
- **Objectif** : supprimer le code mort et documenter le modèle définitif (shared schema + scope).
- **Approche** : ADR court « shared-schema définitif » ; retrait progressif (d'abord commentaires/enum/docs, migrations de nettoyage en dernier, additives).
- **Critères** : plus aucun chemin de code `tenancy_type=schema` ; ADR mergé ; tests tenancy verts.
- **Agent** : Backend · **Parallèle** : Oui · **Taille** : M

---

## EPIC E1 — Registre unifié Module/Solution (Phase 1)

### BOS-010 — [architecture] Spec : registre unifié modules/features/solutions
- **Objectif** : spec complète (specify → clarify → plan → tasks) de la fusion des 3 sources (`config/feature-flags.php`, `Company::KNOWN_MODULES`, `metadata.modules`) en une source unique.
- **Livrables** : `.specify/features/<issue>-unified-module-registry/{spec,plan,tasks}.md` + ADR.
- **Contenu obligatoire** : modèle de données du registre (code, versionné, en PHP — pas de table : cohérent avec la gouvernance actuelle), stratégie dual-read, test de parité, politique kill switch, règles pour `metadata.modules` (dérivé, jamais source).
- **Dépendances** : aucune. **Agent** : Architecte/PM · **Taille** : S

### BOS-011 — [core][backend] ModuleRegistry : source unique de vérité
- **Contexte** : dette D1 — désynchronisations répétées (#7220, #7235, #7432, #7785, #7976).
- **Objectif** : `App\Core\Feature\ModuleRegistry` unique décrivant chaque module (key, label, type horizontal|vertical|platform, défaut, killable, dépendances) ; `Company::KNOWN_MODULES`, `feature-flags.php`, `hasFeature()`, `activateHorizontalTool()`, `PlatformCompanyFeatureController` consomment tous le registre.
- **Approche** : registre PHP versionné + cache ; dual-read 1 release avec test de parité (feature map reconstruite == feature map actuelle pour un échantillon de companies) ; kill switch DB conservé en première priorité.
- **Critères** : ajouter un module = 1 entrée ; `/auth/me` sortie identique (snapshot test) ; garde CI « module non enregistré = échec ».
- **Tests** : parité, kill switch, défauts, provisioning trial (profils company/solo, plancher #7423).
- **Sécurité** : fail-closed conservé (module inconnu = off). **Migration** : aucune (code seul, BOS-012 pour les données).
- **Agent** : Backend senior · **Parallèle** : Non (touche le cœur) · **Taille** : L · **Dépendances** : BOS-010

### BOS-012 — [core][database] Migration de consolidation des features company
- **Objectif** : backfill `companies.features`/`metadata.modules` depuis le registre pour tous les tenants ; `metadata.modules` devient dérivé (écrit par le même code que `features`).
- **Approche** : commande `modules:consolidate --dry-run` d'abord ; rapport de diff par company ; exécution ; test de parité post-migration.
- **Critères** : 0 diff fonctionnel (hasFeature avant/après) ; rapport archivé ; rollback = dual-read conservé.
- **Migration** : données JSONB uniquement, additive, sans downtime. **Agent** : Backend · **Parallèle** : Non (suit BOS-011) · **Taille** : M · **Dépendances** : BOS-011

### BOS-013 — [core][provisioning] SolutionManifest v2 : industry + permissions enforceées
- **Contexte** : `permissions()` déclaré mais jamais câblé ; pas de champ `industry`.
- **Objectif** : manifest v2 (`industry`, `capabilities[]`, `roles` déclarés) ; à l'activation, les permissions du manifest alimentent les grants par défaut des rôles concernés (via `employee_module_grants`/`ModuleKey` — pas de nouveau système).
- **Approche** : étendre le contrat + `SolutionActivator` applique les grants dans la transaction d'activation (idempotent) ; test : activer edumanager → rôle principal a les grants déclarés.
- **Critères** : permissions d'un manifest inactif jamais effectives (fail-closed) ; audit `solution.activated` enrichi.
- **Agent** : Backend · **Parallèle** : Oui (avec BOS-011 si devs distincts) · **Taille** : M · **Dépendances** : BOS-010

### BOS-014 — [vertical][architecture] Convergence des manifests solution legacy
- **Contexte** : Delivery et RestaurantManager implémentent leur propre contrat legacy ; deux manifests restaurant (module `Restaurant` + `RestaurantManager/Domain/Manifests`).
- **Objectif** : tous les manifests implémentent `App\Core\Solutions\Contracts\SolutionManifest` ; un seul manifest restaurant ; suppression des contrats dupliqués.
- **Approche** : adapter les manifests legacy, renommer le module `Restaurant` → clarifié (descripteur de solution) ou fusionner dans RestaurantManager ; garde CI « manifest conforme ».
- **Critères** : 9 manifests conformes ; activation de chaque verticale testée.
- **Agent** : Backend · **Parallèle** : Oui · **Taille** : S · **Dépendances** : BOS-013

### BOS-015 — [documentation][billing] Clarifier le nommage Feature Registry (inventaire API mobile)
- **Objectif** : renommer le concept Billing « Feature Registry » (inventaire d'endpoints pour manifeste mobile) en `ApiEndpointRegistry` (ou équivalent) pour lever la confusion avec le feature-gating tenant ; évaluer le déplacement de la table `features` du schéma tenant vers public.
- **Critères** : renommage complet (classes, routes internes, docs) sans casser le manifeste mobile versionné ; décision table documentée.
- **Agent** : Backend + Docs · **Parallèle** : Oui · **Taille** : XS–S · **Dépendances** : BOS-011 (éviter collision de noms avec ModuleRegistry)

---

## EPIC E2 — Convergence transverse & cœur (Phase 2)

### BOS-020 — [architecture][backend] Module Integration : outbox unifié
- **Contexte** : outbox dupliqué ×4 (Travel, Restaurant, Edu, Platform).
- **Objectif** : `app/Modules/Integration` (ou `app/Shared/Integration` — tranché en spec) fournissant OutboxEvent + dispatcher + consumers ; migration progressive des 4 modules, un à la fois, sans renommage de tables existantes (vues/adaptateurs si nécessaire).
- **Préconditions** : spec (spec-kit) comparant les 4 implémentations ; choix minimal couvrant les 4.
- **Critères** : ≥ 2 modules migrés sans perte d'événements (tests de replay) ; les 2 autres planifiés avec issues filles.
- **Agent** : Backend senior · **Parallèle** : partiellement (1 module migré = 1 sous-tâche) · **Taille** : L · **Dépendances** : BOS-010 (même processus spec)

### BOS-021 — [backend] Webhooks sortants unifiés
- **Contexte** : 3 implémentations (Billing, TravelAgency, Platform).
- **Objectif** : un seul mécanisme (endpoint, signature, delivery, retry) dans Integration, consommé par les 3.
- **Critères** : signature compatible avec les consommateurs existants ou versionnée ; tests de livraison + retry ; secrets inchangés côté clients.
- **Agent** : Backend · **Parallèle** : Oui (avec BOS-020 si devs distincts, sinon séquentiel) · **Taille** : M · **Dépendances** : BOS-020

### BOS-022 — [testing][backend] Rattrapage tests Planning
- **Contexte** : Planning possède absences et notes de frais (propriétaire canonique) avec **2 tests**.
- **Objectif** : ≥ 30 tests couvrant : cycle de vie absence (demande→approbation→refus→annulation), règles de solde, notes de frais (validation, écritures comptables via Expense), scoping manager (dept/superviseur), interactions Payroll.
- **Critères** : couverture des chemins critiques ; golden tests pour les calculs de soldes (convention constitution).
- **Agent** : Backend/QA · **Parallèle** : Oui · **Taille** : M · **Dépendances** : aucune

### BOS-023 — [architecture][backend] Réduction des cycles d'imports du cœur HR (palier 1)
- **Contexte** : 55 paires allowlistées, cycles HR↔Attendance↔Planning↔Payroll, Notification→5 modules.
- **Objectif** : palier mesuré 55 → ≤ 35 en déplaçant les dépendances vers des contrats `app/Shared/Contracts` (pattern éprouvé `Shared/Contracts/Crm`) ; aucune extraction de package.
- **Approche** : itératif — 1 cycle = 1 PR ; priorité aux cycles (pas aux dépendances simples) ; chaque PR réduit l'allowlist.
- **Critères** : allowlist ≤ 35 ; `check-module-isolation.sh` vert ; aucune régression fonctionnelle.
- **Agent** : Backend senior · **Parallèle** : partiellement (conflits possibles avec BOS-024 sur les mêmes modules — coordination) · **Taille** : L (continue) · **Dépendances** : aucune

### BOS-024 — [backend] Couche Application pour les modules à 0 (7 issues filles)
- **Contexte** : EduManager, HealthManager, Pharmacy, HospitalityManager, Communication, Catalog, Fleet sans Application layer (controllers épais) ; HR mince (3).
- **Objectif** : pour chaque module : Actions nommées pour les use cases principaux, controllers amincis, convention `check-actions-convention.sh` respectée.
- **Découpage** : **1 issue fille par module** (BOS-024a…g) — parallélisable à volonté, aucun recouvrement de fichiers.
- **Critères (par module)** : ≥ 5 Actions ou justification documentée ; tests des Actions ; aucun changement d'API.
- **Agent** : Backend (1 dev/module) · **Parallèle** : **Oui, totalement** · **Taille** : M chacune · **Dépendances** : aucune

### BOS-025 — [architecture] ADR frontière Notification vs Communication + déplacement
- **Contexte** : threads dupliqués (`Notification.ConversationThread` vs `Communication.CommunicationThread`), `CommunicationEvent` dans Notification.
- **Objectif** : ADR tranchant (proposition : Notification = préférences/push/annonces internes ; Communication = canaux externes + threads) ; déplacement des classes mal placées avec alias de compat.
- **Critères** : ADR validé ; déplacement sans renommage de tables ; événements cross-module mis à jour.
- **Agent** : Architecte + Backend · **Parallèle** : Oui · **Taille** : M · **Dépendances** : aucune

### BOS-026 — [backend] Achèvement des façades : Growth→Billing, Absence→Planning
- **Contexte** : Growth = 0 modèle propre (modèles Partner* dans Billing) ; Absence = façade HTTP sur Planning (PA2-ARCH-002).
- **Objectif** : absorber Growth dans Billing (routes/providers/filters déplacés, module retiré du registre) ; finaliser Absence→Planning (retrait de la façade si plus aucun consommateur, ou réduction au strict nécessaire documenté).
- **Critères** : modules retirés proprement (registre, providers, routes, tests déplacés) ; CHANGELOG ; pas de route cassée (test de contrat).
- **Agent** : Backend · **Parallèle** : Oui · **Taille** : S · **Dépendances** : BOS-011 (retrait propre via registre unifié)

### BOS-027 — [billing][database] Unification de la représentation d'abonnement
- **Contexte** : abonnement représenté 3× (`companies.plan_id` + dates, table tenant `subscriptions` string, `public.plans` en DB::table sans modèle).
- **Objectif** : une source de vérité (proposition : `public.plans` + `companies.plan_id` canoniques ; `subscriptions` tenant = historique/paiements uniquement) ; modèle Eloquent `Plan` ; service unique de lecture d'état d'abonnement.
- **Critères** : lecture d'état via 1 service partout (rate-limit `api-plan`, provisioning, console plateforme) ; aucune migration destructive ; dual-read transitoire testé.
- **Agent** : Backend · **Parallèle** : Oui · **Taille** : M · **Dépendances** : BOS-011

---

## EPIC E3 — AI Gateway minimal + sécurité (Phase 3)

### BOS-030 — [ai][architecture] Spec : ModelRouter minimal
- **Objectif** : spec du routage : table tâche→provider (chat, classification email, marketing, interprétation Léa), politique de fallback, circuit breaker (seuils), retry/backoff, comportement de dégradation (fake/jamais d'erreur nue à l'utilisateur).
- **Contraintes spec** : rester sur l'interface `LLMClient` existante ; aucun nouveau provider dans cette phase ; quotas/budgets existants conservés en amont du routeur.
- **Agent** : Architecte/AI · **Taille** : S · **Dépendances** : aucune

### BOS-031 — [ai][backend] ModelRouter : routage, fallback, circuit breaker
- **Objectif** : `ModelRouter` sélectionnant le driver selon la tâche (config `ai.php`), fallback automatique sur échec/timeout, circuit breaker par provider (ouvert après N échecs, half-open), retry avec backoff ; métriques par provider dans l'analytics existant.
- **Critères** : test de failover (provider A down → B répond, utilisateur servi) ; circuit breaker ouvre/ferme (test) ; budgets toujours fail-closed ; coût estimé par provider (tarifs externalisés en config — corrige la dette des tarifs en dur).
- **Sécurité** : `AiCloudPolicy` évaluée par provider candidat (jamais de fallback vers un cloud interdit si `ai_cloud_allowed=false` → dégradation explicite).
- **Agent** : Backend/AI senior · **Parallèle** : Non (cœur IA) · **Taille** : M · **Dépendances** : BOS-030, BOS-001

### BOS-032 — [ai][security] Idempotence métier des write-tools
- **Objectif** : clé d'idempotence (UUID côté conversation + empreinte args) sur les 8 write-tools ; table `ai_write_idempotency` (company_id, key, résultat, TTL) ; rejeu = résultat initial, pas de double effet.
- **Critères** : double confirmation (réseau/refresh) = 1 seul effet métier (test par tool critique : create_employee, approve absence) ; `PendingActionStore` évalué pour passage en DB si driver cache non partagé (décision dans l'issue).
- **Agent** : Backend/AI · **Parallèle** : Oui (avec BOS-031 si devs distincts) · **Taille** : S · **Dépendances** : BOS-001

### BOS-033 — [ai][frontend] Streaming SSE du chat (conditionnel)
- **Objectif** : réponses streamées (SSE) sur `/ai/chat` + client mobile manager ; si l'effort dépasse 2 j en conception → classer F7 (reporté).
- **Critères** : streaming fonctionnel avec les 3 providers ou décision documentée ; budgets appliqués au cumul streamé.
- **Agent** : Backend + Mobile · **Parallèle** : Oui · **Taille** : M · **Dépendances** : BOS-031

### BOS-034 — [ai][security] Anti-injection chat + validation runtime des outputSchema
- **Contexte** : le pipeline email a un anti-injection, pas le chat principal ; `outputSchema` déclaré mais jamais validé à l'exécution.
- **Objectif** : marquage/encadrement des contenus métier injectés dans les tool results (délimiteurs + instruction système) ; validation JSON-schema des arguments LLM avant dispatch et des sorties de tools ; violation = refus fail-closed + audit.
- **Critères** : test d'injection via motif d'absence hostile (le LLM ne doit pas déclencher de write non demandé) ; test de contrat outputSchema sur tous les tools déclarés.
- **Agent** : Security/AI · **Parallèle** : Oui · **Taille** : M · **Dépendances** : BOS-001

### BOS-035 — [frontend][ai] Assistant dans le portail web + UI de confirmation
- **Contexte** : seul le mobile manager consomme `/ai/chat` ; aucun client pour confirm/reject → capacités d'écriture dormantes.
- **Objectif** : panneau assistant dans `front/web` (portail tenant) : chat, affichage `tools_used`, cartes de confirmation (pending_confirmations → `/ai/actions/{id}/confirm|reject`), historique.
- **Critères** : parcours complet question → tool read → réponse ; parcours write → confirmation → exécution → audit visible ; respect RBAC (l'UI n'affiche que ce que l'API autorise) ; tests e2e Playwright du flux de confirmation.
- **Agent** : Frontend (Next.js) · **Parallèle** : Oui · **Taille** : M · **Dépendances** : aucune (API existante)

### BOS-036 — [tenancy][architecture] Étude company-switch & email unique global (ADR + POC)
- **Contexte** : `employees.email` unique global + `user_lookups.email` PK bloquent structurellement le multi-org en session ; `user_employee_links` existe.
- **Objectif** : ADR décidant go/no-go du company-switch ; POC de la stratégie retenue (ex. unicité (company_id, email) + backfill + résolution login via User global) ; matrice d'impact (kiosk, invitations, OTP, mobile).
- **Critères** : ADR avec décision explicite (go / go limité aux comptes User liés / no-go) ; estimation de migration ; **aucune implémentation de production dans cette issue**.
- **Agent** : Architecte + Backend senior · **Parallèle** : Oui · **Taille** : M · **Dépendances** : aucune

---

## EPIC E4 — Léa : onboarding en langage naturel (Phase 4)

### BOS-040 — [ai][provisioning] Spec Léa (constitution-compliant)
- **Objectif** : spec complète posant comme exigences non négociables : sortie LLM = JSON schema strict ; allowlist industries/capabilities issue du registre (BOS-011/013) ; mapping déterministe capabilities→solutions ; fallback interview ; aucune écriture par l'IA ; audit complet.
- **Livrables** : spec/plan/tasks + mise à jour `.specify/memory/constitution.md` si nécessaire (clause « IA propose, moteur décide »).
- **Agent** : PM/Architecte · **Taille** : S · **Dépendances** : BOS-011, BOS-013

### BOS-041 — [ai][backend] BusinessIntent : DTO, schéma, validation déterministe, mapping
- **Objectif** : `App\Modules\Onboarding\Application\Lea\BusinessIntent` (industry, business_type, locations, capabilities[]) ; validation contre registre (industries = solutions, capabilities = déclarées dans manifests v2) ; table de correspondance versionnée capabilities→(solutions, outils horizontaux) ; service produisant un plan **identique en structure** à `SetupInterviewPlanner`.
- **Critères** : intent invalide/inconnu → refus structuré avec alternatives ; plan produit accepté par `SolutionActivator` sans modification de celui-ci ; tests : 5 intents types (école, restaurant, hôtel, pharmacie, station-service) → plans attendus.
- **Sécurité** : aucune clé libre ; valeurs hors allowlist rejetées ; pas de tool LLM (completion simple, json_schema).
- **Agent** : Backend/AI · **Parallèle** : Non (séquence Léa) · **Taille** : M · **Dépendances** : BOS-040

### BOS-042 — [ai][backend] Endpoint /onboarding/lea/interpret + clarification + fallback
- **Objectif** : endpoint (auth trial ou invitation, rate-limited, flag `onboarding.lea.enabled`) : interprétation → si confiance faible/capability inconnue, question de clarification (≤ 2 rounds) → plan ; exécution via le flux trial existant (`ProvisionGuidedTrial`) ; fallback automatique vers l'interview déterministe si LLM indisponible (via router BOS-031) ou échec de validation.
- **Critères** : provisioning de bout en bout en staging pour « école » et « restaurant » ; plan Léa == plan interview pour le même besoin (test de parité) ; audit `lea.intent.*` ; flag OFF = comportement actuel inchangé.
- **Agent** : Backend/AI · **Parallèle** : Non · **Taille** : M · **Dépendances** : BOS-041, BOS-031

### BOS-043 — [frontend] UI onboarding Léa (front/web)
- **Objectif** : écran d'accueil onboarding : champ libre « Décrivez votre activité » → résumé structuré éditable (industrie, capacités détectées) → confirmation → provisioning ; possibilité permanente de basculer vers le formulaire classique ; états de clarification conversationnels.
- **Critères** : l'utilisateur valide toujours le plan avant exécution (jamais de provisioning silencieux) ; e2e Playwright (NL → résumé → modification → provisioning) ; i18n fr/en/ar/tr via `shared/`.
- **Agent** : Frontend · **Parallèle** : Oui (avec BOS-042 sur contrat d'API mocké) · **Taille** : M · **Dépendances** : BOS-041 (contrat)

### BOS-044 — [qa][product] Pilote Léa mesuré
- **Objectif** : pilote avec tenants de test + panel bêta : taux de complétion onboarding (Léa vs formulaire), taux de fallback, taux de modification du résumé, coût LLM/onboarding, revue des audits.
- **Critères** : rapport de pilote ; décision GA / itération / retrait (flag) documentée.
- **Agent** : QA + Product · **Parallèle** : Non · **Taille** : S · **Dépendances** : BOS-042, BOS-043

---

## EPIC E5 — Commerce public convergé (Phase 5)

### BOS-050 — [commerce][architecture] PublicOfferContract + conventions API publique
- **Objectif** : contrat `App\Shared\Contracts\Commerce\PublicOffer` (catalog, availability, pricing, order/reservation, idempotence invitée, tokens) + conventions de routes `/api/v1/public/<vertical>/marketplace/*` dérivées des 2 implémentations existantes (retail, travel) ; implémentation de référence documentée.
- **Critères** : les 2 marketplaces existantes peuvent adopter la convention sans breaking change (adaptateurs) ; une 3e verticale (hospitality) a un guide d'implémentation complet ; **aucun portail unifié construit**.
- **Agent** : Architecte + Backend · **Parallèle** : Oui · **Taille** : M · **Dépendances** : aucune

### BOS-051 — [product][documentation] ADR surfaces publiques : front/web vs front/marketplace
- **Objectif** : arbitrage produit documenté : ce qui relève de la vitrine tenant (front/web) vs de la marketplace agrégée (front/marketplace) ; règles pour les futures verticales ; lien avec ADR-0004.
- **Critères** : ADR validé par le owner ; redirections/fusion éventuelle planifiée en issues filles si décidé.
- **Agent** : PM · **Parallèle** : Oui · **Taille** : XS · **Dépendances** : aucune

---

## Rappel des NON-ISSUES (explicitement refusées — ne pas créer)

❌ Organization/BusinessGroup/Workspace/Membership · ❌ schema-per-tenant · ❌ providers Gemini/OpenRouter/Cloudflare/vLLM (→ trigger F4) · ❌ RAG/pgvector/knowledge (→ F3) · ❌ agent ecosystem (→ F5) · ❌ moteur d'automation générique · ❌ marketplace unifié (→ F1) · ❌ niveau groupe/holding (→ F2) · ❌ nouvelles verticales sans pilote client (gouvernance FOCUS).
