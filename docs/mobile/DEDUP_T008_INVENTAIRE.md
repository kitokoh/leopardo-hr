# Dé-duplication `leopardo_hr` / `leopardo_manager` — Inventaire T008 (#2601)

> **Issue** : #2601 — T008 [P3] « Dé-duplication leopardo_hr/leopardo_manager (92 vs 93 fichiers) :
> extraction des écrans partagés vers leopardo_core — chantier structurel documenté ».
> **Feature** : `.specify/features/qa-audit-expert-mobile-2026-08-15/` (US3).
> **Date** : 2026-08-20. **Branche** : `refactor/2601-mobile-dedup`.
> **Méthode** : comparaison fichier par fichier (hash SHA-256 + `diff`) des `lib/` et `test/`
> des deux apps, sans SDK Dart dans l'environnement (vérification statique uniquement).

## Chiffres clés (état mesuré au 2026-08-20, HEAD)

| Métrique | `leopardo_hr` | `leopardo_manager` |
|---|---|---|
| Fichiers `.dart` dans `lib/` | 56 | 62 |
| Fichiers `.dart` dans `test/` | 22 | 22 |
| **Total `.dart`** | **78** | **84** |
| Fichiers communs `lib/` (mêmes chemins relatifs) | 50 | 50 |
| Fichiers communs `test/` (mêmes chemins relatifs) | 22 | 22 |
| **Doublons byte-identiques `lib/`** | **0** | **0** |
| **Doublons byte-identiques `test/`** | **10** | **10** |
| Quasi-doublons `lib/` (diff = préfixe package uniquement) | 20 | 20 |
| Vraies divergences `lib/` | 30 | 30 |
| Fichiers uniques | 6 (HR) | 12 (MGR) |

> Les compteurs de l'issue (« 92 vs 93 ») proviennent de l'audit 2026-08-15 et ne
> correspondent plus exactement à l'arbre courant (fusion du 2026-08-20) ; les
> chiffres ci-dessus font foi pour le chantier.

## Verdict d'ensemble

1. **Aucun fichier de `lib/` n'est byte-identique** : la duplication des écrans est en réalité une
   duplication « quasi-identique modulo le préfixe package applicatif dans les imports »
   (20 fichiers) ou une duplication partielle avec **divergences réelles** (30 fichiers,
   ex. refresh visuel GlassCard appliqué côté manager, clés l10n différentes, comportements
   différents). Conformément à la règle du chantier (« si un fichier est quasi-identique,
   NE LE DÉPLACE PAS — documente-le comme candidat lot suivant »), **aucun fichier de `lib/`
   n'est déplacé dans ce lot**.
2. **10 fichiers de `test/` sont byte-identiques et n'importent que `flutter*` + `leopardo_core`**
   → **extraits dans `leopardo_core/test/` (lot 1, sans risque)**.
3. Les 22 autres fichiers byte-identiques (config, polices, mock assets, `pubspec.lock`)
   sont de l'infrastructure applicative → **non déplaçables** (documenté ci-dessous).

---

### Tableau A — fichiers communs `lib/` (50)

