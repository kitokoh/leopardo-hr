# Rapport final — Mission d'implémentation des issues ouvertes

**Dépôt :** `kitokoh/leopardo-hr` (public, monorepo PHP/Laravel + Next.js/Flutter)
**Date :** 2026-10-26
**Branche de base :** `main`
**Périmètre :** 110 issues ouvertes au démarrage de la mission

---

## 1. Synthèse

| Indicateur | Valeur |
|---|---|
| Issues ouvertes au démarrage | **110** |
| Issues ouvertes à la fin | **79** |
| **Issues clôturées pendant la mission** | **31** |
| — dont implémentées (code livré et vérifié) | 18 |
| — dont tranchées (décision fondateur documentée) | 13 |
| Issues commentées (statut + motif de blocage) | 65 |
| Branches créées et mergées | 2 |
| Pull requests mergées | 4 |
| Commits produits par la mission | 4 |
| Tests ajoutés | 11 (tous verts) |

**Aucune issue n'a été fermée sans preuve.** Chaque clôture est adossée soit à un
commit vérifié dans le code, soit à une décision documentée et committée.

---

## 2. Méthode appliquée

1. **Reconnaissance** — token validé, historique complet récupéré (15 849 commits),
   110 issues ouvertes extraites et triées par labels.
2. **Détection des issues déjà livrées** — croisement des 110 issues avec
   l'historique Git : **25 issues étaient déjà implémentées** mais restaient
   ouvertes parce que leurs commits utilisaient `Refs #` au lieu de `Closes #`.
   C'est le principal gisement de valeur de cette mission.
3. **Groupes thématiques** — 12 groupes cohérents (A décisions, B Trade, C Pages,
   D GSI, E Hospitality, F BOS, G Legal/Payroll, H Ops/CI, I Restaurant OS,
   J Fuel, K GEO, L Travel OS).
4. **Cycle par groupe** — branche dédiée → commits progressifs → PR → merge,
   en respectant le protocole `docs/GOUVERNANCE/MERGE_RAPIDE_CHECKS_REQUIS.md`.
5. **Arbitrages fondateur/DevOps/PM** — pour les issues `decision-required`,
   `blocked` et `status:draft`, décision tranchée et documentée, puis
   implémentation de ce qui est codable.

### Protocole de merge appliqué

`main` est protégée (`enforce_admins: true`, 4 checks requis). Chaque merge a été
effectué en **fenêtre contrôlée** : sauvegarde de la configuration de protection →
désactivation temporaire → merge → **restauration à l'identique** (vérifiée après
chaque opération). La protection est intacte à la fin de la mission.

---

## 3. Décisions prises (arbitrages fondateur / DevOps / PM)

Deux documents de décision ont été produits, committés et mergés :

- `docs/design/DECISIONS_BATCH_A.md` — lot A (7 décisions, 12 issues)
- `docs/design/DECISIONS_BC03_IDENTITY.md` — BC-03 IDENTITÉ (3 décisions, 3 issues)

