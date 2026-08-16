# Session QA — Expert 20 : Consolidation + Audit 360° + Implémentation (2026-08-16)

> Agent : Senior QA / Expert Software Engineer | Portée : Phase 0 (consolidation),
> Phase 1 (audit 360°), Phase 3 (implémentation des findings). Repo : kitokoh/leopardo-hr.

---

## 1. Phase 0 — Consolidation (branches ouvertes + CI main)

| Action | Résultat |
|---|---|
| PRs ouvertes au départ (6) | **#4276** (mobile locale), **#4277** + **#4280** (i18n API batches 1/2), **#4275** (errors.php ×4), **#4279** (docs expert14) **mergées** ; **#4270** (ADR-0014 billing) **fermée** par agent-360 (conflit avec canonique 99/250, décision produit requise) |
| Conflit add/add #4279 | Résolu en concaténant les deux bilans expert14 (audit + round 2) |
| Branches nettoyées | 71 → 2 branches (dont 1 active d'un autre agent) ; supprimées : superseded (4141, 4176-non-clickable, 4178-docs-link, 4180×2, 4181-toast, 4190-empty, 4124, 4151-regression-suite, 4151-test-sites, 4191-batch2, expert14b) + têtes de PRs mergées |
| CI main | Aucun échec sur les 100 derniers runs (annulations = superseded). Branche protégée (5 checks requis). File GitHub Actions saturée (#3545) |
| Audits précédents vérifiés | expert19 : 14/14 issues implémentées ; expert18 : ~12/14 (restants = prod figée ou gros refactors) |

## 2. Phase 1 — Audit 360° (constats live + statiques)

### Constats live prod (v4.23.5 — prod figée, #3767)

| Endpoint | Résultat | Issue |
|---|---|---|
| `POST /api/v1/trial/signup` (payload valide) | **500 en 61,2 s** (reproduit) | #3879 |
| `GET /api/v1/i18n/catalog` | 500 | #3882 |
| `POST /platform/auth/login` admin@leopardo-rh.com | 401 INVALID_CREDENTIALS | #2646 |
| `GET /api/v1/demo-users` | 404 | #2646 |
| `GET /api/v1/admin/dashboard/stats` | 404 | #2812 |
| `GET /api/v1/supported-countries` | 404 | #2813 |
| `https://leopardo-rh.com` | NXDOMAIN (vitrine DOWN) | #3452 |
| `https://leopardo.vercel.app` | **200 mais autre produit** (« Leopardo Marketing IA » espagnol) | PA2-MKT-008 |

### Issues créées (14, format spec-kit, label `qa-audit-2026-08-16`)

| # | Surface | Sévérité | Sujet |
|---|---|---|---|
| 4292 | API | P2 | Résiduel i18n #4191/#3237 — ~20 messages FR/EN codés en dur (PartnerDashboard `localized_message` FR, SSO, Google Auth, Billing, PlatformUser, abort/validators) |
| 4293 | API | P2 | errors.php — 13 clés manquantes tr/ar (clé brute aux tenants) + **PHP invalide sur main** (`];` manquant ×4, ParseError) |
| 4294 | API | P2 | PayrollRunController — `localized_message = $e->getMessage()` (fuite FR en dur ×4) |
| 4296 | API | P3 | 2 jobs sans `failed()` — SendTrialDripEmailJob (revenu), PublishScheduledPostJob |
| 4297 | API | P3 | 4 tests Platform avec fixtures plans legacy (Starter 10, Business 149/100, Pro 99/990) |
| 4298 | API/docs | P3 | openapi.yaml — 18 paths Edge double-préfixés `/api/v1` + drift ~208 routes |
| 4299 | Web | P2 | case-studies (liste + détail) 100% FR + CaseStudyClient mort |
| 4300 | Web | P2 | /contact (23 lignes FR) + badges /pricing FR + metadata homepage FR |
| 4301 | Web | P2 | Guides rendus **vides en SSR** (MainLayout `return null` si !isMounted) |
| 4302 | Web | P3 | CaseStudyClient.tsx mort + dark mode résiduel (blog box, success flash) |
| 4303 | Mobile | P2 | Résiduel #4194 — 113 chaînes FR (history_screen ×3 apps, smart_attendance, pending_sessions) |
| 4304 | Mobile | P2 | Résiduel #3867 — deep links manifest-only, aucune résolution runtime ; manager/hr sans intent-filters |
| 4305 | Admin | P2 | Résiduel #4206 — 15 vues sur 22 100% FR |
| 4306 | Admin | P3 | MetricCard.vue commun mort (résiduel #4182) |

### Positif (vérifié résolu)
hreflang/alternates (#3250), `<html lang>` SSR (#4173), checkout i18n (#4287), `decimalPattern('fr')` → 0 (#4197), orphelins #3812 → 0, tokens legacy #4200 → 0, glass tokens définis, SyncService/URLs #3867 (partiel : deep links manquants → #4304).

## 3. Phase 3 — Implémentations (8 issues, 8 PRs)

| PR | Issue | Sujet | Validation |
|----|-------|-------|-----------|
| #4350 | 4306 | Suppression `common/MetricCard.vue` (0 import) | rg 0 réf. |
| #4351 | 4302 | CaseStudyClient mort + dark mode (blog box, success flash) | tsc/eslint/jest 425 OK |
| #4352 | 4298 | openapi Edge dé-préfixé (18 paths) + garde coverage `spec_form()` | guard 644/723, drift 0, mirror+S DKs régénérés, redocly OK |
| #4353 | 4293 | errors.php : `];` restauré ×4 + 13 clés tr/ar + `LangCatalogParityTest` | parité 58=58=58=58, test Unit |
| #4354 | 4296 | `failed()` sur SendTrialDripEmailJob + PublishScheduledPostJob | test étendu 5 jobs |
| #4355 | 4294 | PayrollRunController localisé (api_errors/errors ×4) | test double-validate EN/TR |
| #4357 | 4297 | Fixtures plans canoniques (4 tests Platform) | rg 0 legacy |
| #4358 | 4301 | Guides SSR non vide (MainLayout sans null gate) + dark mode synchrone | tsc/eslint/jest OK |

## 4. Découvertes critiques

1. **PHP invalide sur main** : la résolution de conflit `442d5138` (merge #4275) a supprimé le `];` de `lang/errors.php` ×4 → ParseError sur tout `__('errors.*')` au prochain déploiement. Hotfix d'un autre agent + PR #4353 (clés tr/ar + garde).
2. **La file CI saturée a laissé passer le merge #4280 avec un check échoué** — la branche protégée n'est efficace que si les runs s'exécutent.
3. **`extract_routes()` vs `parse_routes()`** : préfixes incohérents (EdgeSync `api/v1` absolu) — le YAML portait `/api/v1/edge/*` (mauvais pour les consommateurs) pour satisfaire la passe forward ; corrigé par `spec_form()`.

## 5. Leçons

1. **Vérifier les findings d'un scout contre main À JOUR** : le scout auditait un main périmé (checkout FR « résiduel » déjà corrigé par #4287, guides/métriques à re-vérifier).
2. **Un merge « propre » (0 conflit) peut livrer du PHP invalide** : toujours linter les fichiers lang après résolution de conflit.
3. **Les `Log::spy()` ne capturent pas `Log::channel(...)`** — pattern failed() en `Log::error` nu (compat test #4205).
4. **La branche EST le lock** : vérifier `ls-remote origin fix/<n>-*` avant de pousser un claim (protocole #2400) ; les merge-bots fusionnent en ~75 s.
5. **Rate limit partagé** : le token ghp est consommé par tous les agents — préférer `git`/WebFetch aux appels API, batcher avec retries.

## 6. Recommandations

1. Débloquer la prod (#3767/#3545) : tous les P1 restants sont des symptômes de prod figée (v4.23.5).
2. Merger les 8 PRs (#4350-#4358) quand CI verte (ordre : #4353 → #4355 → reste).
3. Décision produit requise : ADR-0014 (Operations 79€/200 vs canonique 99€/250) — ouvrir une issue dédiée avec validation propriétaire.
4. Prochains lots : #4292 (i18n API résiduel), #4299/#4300 (web i18n), #4303/#4304 (mobile), #4305 (admin i18n lot 5+).