| Fichier (relatif à `lib/`) | Statut | Lignes | Verdict |
|---|---|---|---|
| `app.dart` | divergent | 414 | À traiter par décision produit (divergences réelles) |
| `core/providers/core_providers.dart` | divergent | 159 | À traiter par décision produit (divergences réelles) |
| `features/absences/providers/absence_provider.dart` | quasi (import-only) | 15 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/absences/screens/absence_list_screen.dart` | divergent | 713 | À traiter par décision produit (divergences réelles) |
| `features/attendance/data/attendance_repository.dart` | divergent | 542 | À traiter par décision produit (divergences réelles) |
| `features/attendance/providers/attendance_provider.dart` | quasi (import-only) | 361 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/attendance/screens/attendance_screen.dart` | divergent | 1269 | À traiter par décision produit (divergences réelles) |
| `features/attendance/screens/history_screen.dart` | quasi (import-only) | 357 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/attendance/screens/monthly_summary_screen.dart` | divergent | 292 | À traiter par décision produit (divergences réelles) |
| `features/auth/data/auth_repository.dart` | divergent | 285 | À traiter par décision produit (divergences réelles) |
| `features/auth/providers/auth_provider.dart` | divergent | 206 | À traiter par décision produit (divergences réelles) |
| `features/auth/screens/access_denied_screen.dart` | divergent | 57 | À traiter par décision produit (divergences réelles) |
| `features/auth/screens/login_screen.dart` | divergent | 331 | À traiter par décision produit (divergences réelles) |
| `features/auth/screens/register_screen.dart` | quasi (import-only) | 257 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/cabinet/providers/cabinet_provider.dart` | quasi (import-only) | 22 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/cabinet/screens/cabinet_screen.dart` | quasi (import-only) | 584 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/company_branding/providers/company_branding_provider.dart` | quasi (import-only) | 7 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/company_branding/providers/tenant_branding_provider.dart` | quasi (import-only) | 20 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/company_branding/screens/company_branding_screen.dart` | divergent | 379 | À traiter par décision produit (divergences réelles) |
| `features/evaluations/providers/evaluation_provider.dart` | quasi (import-only) | 8 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/evaluations/screens/evaluation_list_screen.dart` | divergent | 93 | À traiter par décision produit (divergences réelles) |
| `features/home/screens/home_screen.dart` | divergent | 849 | À traiter par décision produit (divergences réelles) |
| `features/home/screens/modules_hub_screen.dart` | divergent | 266 | À traiter par décision produit (divergences réelles) |
| `features/manager/screens/manager_attendance_monitoring_screen.dart` | divergent | 886 | À traiter par décision produit (divergences réelles) |
| `features/notifications/providers/notification_provider.dart` | quasi (import-only) | 17 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/notifications/screens/notification_list_screen.dart` | quasi (import-only) | 270 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/onboarding/data/onboarding_repository.dart` | divergent | 51 | À traiter par décision produit (divergences réelles) |
| `features/payrolls/providers/payroll_provider.dart` | quasi (import-only) | 28 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/payrolls/screens/payroll_list_screen.dart` | divergent | 659 | À traiter par décision produit (divergences réelles) |
| `features/salary_advances/providers/salary_advance_provider.dart` | quasi (import-only) | 8 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/salary_advances/screens/salary_advance_list_screen.dart` | divergent | 719 | À traiter par décision produit (divergences réelles) |
| `features/schedules/providers/schedule_provider.dart` | quasi (import-only) | 9 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/schedules/screens/schedule_list_screen.dart` | divergent | 950 | À traiter par décision produit (divergences réelles) |
| `features/settings/data/settings_repository.dart` | divergent | 320 | À traiter par décision produit (divergences réelles) |
| `features/settings/screens/settings_screen.dart` | divergent | 1555 | À traiter par décision produit (divergences réelles) |
| `features/smart_attendance/data/smart_attendance_repository.dart` | divergent | 59 | À traiter par décision produit (divergences réelles) |
| `features/smart_attendance/providers/smart_attendance_provider.dart` | divergent | 25 | À traiter par décision produit (divergences réelles) |
| `features/smart_attendance/screens/pending_sessions_screen.dart` | divergent | 353 | À traiter par décision produit (divergences réelles) |
| `features/smart_attendance/screens/smart_attendance_dashboard_screen.dart` | divergent | 307 | À traiter par décision produit (divergences réelles) |
| `features/tasks/providers/task_provider.dart` | quasi (import-only) | 14 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/tasks/screens/task_list_screen.dart` | divergent | 511 | À traiter par décision produit (divergences réelles) |
| `features/team/providers/team_provider.dart` | quasi (import-only) | 23 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/team/screens/team_screen.dart` | divergent | 1872 | À traiter par décision produit (divergences réelles) |
| `features/user_auth/data/user_auth_repository.dart` | divergent | 218 | À traiter par décision produit (divergences réelles) |
| `features/user_auth/providers/user_auth_provider.dart` | quasi (import-only) | 106 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/user_auth/screens/company_request_screen.dart` | quasi (import-only) | 296 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/user_auth/screens/user_home_screen.dart` | divergent | 319 | À traiter par décision produit (divergences réelles) |
| `features/user_auth/screens/user_login_screen.dart` | quasi (import-only) | 301 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `features/user_auth/screens/user_register_screen.dart` | quasi (import-only) | 339 | Candidat lot suivant (bloqué par DI app `core_providers.dart`) |
| `main.dart` | divergent | 133 | À traiter par décision produit (divergences réelles) |

