# QA Session — Agent SWEQA-2 2026-08-16 (soirée) : Merge Drain, Main Green & Audit 360°

> Session : 2026-08-16 (17:25 → ~19:00 UTC) | Agent : SWEQA-2 | Repo : kitokoh/leopardo-hr
> Mandat : issues ouvertes + merge des branches → Phase 1 (audit 360°) → Phase 3 (implémentation).

---

## Phase 0/2 — Merge drain & stabilisation main

### P0 corrigé : ParseError `errors.php` ×4 sur main
Le littéral `, origin/main` (résidu de conflit de merge, commit 5335d43ae1) cassait
`api/lang/{en,fr,tr,ar}/errors.php` → **ParseError PHP sur TOUTES les requêtes chargeant le
catalogue** — cause racine probable d'une grande partie des 112+ échecs Feature de #4548.
Corrigé en local puis re-fix mergé via #4584 (agent concurrent) + artefact openapi
« par origin/main » nettoyé (PR #4646).

### Merge drain
- **PRs mergées par cet agent** (squash + suppression de branche, pattern repo) :
  - Docs : #4361, #4423, #4432, #4438, #4465, #4479, #4544
  - `fix(hr)` : #4560 (EmployeeService champs sensibles + test fillable, Closes #4307 —
    supersede #4308/#4550)
  - `fix(web)` : #4646 (suites jest vertes + SEO changelog + résidus merge, Closes #4639)
  - `fix(web)` : #4734 (a11y docs/blog + i18n case-studies, Closes #4705/#4706/#4703)
  - `fix(api)` : #4739 (ZKTeco api.manager, PATCH super-admin password, refreshToken {data},
    AIFeatureCheck standard — Closes #4692/#4695 volet/#4697/#4698)
  - `fix(ci)` : #4740 (branches mortes, analyze strict, gate pint réel, PROD_API_URL unifiée —
    Closes #4719/#4724/#4722/#4721)
  - `fix(admin)` : #4741 (LoginView i18n ×4 + opt-out _skipToast — Closes #4711 volet/#4713)
- **Doublons fermés** : #4550 (superseded par #4560), #4638 (déjà corrigé par #4564),
  #4694 (déjà corrigé : throttle:metrics), #4709 (déjà corrigé : StickyMobileCTA localisé).
- Le reste du drain (~60 PRs) a été mergé par les agents concurrents + poller.

### Suites locales rendues vertes (avant/après)
| Surface | Avant | Après |
|---|---|---|
| front/web jest | 14 failed | **534 passed** |
| front/web tsc/eslint | 1 erreur TS | **0** |
| admin eslint | 4 erreurs | **0** |
| admin vite build | — | **OK** |

> Infra locale : PHP 8.4 + PostgreSQL 14 (fsync off pour les tests) + Composer installés dans
> le sandbox. La suite Feature complète n'est pas exécutable de bout en bout sur ce FS (chaque
> test reconstruit le schéma MVP — ~1 min/test) ; validation ciblée par fichiers + CI.

---

## Phase 1 — Audit 360° (4 sous-agents)

4 audits parallèles (API Laravel / vitrine Next.js / admin Vue / mobile Flutter + CI), chaque
finding vérifié dans le code. **27 issues créées** (#4687 → #4724), format spec-kit
(`## Constat / ## Cause racine / ## Fix attendu / ## Critères d'acceptation`), labels
`QA` + `qa-audit-2026-08-16` + surface/type :

| Surface | P2 | P3 |
|---|---|---|
| API | EdgeNode license_key exposé (#4687), ApprovalController::history (#4688), renderer abort sans localized_message (#4689), littéraux FR contrôleurs (#4690) | ZKTeco middleware (#4692), /metrics throttle (#4694 → déjà fixé), password_hash fillable User/SuperAdmin (#4695), /kiosk GET throttle (#4696), AIFeatureCheck (#4697), refreshToken (#4698), code mort (#4699) |
| Web | Modules FR en dur (#4702), case-studies FR (#4703), BlogCard Archivé (#4704), docs search a11y (#4705), newsletter a11y (#4706) | metadata racine FR (#4707), code mort (#4708), StickyMobileCTA (#4709 → déjà fixé) |
| Admin | /fleet+/exports inaccessibles (#4710), Login/Settings/EdgeNodes i18n (#4711), auth store FR (#4712), double toast (#4713) | dates modal (#4714), realtime localhost (#4715), libellés relatifs FR (#4716) |
| Mobile/CI | UIBackgroundModes location (#4717), branches CI mortes (#4719), URLs legacy CI (#4720), deux vars URL (#4721) | providers morts (#4718), gate pint (#4722), duplications CI (#4723), v*-prod/analyze (#4724) |

**Verdict global** : surface API remarquablement défendue (isolation tenant fail-closed,
policies, throttles) — pas de P1. Les dettes majeures sont l'i18n mobile (~800 chaînes FR,
déjà tracké #4194/#4303), l'i18n admin (15+ vues FR) et la cohérence des contrats d'erreur.

---

## Phase 3 — Implémentations (7 PRs mergées, toutes validées localement)

| PR | Issues | Contenu |
|---|---|---|
| #4646 | #4639 | suites jest vertes (pricing mirror 79/66 ADR-0014, country #4476, modules stats, SignupForm fireEvent) + titre SEO changelog EN |
| #4734 | #4705/#4706/#4703 | a11y docs search + blog newsletter ; i18n page détail case-studies ×4 |
| #4739 | #4692/#4695/#4697/#4698 | api.manager CRUD ZKTeco ; PATCH super-admin password (corrige la régression #4695 de main) ; refreshToken {data} ; AIFeatureCheck standard |
| #4740 | #4719/#4724/#4722/#4721 | CI : branches mortes, analyze strict, gate pint réel, PROD_API_URL → PROD_API_BASE_URL |
| #4741 | #4711/#4713 | LoginView i18n ×4 ; opt-out _skipToast (Analytics/Growth/Exports) |

---

## Reste à faire (handoff)

1. **API** : #4687 (EdgeNode $hidden), #4688 (history envelope), #4689 (localized_message
   partout), #4690 (FR contrôleurs), #4696 (/kiosk GET), #4699 (code mort).
2. **Web** : #4702 (modules badges), #4704 (BlogCard Archivé), #4707 (metadata FR), #4708
   (code mort vitrine).
3. **Admin** : #4710 (routes fleet/exports), #4711 (Settings/EdgeNodes + auth store #4712),
   #4714/#4715/#4716.
4. **Mobile/CI** : #4717 (UIBackgroundModes location), #4718 (providers morts), #4720
   (URLs legacy CI — dépend de la résolution prod #3765/#3452), #4723 (duplications CI).
5. **Ops/prod** (accès externes requis) : #3765/#3766/#3767/#3771/#3452/#2812/#2813/#2906/
   #2646/#3259.
6. **Suite Feature complète** : à faire tourner en CI (runners rapides) une fois la famine
   #3545 résorbée ; les échecs de schéma PlatformUserApiTest (super_admins.status
   fixture/migrations) sont pré-existants sur main — investiguer.
