# REGISTRE DES SCENARIOS DE TESTS

## Objectif

Fournir une source de verite unique  pour savoir:

- quelles surfaces fonctionnelles doivent etre testees
- dans quel document de scenarios elles sont decrites
- quel workflow CI les execute
- quels artefacts doivent etre produits avant un deploiement

## Regle de gouvernance

Toute nouvelle fonctionnalite, extension de parcours critique ou changement de comportement dans:

- `api/`
- `front/mobile/`
- `front/admin-dashboard/`

doit mettre a jour:

1. le document de scenarios du domaine
2. ou ce registre si le domaine, le workflow, les artefacts ou la criticite changent

Le workflow `Governance Gates` bloque la PR si la surface fonctionnelle change sans mise a jour du registre ou du document de scenarios associe.

## Matrice canonique

| Domaine | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| API backend | `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` | `Tests - Leopardo RH` | JUnit unit/feature, logs, quality summary, coverage clover + HTML | Obligatoire |
| Mobile Flutter | `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md` | `Tests - Leopardo RH` | `test-results.json`, `lcov.info`, quality summary, smoke APK | Obligatoire si `front/mobile/**` change |
| Web admin | `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `Web CI - Leopardo Admin` | rapport Playwright HTML, JUnit Playwright, traces, screenshots, videos en echec | Obligatoire si `front/front/admin-dashboard/**` change |
| Web vitrine / manager | `front/web/src/modules/vitrine/` + `CHANGELOG.md` | `Web Marketing CI - Leopardo Public` | lint Next.js, build Next.js, locale rail valide, metadata stables | Obligatoire si `front/web/**` change |
| Gouvernance repo | `tools/check-governance.ps1` + ce registre | `Tests - Leopardo RH` | journal CI, verifications changelog/scenarios | Obligatoire |
| Deploiement main | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` | `Deploy - Leopardo RH` | healthcheck post-deploy, rollback hook si echec | Strictement bloque tant que les workflows requis ne sont pas verts |
| Release readiness | `docs/validation/RELEASE_READINESS_GATE.md` | GitHub Actions + `dev-hub/tools/release-readiness.ps1` | rapport readiness, inventaire tests, statut go/no-go | Obligatoire avant declaration production-ready |

## Definition "tests concluants"

Un SHA est deployable seulement si:

1. `Tests - Leopardo RH` est `success`
2. `Web CI - Leopardo Admin` est `success` si le SHA touche `front/front/admin-dashboard/**`
3. `Web Marketing CI - Leopardo Public` est `success` si le SHA touche `front/web/**`
4. les artefacts minimums du domaine existent
5. aucun job critique n'est `failure`, `cancelled` ou `timed_out`

## Politique artefacts

### Backend

- `backend-test-reports`
- `backend-quality-summary`
- `backend-quality-reports`
- `backend-coverage-summary`
- `backend-coverage-reports`

### Mobile

- `mobile-quality-summary`
- `mobile-test-reports`
- APK smoke si build mobile actif

### Web admin

- `playwright-report`
- `test-results/junit.xml`
- traces Playwright sur premier retry
- videos Playwright retenues en echec

### Web vitrine / manager

- logs de lint Next.js
- logs de build Next.js
- validation du locale rail public et des metadata au travers du build

## Evolution attendue

Quand un domaine gagne une feature significative, ajouter:

- le scenario nominal
- les refus RBAC / tenant
- les erreurs de validation
- les cas de resilience
- l'artefact CI attendu

## Extension 2026-05-07 - I18N enterprise partage

| Domaine transverse | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| I18N partage backend/web/mobile | `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` + `SCENARIOS_TEST_MOBILE_FLUTTER.md` + `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `I18N Enterprise` + workflows de surface | catalogues generes, checksums `versions.json`, validation locale, endpoint distant syntaxiquement valide | Obligatoire si `shared/i18n/**` ou une surface synchronisee change |

## Notes 2026-05-12

- v4.16.50 : Le seuil coverage mobile par defaut est un ratchet a `21%`, base sur la mesure GitHub Actions `21.85%`. La prochaine cible mobile est `25%`; ne pas augmenter sans nouvelle mesure verte.
- v4.16.49 : Les tests mobiles Plan 14 couvrent desormais navigation GoRouter, surfaces principales, contrats repositories via `ApiClient` mocke et baselines structurelles paie/conges. Le poste Windows local ne fournit pas `flutter`/`dart`; GitHub Actions reste la source de verite pour compiler et executer ces tests.
- v4.16.47 : Les benchmarks performance Plan 14 ajoutent des scripts k6 pour 100 employes simultanes, paie 500 employes et dashboard 10k employes. Les scenarios API doivent garder l'organigramme scope par tenant et le rapport mensuel attendance groupe par employe pour eviter les regressions de scans repetes.
- v4.16.61 : Le sitemap Next `/api/sitemap` liste aussi `/changelog`, `/privacy` et `/terms` pour suivre les routes publiques FR/EN/TR/AR.
- v4.16.60 : La vitrine expose `/changelog` (extrait public du changelog produit) ; le footer pointe vers `/pricing`, `/changelog`, `/blog`, `/privacy`, `/terms`. Le Web Marketing CI doit continuer a valider lint + build ; ajouter un smoke manuel ou E2E du lien « Changelog » si une suite Playwright vitrine est introduite.
- v4.16.29 : La vitrine `front/web` expose maintenant les pages legales `/privacy` et `/terms` en FR/EN/TR/AR avec RTL arabe. Les scenarios Web Marketing doivent verifier les liens footer, le rendu des routes et le changement de langue sur ces pages.
- v4.16.8 : Le cockpit `front/admin-dashboard` consomme maintenant `/platform/metrics/overview` pour les chiffres financiers globaux. Les scenarios web admin doivent verifier MRR, ARR, encaissements 30 jours, impayes et subscriptions sans recalculer ces agregats depuis des listes partielles.
- v4.16.7 : Le contrat `GET /api/v1/platform/metrics/overview` devient une surface plateforme critique pour le cockpit super-admin. Il doit rester protege par `super_admin_api`, exposer uniquement des agregats non nominatifs et rester tolerant aux tables billing absentes pendant les migrations progressives.
- v4.16.0 : Les annotations PHPDoc `@property`, `@return` et `@var Employee` ajoutees dans les modeles, services et controllers ne modifient aucun comportement runtime. Le helper `currentCompany()` est un remplacement fonctionnellement identique de `app('current_company')`. Le binding `LLMClient` dans AppServiceProvider preserve le meme comportement de selection provider. Aucun nouveau endpoint ni modification de contrat API.
- v4.16.1 : Extraction des appels inline `$request->user()->` dans 8 controllers supplementaires. Aucun changement de comportement ni de contrat API.
- v4.16.2 : Extraction des chaines `->fresh()->` nullables et ajout de null checks sur les relations. Aucun changement de comportement ni de contrat API.
- v4.16.27 : Annotations PHPStan Partie 5 — `@mixin` sur 16 Resources, `@property` sur 4 modeles Camera, `@property-read` sur 14 modeles, `@param/@return Builder<static>` sur 48 scopes. Aucun changement de comportement runtime.
- v4.16.3 : Guards `Schema::hasTable()` sur 8 migrations tenant + `$withinTransaction = false` sur migration public. Aucune modification de schema — uniquement idempotence des migrations existantes.

## Notes 2026-05-08

- Le workflow `Tests - Leopardo RH` ne doit pas lancer le job mobile uniquement parce que `.github/workflows/tests.yml` change. La dette mobile historique doit rester visible, mais elle ne doit bloquer une PR backend/admin/web que si `front/mobile/**` bouge vraiment.
- La gate `Backend Quality` doit rester veridique sur le code PHP touche par la PR. Tant que tout l'historique PHPStan n'est pas resorbe, privilegier un scope diff-aware plutot qu'un faux vert global ou un blocage hors perimetre.
- Le contrat d'auth plateforme (`/api/v1/platform/auth/*`, `role=super_admin`, `two_fa_enabled`, `202 TWO_FA_REQUIRED`) fait maintenant partie du perimetre admin critique et doit rester documente et teste.
- Les extensions attendance qui rendent la valeur terrain visible (impact business des anomalies, actions manager recommandees, rapport mensuel avec estimation paie, checklist go-live) font partie des scenarios API critiques et doivent rester couvertes par `Tests - Leopardo RH`.
- Le contrat health plateforme (`/api/v1/platform/companies/{company}/health`) est une surface v5.0 critique : il soutient adoption, retention et upsell, et doit rester teste avec isolation tenant et auth super-admin.
- La vue portefeuille health (`/api/v1/platform/companies/health`) est critique pour le pilotage commercial : elle doit garder MRR, repartition des risques et next action par client dans la CI backend.
- Le contrat abonnement plateforme (`/api/v1/platform/companies/{company}/subscription`) est critique pour la commercialisation : il doit rester fournisseur-agnostique et valider plan, statut et dates avant toute integration paiement.
- Le catalogue plans plateforme (`/api/v1/platform/plans`) doit rester teste afin que l'admin-dashboard ne hardcode jamais les `plan_id` ou les limites de packaging.
- Le cockpit admin v5.0 doit afficher les donnees reelles du portefeuille, du detail health, des abonnements et des plans. Toute regression `front/front/admin-dashboard/**` sur ces vues doit rester couverte par build/Playwright.
- L'intake demandes clients de l'admin-dashboard doit rester branche sur `/api/v1/platform/company-requests` : filtres statut, compteurs et actions approuver/rejeter font partie du parcours commercial critique.
- L'accueil admin v5.0 ne doit plus dependre d'endpoints mockes `/admin/dashboard/*`; il synthetise les contrats plateforme existants pour garder un premier ecran exploitable.
- L'approbation d'une demande client doit verifier le provisioning complet : company publique, manager principal tenant, invitation et `approved_company_id`.
- v4.16.28 : CI/CD Hardening Partie 6 — seuil coverage 40%, PHPStan diff-gate elargi a tout `app/`, baseline auto-regen sur main avec delta, 3 suites E2E Playwright (navigation, accessibilite, error-handling). Aucun changement de comportement runtime.
- v4.16.67 : Plan 15 Batch 1 — declarations sociales CNAS DZ / CNSS MA, import employes CSV, compression gzip API. Nouveaux endpoints : `POST /social-declarations/cnas-dz`, `POST /social-declarations/cnss-ma`, `POST /employees/import`, `GET /employees/import-template`. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.71 : Iteration 9 — Audit logs UI (E9) avec filtres action/type/recherche, export CSV, panneau detail avec diff old/new values. E4 (recrutement Kanban) confirme DONE. Good first issues (I2) et release notes v0.1.0 (I5) documentes.