---

### Tableau B — fichiers communs `test/` (22, état à HEAD)

| Fichier (relatif à `test/`) | Statut | Verdict |
|---|---|---|
| `features/attendance/attendance_day_summary_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/attendance/attendance_repository_actions_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/attendance/attendance_screen_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/attendance/employee_day_detail_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/attendance/history_screen_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `features/auth/login_screen_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/auth/welcome_screen_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `features/mobile_marketing_readiness_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `features/mobile_surface_smoke_test.dart` | divergent | Divergent — diff réelle — à unifier par décision |
| `golden/critical_component_golden_test.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `helpers/mobile_test_harness.dart` | quasi (import-only) | Candidat lot suivant (importe le package app) |
| `models/approval_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `models/contract_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `models/expense_claim_test.dart` | divergent | Divergent — diff réelle — à unifier par décision |
| `models/onboarding_step_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `models/sync_models_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `models/training_enrollment_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `models/vehicle_position_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `navigation/go_router_guard_test.dart` | divergent | Divergent — routes propres à chaque app (shells hr/manager) — spécifique |
| `repositories/repository_contract_test.dart` | divergent | Divergent — **hors périmètre #2601** (contrat onboarding, PR #2663 en cours) |
| `widget_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |
| `widgets/mobile_surface_test.dart` | byte-identique | **EXTRAIT lot 1 → `leopardo_core/test/`** |

---

### Tableau C — fichiers uniques (18)

| Fichier | App | Verdict |
|---|---|---|
| `features/contracts/data/contract_repository.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/contracts/providers/contract_provider.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/contracts/screens/contract_screen.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/home/screens/hr_main_shell.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/organigramme/data/organigramme_repository.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/organigramme/screens/organigramme_screen.dart` | HR only | Spécifique HR (contrats, organigramme, shell HR) — pas de doublon |
| `features/ai_chat/data/ai_chat_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/ai_chat/screens/ai_chat_screen.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/approvals/data/approval_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/approvals/providers/approval_provider.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/approvals/screens/approval_screen.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/company_branding/data/company_branding_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/home/screens/manager_main_shell.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/notifications/data/notification_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/payrolls/data/payroll_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/vehicle_position/data/vehicle_position_repository.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/vehicle_position/providers/vehicle_position_provider.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |
| `features/vehicle_position/screens/vehicle_map_screen.dart` | MGR only | Spécifique Manager (ai_chat, approvals, vehicle_position…) — pas de doublon |

---


## Détail des 20 quasi-doublons `lib/` (bloqués par la DI applicative)

Les 20 fichiers « quasi (import-only) » du tableau A sont identiques entre les deux apps à
l'exception du préfixe `package:leopardo_hr/` ↔ `package:leopardo_manager/` dans les imports.
Leur extraction est **bloquée** par un même motif structurel : tous importent
`core/providers/core_providers.dart` (le câblage DI propre à chaque app) ou un provider
applicatif (auth/attendance/user_auth), lui-même branché sur des repositories locaux
(`AuthRepository`, `AttendanceRepository`, `SettingsRepository`, `UserAuthRepository`) qui
**divergent réellement** entre les apps. Déplacer un de ces fichiers sans déplacer sa closure
casserait les symboles (ex. `absenceRepositoryProvider` n'existe pas dans
`leopardo_core/core/providers/base_providers.dart`).