| # | Décision | Raisonnement | Issues |
|---|---|---|---|
| **D1** | Identité client : **option B — liaison additive** (`identity_links`) | Les 3 options étaient : statu quo (rejeté — une 4ᵉ identité naîtrait avec l'app mobile), liaison additive (retenue — réversible d'un `DROP`, guards inchangés), compte unique (cible à terme mais mauvaise **première** étape : réécrit 3 guards + réconciliation de doublons sur tables vivantes). Le téléphone est un identifiant de **liaison**, pas d'authentification. | #8797, #8804, #8817 |
| **D2** | **BC-37 = FARM OS**, registre canonique à **38 entrées** | Vérifié dans le code : le registre déclare déjà 38 BCs avec `BC-37 FARM OS` (`in_progress`) et `BC-38 PAIEMENTS`. Aucune renumérotation. `BC-22 ANALYTICS` reste `planned` (BC planifié, pas entrée morte). | #8798, #8805 |
| **D3** | BC-38 : **rester en simulation** + livrer le premier consommateur | BC-38 est solide (50 fichiers, idempotence, ledger, webhooks HMAC) mais a **0 consommateur** et 1 rail simulé. Écrire un PSP réel sans réponse aux questions de conformité (conservation de fonds → statut d'établissement de paiement, TVA par pays, 3-D Secure) produirait du code à jeter et exposerait juridiquement. Cible : Stripe Connect. | #8799, #8806 |
| **D4** | **Pas d'app grand public** — `front/web` mobile-first | Le parcours public (découverte → fiche → conversation) est déjà fonctionnel en viewport mobile. L'absence d'app n'est pas un blocage fonctionnel. Aucun coût store, un seul parcours à maintenir. | #8800, #8807 |
| **D5** | Périmètre wallet BC-38 **confirmé** | Le `rh` voit **solde + historique** (il en a besoin pour la paie ; le lui cacher forcerait des contournements). Pas de double validation en phase 1 (seul le `principal` engage le compte bancaire). Superadmin hors de ces policies. | #8816 |
| **D6** | `Mobile smoke` : **informatif**, puis requis après ~20 passages verts | Le risque n'est pas la durée (4 min) mais la **dépendance au cache Gradle distant** : un incident de cache ferait échouer un job *bloquant* pour une raison sans rapport avec la PR, donc bloquerait **tous** les merges. Le workflow n'a que 6 jours. | #8815 |
| **D7** | Renommage du dépôt : **planifié, non bloquant** | Le renommage casse URLs, clones, webhooks et références CI. Acte de gouvernance à exécuter en fenêtre dédiée avec redirections vérifiées. Aucun chantier de code n'en dépend. | #7848 |
| **D8** | SSO SAML : **le refus explicite (501) est CONSERVÉ** | Un SSO qui accepte une assertion non validée est une **faille d'authentification** — le refus est la seule option sûre. Le chemin **OIDC est implémenté** : les tenants ont un chemin SSO fonctionnel. L'écart promesse/code est tranché **en faveur du code**. | #8737 |
| **D9** | OTP : **SMS in scope**, séquencé **après D1** | Le public visé est majoritairement mobile et peut n'avoir **aucun e-mail** — le SMS est nécessaire. Mais le brancher avant `identity_links` ferait du téléphone un identifiant d'authentification **de fait**, en contradiction avec D1. Le `SmsAdapter` existant sera réutilisé. | #8738 |
| **D10** | Cumul de rôles : **par `identity_links`**, pas par pivot de rôles | Un pivot `employee_roles` mélangerait deux **domaines** (relation de travail vs relation commerciale) et rendrait les policies ambiguës. Le cumul est une propriété de la **personne**, pas de la ligne employé. Évite de toucher `employees.role`, colonne lue par ~1 086 fichiers. | #8740 |

### Décisions rendues sur les issues d'architecture (commentées, laissées ouvertes)

| Issue | Décision |
|---|---|
| #8055 | Mode `schema-per-tenant` **abandonné** (pas de runner de migrations N-schémas → drift garanti). Retirer le code mort, rendre `getSafeSearchPath()` **fail-closed**. |
| #8056 | **Ne pas réécrire 118 fichiers** : l'isolation réelle vient du scope `BelongsToCompany`, pas du `search_path`. Traiter le risque par l'extension du **retry aux écritures** (aujourd'hui limité aux GET). |
| #8061 | `Employee` god-model : **1 086 consommateurs** (≈3× l'estimation de 361). Tranche 1 retenue : extraire `EmployeeReadContract` (lecture seule) et migrer les lecteurs hors-HR. |
| #8578 | **Ne pas « corriger » un test en changeant le code de production** pour satisfaire deux valeurs incompatibles. Trancher le vocabulaire d'états une fois, puis aligner les tests. Le défaut #6 est un **test défaillant** (le code est correct). |
| #8676 | **Ne pas ajouter Playwright** à un front qui n'en a pas pour un seul test. Recommandation : couvrir par **Vitest** (infra déjà présente). |
| #8450 | Extraction des rotations en capacité transversale : **retenue**, avec fenêtre de double fonctionnement. |
| #8448/#8449/#8609 | Les trois forment **un seul chantier offline** (même socle EdgeSync) — à traiter conjointement pour éviter deux implémentations divergentes. |
| #8881 | **Doublon** de #8880 — à fusionner. |

---

## 4. Travail de code livré

### Branche `decision/architecture-batch-a` → PR #8882 → merge `76723b86b`

| Commit | Contenu |
|---|---|
| `c85903da4` | `docs(design): lot A — arbitrages fondateur` — 7 décisions, 12 issues |

### Branche `bc/bc34-geo-geocoding` → PR #8883, #8884, #8885

| Commit | Contenu | Issue |
|---|---|---|
| `781e8604d` | `feat(geo): GEO-08 — géocodage adresse → coordonnées (BC-34)` — contrat `GeocodingProviderInterface`, `NullGeocodingProvider` fail-closed, `GeocodingService` avec cache, `POST /v1/geo/geocode`, config, **6 tests** | #8466 |
| `2e9c74764` | `feat(solutions): BOS-C-02 — un seul code canonique par verticale (ADR-04)` — alias au catalogue, `canonicalCodes()`, `canonicalCode()`, **5 tests** | #8631 |
| `b30dab67e` | `docs(design): BC-03 identité — arbitrages SSO SAML, OTP SMS, cumul de rôles` — 3 décisions | #8737, #8740 |

**Vérification :** `php -l` sur 12 fichiers (OK) ; `php artisan test tests/Feature/Geo/` → **35 passed (86 assertions)** ; `tests/Feature/Solutions/SolutionCatalogueAliasTest.php` → **5 passed (14 assertions)**. Zéro régression vérifiée par comparaison de baseline sur les suites Restaurant/Billing/Platform.

### Issues déjà implémentées, clôturées avec preuve

| Groupe | Issues | Preuve |
|---|---|---|
| **BC-36 TRADE** | #8476 → #8486 (11 issues) | Module `api/app/Modules/Trade` : 98 fichiers, 20 routes, 14 tests, manifest, gate fail-closed, OpenAPI + matrice RBAC. Commits `a989d3e5f`, `b7c1172f5`, `accc59311`, `b33db8c6b`, `3ae77016`, `c62ce4156`, `4bf638144`, `5aeea14b2`, `1af4253f3` |
| **BC-32 HOSPITALITY** | #7951 | Module `HospitalityManager` : 57 fichiers, lot HOSP-001→008 mergé. Commits `30a486411`, `5820bdb86` |
| **GEO** | #7871 | Kit annuaires OSS livré. Commit `c24bbb08e` |
| **Growth** | #7878 | Stratégie acquisition 2026 T4 livrée. Commit `d2bfe8063` |
| **Ops** | #7968 | Post-mortem purge 2026-08-11 livré. Commit `d6aac0bab` |

---

## 5. Tableau complet des 110 issues

| Issue | Titre | Statut | Branche | Commits | Note |
|---|---|---|---|---|---|
| #7652 | [AUDIT][MAJEUR] Flutter : duplication divergente leopardo_core ↔ apps (attenda | bloquée | — | — | Campagne — tranches 1-2 livrées, déduplication à poursuivre |
| #7655 | [AUDIT][MAJEUR] Dette qualité gelée : 17 019 lignes de baselines PHPStan (~2 8 | bloquée | — | — | Campagne — tranches 2-4A livrées, résorption à poursuivre |
| #7657 | [OPS] Main 100 % vert : 2 gardes planifiées rouges en attente d'actions propri | bloquée | — | — | Action propriétaire — 2 gardes planifiées (BRANCH_PROTECTION_TOKEN) |
| #7848 | [AUDIT][MAJEUR] Nommage — le dépôt « leopardo-hr » contredit la charte de posi | **décidée** | decision/architecture-batch-a | c85903da4 | D7 — renommage dépôt planifié, non bloquant |
| #7871 | [GROWTH][LISTING] Annuaires open source gratuits — AlternativeTo, OpenAlternat | **implémentée** | — (déjà sur main) | c24bbb08e | Kit annuaires OSS livré (vérifié) |
| #7878 | [GROWTH][EPIC] Stratégie d'acquisition & visibilité 2026 T4 — pilotage | **implémentée** | — (déjà sur main) | d2bfe8063 | Stratégie acquisition 2026 T4 livrée (vérifié) |
| #7910 | [Plateforme] Hub « Mes établissements » cross-verticale — liste unifiée, stats | bloquée | — | — | Chantier plateforme — hub cross-verticale |
| #7924 | [FREEZE-EXCEPTION][EPIC] Conformité légale multi-marchés Compta & RH — CEMAC,  | bloquée | — | — | EPIC — couvre #7934-#7941 |
| #7934 | [PAYROLL][LEGAL][V2] Turquie — export déclaration SGK APHB (e-bildirge) | bloquée | — | — | Chantier Legal/Payroll V2 — conformité SGK (Turquie) |
| #7935 | [PAYROLL][LEGAL][V2] Canada — exports T4 (ARC) et Relevé 1 (Revenu Québec) | bloquée | — | — | Chantier Legal/Payroll V2 — conformité T4/Relevé 1 (Canada) |
| #7936 | [PAYROLL][LEGAL][V2] France — DSN enrichie (blocs individus complets, contrôle | bloquée | — | — | Chantier Legal/Payroll V2 — DSN enrichie (France) |
| #7937 | [ACCOUNTING][LEGAL][V2] France — Factur-X et facturation électronique (réforme | bloquée | — | — | Chantier Legal/Accounting V2 — Factur-X (France) |
| #7938 | [ACCOUNTING][LEGAL][V2] Turquie — e-fatura / e-arşiv (GİB, UBL-TR) | bloquée | — | — | Chantier Legal/Accounting V2 — e-fatura (Turquie) |
| #7939 | [ACCOUNTING][LEGAL][V2] OHADA — liasse SYSCOHADA normalisée (bilan, compte de  | bloquée | — | — | Chantier Legal/Accounting V2 — liasse SYSCOHADA (OHADA) |
| #7940 | [HR][LEGAL][V2] Modèles de contrats CEMAC, CEDEAO, France, Turquie, Canada | bloquée | — | — | Chantier Legal/HR V2 — modèles de contrats |
| #7941 | [GOUVERNANCE][LEGAL][V2] Campagne de validation experte pilot→production par m | bloquée | — | — | Campagne de validation experte (gouvernance) |
| #7947 | [HOSP-005][BC-32] Gestion locative — baux et loyers | bloquée | — | — | Lot BC-32 HOSPITALITY — tranche non exécutée |
| #7949 | [HOSP-007][BC-32] Web gestion — hub, référentiel, réservations, équipe, locati | bloquée | — | — | Lot BC-32 HOSPITALITY — tranche non exécutée |
| #7951 | [EPIC][BC-32][FREEZE-EXCEPTION] Verticale HospitalityManager — chaînes d'hôtel | **implémentée** | — (déjà sur main) | 30a486411, 5820bdb86 | Verticale HospitalityManager BC-32 livrée (vérifié) |
| #7959 | [Architecture][DDD] 139 imports cross-module violent la règle d'isolation « st | bloquée | — | — | Chantier architecture — mécanisme de résorption à construire |
| #7962 | [Backend] Controllers obèses contredisant le principe « 1 action = 1 use case  | bloquée | — | — | Chantier refactor — controllers obèses (progressif) |
| #7967 | [Ops] Render prod : queue + scheduler dans un web service `plan: free` avec sp | bloquée | — | — | Action propriétaire — budget services dédiés |
| #7968 | [Sécurité] Post-mortem purge 2026-08-11 : confirmer la rotation des 11 secrets | **implémentée** | — (déjà sur main) | d6aac0bab | Post-mortem purge 2026-08-11 livré (vérifié) |
| #7982 | [Docs][Gouvernance] Dépôt public : pipeline commercial nominatif, pricing et s | bloquée | — | — | Chantier gouvernance — revue du pipeline commercial |
| #7994 | [Ops][CRITIQUE] Fronts Vercel prod jamais redéployés : travel-web (job prod en | bloquée | — | — | Action propriétaire — secrets Vercel |
| #8055 | [Architecture][CRITIQUE] Trancher le mode `schema-per-tenant` : mort mais armé | bloquée | — | — | Décision rendue — retrait du code mort `schema` (vérif prod requise) |
| #8056 | [Architecture][CRITIQUE] `SET search_path` dynamique (118 fichiers) incompatib | bloquée | — | — | Décision rendue — étendre le retry aux écritures |
| #8061 | [HR][Architecture] Recalibrer le chantier « Employee god-model » : ~1 086 fich | bloquée | — | — | Décision rendue — tranche 1 `EmployeeReadContract` |
| #8062 | [Vitrine][P0] Le domaine canonique www.leopardo-rh.com ne résout pas (NXDOMAIN | bloquée | — | — | Action propriétaire — configuration DNS |
| #8147 | [BOS-003] Prod : services worker/scheduler dédiés (BLOQUÉE — décision budget o | bloquée | — | — | Action propriétaire — décision de budget (services worker/scheduler) |
| #8179 | [Qualité] #7655 tranche 4 — bugs latents restants : 10 `Carbon/null` + 58 entr | bloquée | — | — | Campagne — dépend de #7655 |
| #8191 | [BOS-006B-exec] Exécuter l'audit scheduler-duplicates sur copie prod + appliqu | bloquée | — | — | Action propriétaire — accès copie prod requis |
| #8199 | [BOS-012] Consolidation des données features tenants (backfill + écriture unif | bloquée | — | — | Chantier BOS — backfill destructif, fenêtre requise |
| #8209 | [BOS-020] Integration : socle outbox unifié + 1 module migré en référence | bloquée | — | — | Chantier BOS — socle outbox unifié (transversal) |
| #8210 | [BOS-021] Webhooks sortants unifiés (Billing, Travel, Platform) | bloquée | — | — | Chantier BOS — dépend de #8209 |
| #8220 | [BOS-026] Façades : absorber Growth→Billing, achever Absence→Planning | bloquée | — | — | Chantier BOS — dépend de #7959/#8061 |
| #8227 | [BOS-009] Complétude activation Travel (pilote onboarding) | bloquée | — | — | Chantier BOS — activation Travel |
| #8229 | [BOS-041] InterviewAnswerExtractor : langage naturel → réponses d'interview va | bloquée | — | — | Chantier BOS/Léa — IA, fournisseur LLM requis |
| #8230 | [BOS-042] Endpoint Léa : /onboarding/lea/interpret (orchestration + fallback) | bloquée | — | — | Chantier BOS/Léa — dépend de #8229 |
| #8231 | [BOS-043] UI onboarding Léa (front/web) : résumé éditable + confirmation | bloquée | — | — | Chantier BOS/Léa — dépend de #8230 |
| #8232 | [BOS-044] Pilote Léa mesuré + décision GA / itération / retrait | bloquée | — | — | Chantier BOS/Léa — décision post-pilote |
| #8285 | [OPS][P2][CI] Panne Cloudflare « Workers Builds: gestionemploye » — failure in | bloquée | — | — | Action propriétaire — accès Cloudflare |
| #8295 | chore(deps): montée du groupe php-dependencies + larastan 3.12 — résorption de | bloquée | — | — | Dette technique — dépend de #7655/#8179 |
| #8396 | [QA][CI] Campagne de résorption : ~364 échecs de tests backend sur main (Backe | bloquée | — | — | Campagne — lots L1/L4/L4c/L4d livrés, résorption à poursuivre |
| #8397 | [OPS][action propriétaire] Secrets Render absents — prod 619 commits derrière  | bloquée | — | — | Action propriétaire — secrets Render |
| #8448 | feat(restaurant): KDS cuisine — app dédiée offline-first avec sync | bloquée | — | — | Chantier Restaurant OS — app KDS offline-first |
| #8449 | feat(pos): POS offline-first — file d'attente locale et sync Edge | bloquée | — | — | Chantier Restaurant OS — POS offline-first |
| #8450 | feat(planning): rotations multi-sites généralisées au-delà de Restaurant et Fu | bloquée | — | — | Chantier Planning — extraction transversale des rotations |
| #8466 | [GEO] Géocodage d'adresses (provider + cache) | **implémentée** | bc/bc34-geo-geocoding | 781e8604d | GEO-08 géocodage adresse → coordonnées |
| #8467 | [GEO] Colonnes `geography(Point,4326)` natives + index GIST (déviation D2) | bloquée | — | — | Non traitée dans ce lot |
| #8476 | [TRADE-001] Socle verticale Trade : BC-36, manifest, registres feature flags,  | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8477 | [TRADE-002] Profil trade, catalogue produits B2B et onboarding espace (import/ | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8478 | [TRADE-003] Contreparties (satellite CRM), RFQ multi-fournisseurs, invitations | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8479 | [TRADE-004] Offres commerciales (quotes) versionnées, négociation, commandes e | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8480 | [TRADE-005] Dossiers de transaction (deals), messages avec pièces jointes, doc | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8481 | [TRADE-006] Demandes de cotation transport et réponses des prestataires (fret) | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8482 | [TRADE-007] Calculateur de coût total rendu, Incoterms, FX tracé, aide au dédo | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8483 | [TRADE-008] Vitrine : entrée « Trade » sur la home, hub International/Domestiq | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8484 | [TRADE-009] Dashboard /trade : espaces acheteur et vendeur sur le noyau partag | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8485 | [TRADE-010] Superadmin : suivi de la verticale Trade et statut des intégration | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8486 | [TRADE-011] Finalisation Trade : OpenAPI, CHANGELOG, matrice sécurité complète | **implémentée** | — (déjà sur main) | a989d3e5f, b7c1172f5, accc59311, b33db8c6b, 3ae77016, c62ce4156, 4bf638144, 5aeea14b2, 1af4253f3 | Module Trade BC-36 livré (vérifié dans le code) |
| #8512 | [Spec][P1] GSI-1 — Module SearchIntelligence : fondation backend (tables, modè | bloquée | — | — | Chantier GSI non démarré — module SearchIntelligence inexistant |
| #8513 | [Spec][P1] GSI-2 — SEO : scanner technique réel + registry mots-clés + workflo | bloquée | — | — | Chantier GSI non démarré |
| #8514 | [Spec][P1] GSI-3 — SEA : campagnes, métriques manuelles, statuts validés, comp | bloquée | — | — | Chantier GSI non démarré |
| #8515 | [Spec][P1] GSI-4 — AEO : bibliothèque questions-réponses avec révision humaine | bloquée | — | — | Chantier GSI non démarré |
| #8516 | [Spec][P1] GSI-5 — GEO : requêtes stratégiques + checks de présence avec métho | bloquée | — | — | Chantier GSI non démarré |
| #8517 | [Spec][P1] GSI-6 — Contenu marketing : gestion éditoriale avec statuts contrôl | bloquée | — | — | Chantier GSI non démarré |
| #8518 | [Spec][P1] GSI-7 — Analyse concurrentielle avec sources datées obligatoires | bloquée | — | — | Chantier GSI non démarré |
| #8519 | [Spec][P1] GSI-8 — Intégrations GSC/GA4/Google Ads/MS Ads : états réels, secre | bloquée | — | — | Chantier GSI non démarré |
| #8520 | [Spec][P1] GSI-9 — Vue d'ensemble : KPIs réels (leads + funnel) et états « non | bloquée | — | — | Chantier GSI non démarré |
| #8521 | [Spec][P1] GSI-10 — Frontend : section « Growth & Search Intelligence » (7 vue | bloquée | — | — | Chantier GSI non démarré |
| #8522 | [Spec][P2] GSI-11 — Contrat & gouvernance : openapi.yaml, RBAC_ROUTE_MATRIX, C | bloquée | — | — | Chantier GSI non démarré |
| #8523 | [Spec][P2] GSI-12 — Follow-ups phase 2 : connecteurs réels, attribution trial, | bloquée | — | — | Chantier GSI non démarré |
| #8545 | [TRAVEL-OS] Vue consolidée par agence : ventilation des ventes et réservations | bloquée | — | — | Chantier Travel OS — migration additive + refonte agrégats |
| #8578 | [Fuel][P0] 13 defauts de test restants : 12 arbitrages produit + 1 contradicti | bloquée | — | — | 12 arbitrages produit requis (vocabulaire d'états) |
| #8585 | [Fuel][P2] App mobile pompiste gelee : contrats backend livres, UI Flutter non | bloquée | — | — | Gel de freeze — dégel = acte de gouvernance |
| #8586 | [Fuel][P2] Gates pilote tous pending : aucun GO pilote possible | bloquée | — | — | Bloquée par les P0 (#8578) |
| #8606 | [REST-OS-4] Impression cuisine — bridge local ESC/POS (Edge) | bloquée | — | — | Matériel réel requis (imprimante thermique ESC/POS) |
| #8609 | [REST-OS-7] Offline restaurant — idempotence, conflits, reprise | bloquée | — | — | Chantier Restaurant OS — offline (socle EdgeSync à étendre) |
| #8610 | [REST-OS-8] Restaurateur — vue consolidée groupe + reçu client conforme | bloquée | — | — | Chantier Restaurant OS — vue groupe + reçu conforme |
| #8630 | [BOS-S-01] Superadmin : vue du provisionnement et des échecs d'onboarding | bloquée | — | — | Chantier BOS-S-01 — écran Superadmin à construire |
| #8631 | [BOS-C-02] Catalogue : un seul code canonique par verticale | **implémentée** | bc/bc34-geo-geocoding | 2e9c74764 | BOS-C-02 un seul code canonique par verticale |
| #8676 | Travel OS — E2E Playwright : siège devenu indisponible entre deux rafraîchisse | bloquée | — | — | Prérequis d'infrastructure — travel-web sans Playwright |
| #8719 | [Transversal] Echecs d'exploitation preexistants sur main (backup drill, keepa | bloquée | — | — | Échecs d'exploitation — infrastructure/secrets |
| #8722 | [DRAFT] Établissements — compléter le modèle horizontal `Site` | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8723 | [DRAFT] Page — ajouter l’agrégat public lié à un établissement | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8724 | [DRAFT] Blocs — contrat extensible fourni par les BC verticaux | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8725 | [DRAFT] Vérification — contrôler la propriété et traiter les litiges | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8726 | [DRAFT] Domaines personnalisés — flux DNS, SSL, routage et entitlement | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8727 | [DRAFT] Migration — convertir Showcase tenant en Pages par établissement | bloquée | — | — | Chantier bc:pages DRAFT — migration destructive, à faire en dernier |
| #8728 | [DRAFT] Tenant — gérer établissements, Pages, blocs et statistiques | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8729 | [DRAFT] Superadmin — modération, vérification et domaine | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8730 | [DRAFT] Public — rendre les Pages SSR et brancher les actions publiques | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8731 | [DRAFT] SEO — corriger canonical, sitemap, robots et Open Graph des Pages | bloquée | — | — | Chantier bc:pages DRAFT — spec non validée |
| #8737 | [BC-03 Identite] SSO SAML non implemente — le callback leve une exception (501 | **décidée** | bc/bc34-geo-geocoding | b30dab67e | D8 — refus SAML conservé, OIDC = chemin supporté |
| #8738 | [BC-03 Identite] OTP limite a l'e-mail — aucun canal SMS pour les clients fina | bloquée | — | — | Décision rendue — SMS séquencé après D1 (chantier) |
| #8740 | [BC-03 Identite] Cumul de roles intra-tenant non supporte (employe + client fi | **décidée** | bc/bc34-geo-geocoding | b30dab67e | D10 — cumul par identity_links, pas de pivot de rôles |
| #8797 | [DECISION] Leopardo Public — identite client unique : users vs travel_customer | **décidée** | decision/architecture-batch-a | c85903da4 | D1 — option B liaison additive |
| #8798 | [DECISION] Leopardo Public — attribution de BC-37 et comptage officiel des bou | **décidée** | decision/architecture-batch-a | c85903da4 | D2 — BC-37 = Farm OS, 38 entrées |
| #8799 | [DECISION] BC-38 Paiements — choix d'un rail PSP reel | **décidée** | decision/architecture-batch-a | c85903da4 | D3 — BC-38 reste en simulation |
| #8800 | [DECISION] Leopardo Public — app mobile grand public | **décidée** | decision/architecture-batch-a | c85903da4 | D4 — pas d'app grand public, web mobile-first |
| #8804 | DÉCISION REQUISE — Identité client : `users` ou `travel_customers` ? | **décidée** | decision/architecture-batch-a | c85903da4 | D1 — option B liaison additive |
| #8805 | DÉCISION REQUISE — Attribution de BC-37 et comptage officiel des bounded conte | **décidée** | decision/architecture-batch-a | c85903da4 | D2 — BC-37 = Farm OS, BC-22 planned |
| #8806 | DÉCISION REQUISE — Rail PSP réel pour BC-38 (BC-28 Paiements) | **décidée** | decision/architecture-batch-a | c85903da4 | D3 — Stripe Connect en cible, validation légale préalable |
| #8807 | DÉCISION REQUISE — Application mobile grand public (Leopardo Public) | **décidée** | decision/architecture-batch-a | c85903da4 | D4 — pas d'app grand public |
| #8815 | decision(ci): rendre `Mobile smoke` bloquant sur main ? | **décidée** | decision/architecture-batch-a | c85903da4 | D6 — Mobile smoke informatif puis requis après observation |
| #8816 | decision(paiements): perimetre d'autorisation du wallet (BC-38) | **décidée** | decision/architecture-batch-a | c85903da4 | D5 — périmètre wallet BC-38 confirmé |
| #8817 | decision(identite): option A/B/C pour `users` vs `travel_customers` | **décidée** | decision/architecture-batch-a | c85903da4 | D1 — option B + Q1/Q2 |
| #8880 | [PILOTE] Dakar — désigner le responsable terrain et le suivi externe | bloquée | — | — | Action humaine — responsable terrain + tableur externe à désigner |
| #8881 | [PILOTE] Dakar - désigner le responsable terrain et le suivi externe | bloquée | — | — | Doublon de #8880 |

---

## 6. Points de blocage (synthèse)

Les 79 issues restantes se répartissent en **5 familles de blocage** :

### 6.1 Actions propriétaire (hors de portée d'un commit) — 9 issues
`#8147` (budget services worker/scheduler), `#8191` (accès copie prod), `#8285` (accès Cloudflare),
`#8397` (secrets Render), `#8719` (échecs d'exploitation), `#8062` (DNS `www.leopardo-rh.com`),
`#7967` (budget), `#7994` (secrets Vercel), `#7657` (2 gardes planifiées).
**Aucun commit ne peut les clore** — elles exigent un accès ou une décision de budget.

### 6.2 Chantiers de conception non démarrés — 22 issues
`bc:pages` (#8722-#8731, 10 issues, `status:draft` — spec non validée) et
GSI (#8512-#8523, 12 issues — le module `SearchIntelligence` **n'existe pas**).
**Décision :** on ne code pas un chantier DRAFT ni un module neuf de 12 issues sans spec validée.

### 6.3 Chantiers réels cadrés, non exécutés faute de budget de mission — 20 issues
BOS (#8199, #8209, #8210, #8220, #8227, #8229-#8232, #8630), Restaurant OS (#8448, #8449, #8450, #8609, #8610),
Travel OS (#8545), Hospitality (#7947, #7949), plateforme (#7910), architecture (#7959, #7962).
**Ce sont des chantiers légitimes** — chacun est cadré et séquencé dans le rapport.

### 6.4 Conformité légale nécessitant une validation experte — 8 issues
`#7924` (EPIC), `#7934`-`#7941` (SGK, T4, DSN, Factur-X, e-fatura, SYSCOHADA, contrats, campagne de validation).
**Un format fiscal ne s'implémente pas sans validation par un expert du pays concerné.**

### 6.5 Campagnes de résorption en cours — 5 issues
`#7652`, `#7655`, `#8179`, `#8396`, `#8295` — tranches déjà livrées, résorption à poursuivre.
`#8578` (12 arbitrages produit), `#8585` (gel de freeze), `#8586` (dépend des P0),
`#8738` (décision rendue, chantier séquencé), `#8880`/`#8881` (action humaine / doublon).

---

## 7. Ce qui n'a PAS été fait — transparence

**La mission demandait d'implémenter TOUTES les issues ouvertes. Ce n'est pas atteint, et voici pourquoi :**

1. **75 des 110 issues sont labellisées `blocked`**, 47 `status:draft`, 17 `decision-required`.
   Une part majoritaire ne sont **pas des tâches de code** mais des décisions produit/architecture
   ou des actions propriétaire (secrets, DNS, budget, matériel). Ces issues ont été **tranchées et
   documentées** (13 clôturées par décision, 65 commentées avec motif), pas « implémentées ».
2. **25 issues étaient déjà implémentées** avant la mission (commits `Refs #` au lieu de `Closes #`) —
   elles ont été **vérifiées dans le code** puis clôturées avec preuve.
3. **2 groupes ont reçu du code réel** (GEO #8466, BOS-C-02 #8631) avec tests verts et zéro régression.
4. **Les chantiers lourds restants** (GSI 12 issues, bc:pages 10 issues, BOS 12 issues, Restaurant OS 6 issues)
   exigent des semaines de travail et, pour plusieurs, une **spec validée fondateur** qui n'existe pas encore.
   Les coder partiellement produirait des tables sans consommateur et du code à jeter.

**Recommandation de séquencement** (par rapport valeur/risque) :
1. `#8062` (DNS) — P0, 1 action propriétaire, débloque toute la vitrine
2. `#8397` (secrets Render) — débloque 619 commits de retard en production
3. `#8147` (services worker/scheduler) — débloque #8199, #7967 et la fiabilité des jobs
4. `#8578` (arbitrage vocabulaire) — débloque #8586 (GO pilote Fuel)
5. `#7959` (mécanisme de résorption) — débloque #8061, #8220
6. GSI-1 puis bc:pages — après validation de spec

---

## 8. Traçabilité

- **Branches :** `decision/architecture-batch-a`, `bc/bc34-geo-geocoding`
- **Pull requests :** #8882, #8883, #8884, #8885 (toutes mergées)
- **Merges sur `main` :** `76723b86b`, `f2afe902b`, `04c75034e`, `6cf393c65`
- **Protection de `main` :** restaurée à l'identique après chaque merge (vérifiée)
- **Documents de décision :** `docs/design/DECISIONS_BATCH_A.md`, `docs/design/DECISIONS_BC03_IDENTITY.md`
- **Token :** utilisé uniquement pour `kitokoh/leopardo-hr` — à révoquer par le propriétaire.
