# QA Session — Expert 20 (2026-08-16) — Audit 360° + stabilisation main

Bilan de session agent : stabilisation main (2 régressions P0), audit global 360°, spécifications, issues.

## 1. Stabilisation main (P0) — main redevient vert

| Problème | Preuve | Fix |
|---|---|---|
| Merge #4275 (442d5138) : `api/lang/{fr,en,ar,tr}/errors.php` sans fermeture `];` → ParseError PHP → 500 sur toute réponse `__('errors.*')` | `php -l` : « Unclosed [ on line 3 » ×4 locales ; parents valides | Issue #4291 → PR #4295 |
| #4249/#4288 : `EmployeeService::create/update` passe `role/manager_role/status/company_id` (non fillable #3677) dans `create()`/`fill()` → perdus silencieusement → `EmployeeResource:79 getAppDownloadLink(null)` TypeError → **500 POST /api/v1/employees** ; company_id absent = hors tenant ; rôles jamais persistés | 4 tests `EmployeesRbacTest` rouges localement (PostgreSQL) | Issue #4307 → PR #4308 (pattern #3677/#4151, aligné sur les 6 autres sites déjà corrigés) |

Leçon : les merges de la journée sont passés **sans CI verte** (pipeline saturé, runs annulés — famine #3545) — c'est ainsi que du code cassé a atteint main. **Garde : avant de merger, vérifier qu'au moins Backend Coverage a tourné sur le SHA ; un merge sans check = risque P0.**

## 2. Audit 360° (5 surfaces, lecture seule + vérifications)

| Surface | Méthode | Résultat |
|---|---|---|
| API Laravel | Scout + revue manuelle + tests locaux | 2 P0 (stabilisés), 11 findings (localized_message brut, FR résiduels, route dupliquée, throttle manquant, commande morte, exceptions dupliquées, URLs prod en dur, tests manquants) |
| Vitrine Next.js | Scout | 1 P1 (accordéon FAQ cassé pages modules), 6 P3 (ancre morte, a11y, aria FR, Math.random ids, contact/case-studies FR) |
| Admin Vue | Scout | 1 P1 (SystemView 404), i18n lots 5+, 10 clés manquantes, MetricCard mort, catchs silencieux, XSS popup Fleet |
| Mobile Flutter ×6 | Scout | Smart-Attendance 100 % FR (P2), marketing fr_FR only, résidu #4197, leak subscription, catch muet |
| CI/edge/kiosk/docs | Scout + probes | 1 P1 (queues webhooks/audit jamais consommées), 3 P2 (deploy-staging famine, coverage-gate doublon, FIREBASE_APP_ID_HR), 3 P3 |

## 3. Issues créées (34, #4291→#4330) — méthode spec-kit (problème/impact/preuve/critères)

Feature : `.specify/features/qa-360-audit-expert20-2026-08-16/` (findings-registry.md, spec.md, tasks.md T001-T038).

- **P0** : #4291 (errors.php), #4307 (EmployeeService).
- **API** : #4309 (PayrollRun localized_message brut), #4310 (Attendance FR), #4311 (FR résiduels), #4312 (SSO/middleware FR), #4313 (ContractController fuite), #4314 (route notifications dupliquée), #4315 (sso/providers throttle), #4316 (commande edge morte), #4317 (exceptions paie dupliquées), #4318 (URLs prod en dur), #4319 (tests manquants).
- **Web** : #4320 (FAQ accordion P1), #4321 (ancre docs), #4322 (a11y inputs), #4323 (aria Fermer), #4324 (Select/Textarea), #4325 (contact FR).
- **Admin** : #4326 (SystemView 404 P1), #4327 (lot 5 i18n), #4328 (FR partiels), #4329 (10 clés manquantes), #4330 (MetricCard mort), #4331 (catchs silencieux), #4332 (XSS popup).
- **Mobile** : #4333 (smart-attendance FR), #4334 (marketing locale), #4335 (résidu dd/MM/yyyy), #4336 (leak subscription), #4337 (catch muet).
- **CI/Ops** : #4338 (queues worker P1), #4339 (deploy-staging), #4340 (coverage-gate), #4341 (FIREBASE_APP_ID_HR), #4342 (CHANGELOG dups), #4343 (secrets doc), #4344 (i18n paths).

## 4. Leçons

- **Les régressions de la journée viennent des merges sans CI** : vérifier Backend Coverage sur le SHA avant merge, pas seulement le statut de la PR.
- `errors.php` ×4 : un merge de résolution de conflits i18n peut tronquer un fichier PHP — **garde `php -l` sur api/lang/ dans les PRs i18n**.
- Le pattern #3677/#4151 (clés sensibles hors fillable) était appliqué partout SAUF `EmployeeService` — la transformation #4151 a laissé un site non converti qui produit des employés hors tenant + 500.