### Prérequis pour le lot suivant (lib/)

1. **Unifier les repositories locaux divergents** dans `leopardo_core` (ou les paramétrer) :
   `auth_repository.dart`, `attendance_repository.dart`, `settings_repository.dart`,
   `user_auth_repository.dart`, `onboarding_repository.dart`.
2. **Extraire les providers de repositories identiques** (ex. `absenceRepositoryProvider`,
   `cabinetRepositoryProvider`, …) de `core_providers.dart` vers un nouveau fichier core
   (attention Riverpod : conserver l'identité du provider `apiClientProvider` avec le
   `onUnauthorized` câblé — issue #2737 — sinon régression de session).
3. **Décider du sort des écrans divergents** (refresh GlassCard manager vs HR, clés l10n,
   comportements) avant toute unification.

## Fichiers byte-identiques NON déplaçables (hors tests)

| Fichier | Raison |
|---|---|
| `.gitignore`, `.gitkeep`, `.metadata`, `Makefile`, `analysis_options.yaml`, `build.ps1`, `build.yaml` | Configuration/scaffolding propre à chaque package Flutter |
| `pubspec.lock` | Généré par pub — ne se déplace jamais |
| `assets/fonts/Inter-Variable.ttf`, `Inter-Italic-Variable.ttf` | Déclarés dans le `pubspec.yaml` de chaque app (résolution d'assets au build) |
| `assets/mock/mock_*.json` (×12) | Chargés par chemin relatif (`assets/mock/...`) via `rootBundle` — les déplacer casserait les apps ; `leopardo_core` déclare déjà ses propres `assets/mock/` (non comparés, à auditer séparément) |

## Vérifications effectuées (lot 1)

- **Hash** : les 10 fichiers extraits sont triple-identiques (HR@HEAD == MGR@HEAD == `leopardo_core/test/`).
- **Grep** : aucune référence aux anciens chemins hors docs historiques ; aucun import de
  `*_test.dart` depuis du code applicatif ; les tests restants des apps n'importent pas les fichiers déplacés.
- **Imports** : les 15 cibles d'import uniques des 10 tests existent toutes dans `leopardo_core`
  (modèles, `welcome_screen`, `l10n`, `app_colors`, `mobile_surface`).
- **Lint/CI** : `analysis_options.yaml` identique entre apps et core → mêmes lints ;
  `melos.yaml` exécute déjà `flutter test` sur `leopardo_core` (dir `test/` présent) ;
  `mobile-apps-ci.yml` exécute `flutter test` par package → les tests extraits tournent côté core,
  les apps conservent chacune 12 tests.
- **Contrainte core** (`validate-mobile-apps-split.ps1`) : `leopardo_core/lib` n'importe aucun
  package app → respecté (les tests extraits n'importent que core/flutter).

## Risques restants

- **Aucun fichier `lib/` n'a été extrait** : la duplication réelle des écrans (92/93 fichiers
  du titre) reste en place — le chantier doit se poursuivre via les prérequis ci-dessus.
- `leopardo_core` héberge désormais 19 tests → la suite core grossit (temps CI marginal).
- Les tests extraits ne couvrent pas les écrans partagés : ils ne testent que les modèles et
  widgets core — la valeur de dé-duplication est structurelle (suppression de 20 copies).
- `flutter analyze` / `flutter test` non exécutables dans ce sandbox (pas de SDK Dart) :
  validation à confirmer en CI (`mobile-apps-ci.yml`).

## Références

- Spec : `.specify/features/qa-audit-expert-mobile-2026-08-15/{spec,plan,tasks}.md`
- Convergence : `docs/mobile/CONVERGENCE_TRACKER.md`, `docs/mobile/CONVERGENCE_F27.md`
- Garde-fous CI : `dev-hub/tools/validate-mobile-apps-split.ps1`, `validate-mobile-workflow-contracts.ps1`, `.github/workflows/mobile-apps-ci.yml`
