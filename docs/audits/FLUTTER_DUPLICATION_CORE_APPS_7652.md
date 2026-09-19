# Duplication divergente `leopardo_core` ↔ apps Flutter — état des lieux et plan (issue #7652)

> Audit 2026-09-19, tranche 1 (branche `fix/7652-flutter-dedup`). Mesures reproduites sur
> `main` avec `python3 dev-hub/tools/check-mobile-core-duplication.py --report`.

## 1. Constat mesuré

`front/mobile_apps/leopardo_core/lib/features/` contient des features complètes qui
existent AUSSI en copies **modifiées** dans les apps, en contradiction avec la règle du
`front/mobile_apps/README.md` (« toute modification partagée va dans `leopardo_core` »).

Mesure exhaustive (tout fichier `.dart` d'une app dont le chemin relatif sous
`lib/features/` existe aussi dans le core) :

- **60 chemins partagés** core↔apps au départ de la tranche, dont :
  - **8 shims de ré-export** déjà conformes dans `leopardo_manager`
    (`export 'package:leopardo_core/...';`, pattern #5279) — c'est l'**état cible** ;
  - **4 fichiers byte-identiques** dans `leopardo_employee` (consolidés par cette
    tranche, voir §3) ;
  - **48 copies réellement divergentes** (figées en baseline, voir §4).
- Toutes les apps concernées dépendent déjà de `leopardo_core` par chemin
  (`pubspec.yaml` : `leopardo_core: path: ../leopardo_core`) — la dé-duplication ne
  nécessite aucun changement de dépendances.
- La quasi-totalité des « petites » divergences (2 à 9 lignes) ne sont que le préfixe
  d'import (`package:leopardo_core/...` ↔ `package:leopardo_employee/...`) : code
  métier identique, types **différents** pour Dart. Elles ne sont PAS consolidées dans
  cette tranche (impossible de compiler dans la sandbox — voir §5, étape 1).

## 2. Inventaire brut (sortie du script, état fin de tranche 1)

```text
ÉTAT         DIFF    CORE    APP  CHEMIN (relatif à front/mobile_apps)
DIVERGENT     237     343    152  leopardo_accounting/lib/features/auth/data/auth_repository.dart
DIVERGENT     197     250     71  leopardo_accounting/lib/features/auth/providers/auth_provider.dart
DIVERGENT     408     335    185  leopardo_accounting/lib/features/auth/screens/login_screen.dart
DIVERGENT      39     134    111  leopardo_employee/lib/features/absences/data/absence_repository.dart
DIVERGENT       2      15     15  leopardo_employee/lib/features/absences/providers/absence_provider.dart
DIVERGENT     341     714    619  leopardo_employee/lib/features/absences/screens/absence_list_screen.dart
DIVERGENT     438     566    466  leopardo_employee/lib/features/attendance/data/attendance_repository.dart
DIVERGENT     107     366    385  leopardo_employee/lib/features/attendance/providers/attendance_provider.dart
DIVERGENT    1386    1274   2432  leopardo_employee/lib/features/attendance/screens/attendance_screen.dart
DIVERGENT      12     362    362  leopardo_employee/lib/features/attendance/screens/history_screen.dart
DIVERGENT     689     301    534  leopardo_employee/lib/features/attendance/screens/monthly_summary_screen.dart
DIVERGENT     116      59    101  leopardo_employee/lib/features/attendance_geo/data/attendance_geo_repository.dart
DIVERGENT     106      79     71  leopardo_employee/lib/features/attendance_geo/data/models/geo_attendance_session.dart
DIVERGENT     248      20    234  leopardo_employee/lib/features/attendance_geo/providers/attendance_geo_provider.dart
DIVERGENT      21     343    356  leopardo_employee/lib/features/auth/data/auth_repository.dart
DIVERGENT      17     250    255  leopardo_employee/lib/features/auth/providers/auth_provider.dart
DIVERGENT      57     335    342  leopardo_employee/lib/features/auth/screens/login_screen.dart
DIVERGENT       6     255    257  leopardo_employee/lib/features/auth/screens/register_screen.dart
DIVERGENT     278     239    255  leopardo_employee/lib/features/auth/screens/welcome_screen.dart
SHIM            -       -      -  leopardo_employee/lib/features/cabinet/data/cabinet_repository.dart
DIVERGENT       2      22     22  leopardo_employee/lib/features/cabinet/providers/cabinet_provider.dart
DIVERGENT      86     584    590  leopardo_employee/lib/features/cabinet/screens/cabinet_screen.dart
DIVERGENT       4      20     20  leopardo_employee/lib/features/company_branding/providers/tenant_branding_provider.dart
SHIM            -       -      -  leopardo_employee/lib/features/evaluations/data/evaluation_repository.dart
DIVERGENT       2       8      8  leopardo_employee/lib/features/evaluations/providers/evaluation_provider.dart
DIVERGENT       2      93     93  leopardo_employee/lib/features/evaluations/screens/evaluation_list_screen.dart
DIVERGENT     593     824    391  leopardo_employee/lib/features/home/screens/home_screen.dart
DIVERGENT       3     254    255  leopardo_employee/lib/features/home/screens/modules_hub_screen.dart
SHIM            -       -      -  leopardo_employee/lib/features/notifications/data/notification_repository.dart
DIVERGENT       2      17     17  leopardo_employee/lib/features/notifications/providers/notification_provider.dart
DIVERGENT      41     270    267  leopardo_employee/lib/features/notifications/screens/notification_list_screen.dart
DIVERGENT       9      51     42  leopardo_employee/lib/features/onboarding/data/onboarding_repository.dart
DIVERGENT      14      99     97  leopardo_employee/lib/features/payrolls/data/payroll_repository.dart
DIVERGENT      22      28     22  leopardo_employee/lib/features/payrolls/providers/payroll_provider.dart
DIVERGENT      61     149    100  leopardo_employee/lib/features/salary_advances/data/salary_advance_repository.dart
DIVERGENT       2       8      8  leopardo_employee/lib/features/salary_advances/providers/salary_advance_provider.dart
DIVERGENT     303     712    543  leopardo_employee/lib/features/salary_advances/screens/salary_advance_list_screen.dart
SHIM            -       -      -  leopardo_employee/lib/features/settings/data/biometric_enrollment.dart
DIVERGENT       7     320    323  leopardo_employee/lib/features/settings/data/settings_repository.dart
DIVERGENT     904    1630   1862  leopardo_employee/lib/features/settings/screens/settings_screen.dart
DIVERGENT      73     286    213  leopardo_employee/lib/features/user_auth/data/user_auth_repository.dart
DIVERGENT       9     111    106  leopardo_employee/lib/features/user_auth/providers/user_auth_provider.dart
DIVERGENT      77     296    297  leopardo_employee/lib/features/user_auth/screens/company_request_screen.dart
DIVERGENT      93     404    319  leopardo_employee/lib/features/user_auth/screens/user_home_screen.dart
DIVERGENT      57     301    304  leopardo_employee/lib/features/user_auth/screens/user_login_screen.dart
DIVERGENT      75     339    342  leopardo_employee/lib/features/user_auth/screens/user_register_screen.dart
SHIM            -       -      -  leopardo_manager/lib/features/attendance/data/attendance_repository.dart
SHIM            -       -      -  leopardo_manager/lib/features/attendance/providers/attendance_provider.dart
SHIM            -       -      -  leopardo_manager/lib/features/auth/providers/auth_provider.dart
SHIM            -       -      -  leopardo_manager/lib/features/company_branding/providers/tenant_branding_provider.dart
SHIM            -       -      -  leopardo_manager/lib/features/company_branding/screens/company_branding_screen.dart
SHIM            -       -      -  leopardo_manager/lib/features/home/screens/home_screen.dart
SHIM            -       -      -  leopardo_manager/lib/features/notifications/data/notification_repository.dart
SHIM            -       -      -  leopardo_manager/lib/features/notifications/screens/notification_list_screen.dart
DIVERGENT     237     343    150  leopardo_marketing/lib/features/auth/data/auth_repository.dart
DIVERGENT     196     250     72  leopardo_marketing/lib/features/auth/providers/auth_provider.dart
DIVERGENT     407     335    184  leopardo_marketing/lib/features/auth/screens/login_screen.dart
DIVERGENT     234     343    151  leopardo_travel_agent/lib/features/auth/data/auth_repository.dart
DIVERGENT     197     250     71  leopardo_travel_agent/lib/features/auth/providers/auth_provider.dart
DIVERGENT     422     335    187  leopardo_travel_agent/lib/features/auth/screens/login_screen.dart

Total: 48 doublon(s) réel(s) (0 identique(s), 48 divergent(s)), 12 shim(s) de ré-export (état cible).
```

### Lecture par feature : quelle version fait foi ?

Datation `git log -1` par fichier + lecture des diffs. « Canonique probable » = version
la plus récente ET la plus complète ; à confirmer feature par feature avec la CI Flutter.

| Feature (paire) | Divergence | Constat | Canonique probable |
| --- | --- | --- | --- |
| `attendance` (employee) | 1 386 lignes sur l'écran, 438 sur le repository | La copie **employee** est presque 2× plus grosse (2 432 L vs 1 274 L : biométrie, géoloc, offline) et le repository core a été retouché APRÈS (2026-08-25 vs 08-23/24) — **divergence croisée**, la pire du lot | **Fusion requise** (ni l'un ni l'autre seul) |
| `attendance_geo` (employee) | provider : core 20 L vs app 234 L | Le provider core est un squelette ; l'app porte l'implémentation réelle. Le repository diverge dans les deux sens | **employee**, à remonter dans le core |
| `absences` (employee) | 341 lignes sur `absence_list_screen.dart` | Copies modifiées des deux côtés à un jour d'écart (core 08-25, employee 08-26) | **Fusion requise** |
| `auth` (employee) | 5 fichiers, 6–278 lignes | Retouches croisées le même jour (2026-08-26) ; `welcome_screen` massivement divergent | **Fusion requise** |
| `auth` (accounting / marketing / travel_agent) | ~200–420 lignes chacun | Forks **simplifiés** (150–187 L vs 250–343 L) figés en août/début septembre, alors que le core a reçu des correctifs jusqu'au **2026-09-17** : fixes non propagés quasi certains | **core**, paramétré par app (branding/redirections via config) |
| `settings` (employee) | 904 lignes sur l'écran | La copie **employee** est plus récente (2026-09-04 vs 08-26) et plus complète (1 862 L vs 1 630 L) | **employee**, à remonter dans le core |
| `home` (employee) | 593 lignes | Le **core** est plus récent (08-25 vs 08-17) et plus complet (824 L vs 391 L) | **core** |
| `user_auth`, `salary_advances`, `payrolls`, `onboarding` (employee) | 9–303 lignes | Le core est systématiquement plus récent (2026-08-25/26) et plus complet | **core** |
| `cabinet`, `evaluations`, `notifications`, `company_branding` (employee) | 2–86 lignes | Divergences majoritairement limitées au préfixe d'import ; les data-layers identiques ont été consolidés (§3) | **core** (quasi acquis) |

## 3. Fait dans cette tranche (vérifiable sans build)

1. **Script d'inventaire et de garde committé** (case 1 de l'issue) :
   `dev-hub/tools/check-mobile-core-duplication.py` (`--report`, `--update-baseline`,
   mode garde par défaut).
2. **Consolidation des seuls cas sûrs** : les 4 fichiers de `leopardo_employee`
   **byte-identiques** au core deviennent des shims de ré-export (pattern #5279 déjà
   éprouvé par `leopardo_manager`) :
   - `cabinet/data/cabinet_repository.dart`
   - `evaluations/data/evaluation_repository.dart`
   - `notifications/data/notification_repository.dart`
   - `settings/data/biometric_enrollment.dart`

   Sûreté vérifiée statiquement : les 4 fichiers n'importaient QUE des symboles
   `leopardo_core` (aucun import `leopardo_employee` interne), tous leurs consommateurs
   les importent par chemin `package:` inchangé (`core_providers.dart`,
   `settings_screen.dart`, `settings_repository.dart`), aucun fichier de l'app
   n'importait déjà la version core (pas de collision d'export), et
   `core_providers.dart` masque déjà (`hide`) les providers shadowés du core.
3. **Garde CI** `.github/workflows/mobile-core-duplication-guard.yml` (case 4 de
   l'issue, version « anti-régression ») : rouge UNIQUEMENT sur une **nouvelle** copie
   core→app ; la dette existante est figée dans
   `dev-hub/tools/mobile-core-duplication-baseline.txt` (48 entrées). Un shim de
   ré-export passe toujours. Aucune toolchain Flutter requise.

## 4. Non fait dans cette tranche — et pourquoi

- **Aucune fusion de fichier divergent** : la sandbox d'agent n'a ni `flutter` ni
  `dart` (`dart format` / `flutter analyze` / tests indisponibles). Fusionner 48
  fichiers divergents (dont 1 386 lignes de diff sur l'écran de pointage) sans pouvoir
  compiler serait irresponsable.
- **Pas de suppression des copies « préfixe d'import seulement »** : remplacer une
  classe locale par la classe core change l'identité de type Dart ; sans analyzer, un
  conflit d'export ou une inférence de type cassée passerait inaperçu.
- La baseline doit **maigrir** à chaque tranche suivante, jamais grossir (la garde
  avertit quand une entrée est résolue ; la retirer via `--update-baseline`).

## 5. Plan de dé-duplication complet (nécessite la CI Flutter `mobile-apps-ci.yml`)

Ordre du moins risqué au plus risqué ; chaque étape = une PR verte sur
`Mobile Apps CI - Flutter` + baseline régénérée.

1. **Copies triviales (préfixe d'import seulement)** — remplacer par des shims les
   fichiers dont le diff ne porte que sur `package:leopardo_<app>/` → `package:leopardo_core/`
   (~15 fichiers : providers `cabinet`, `evaluations`, `notifications`,
   `salary_advances`, `absences`, `company_branding`, écrans `evaluation_list`,
   `modules_hub`, `history_screen`…). Vérification : `flutter analyze` + tests des apps.
2. **Features où le core fait foi** — `home`, `user_auth`, `payrolls`, `onboarding`,
   `salary_advances` (employee) : porter les éventuels deltas utiles de l'app dans le
   core (diff par diff), puis shims.
3. **Auth des apps satellites** (`accounting`, `marketing`, `travel_agent`) : les forks
   simplifiés deviennent le core paramétré (rôle/app via config injectée — jamais par
   fork), ce qui repropage les correctifs core du 2026-09-17. Point sécurité : diff
   d'audit des 3 forks AVANT suppression pour vérifier qu'aucun fix local n'existe que là.
4. **Fusions croisées** — `settings`, `absences`, `auth` (employee) : réconciliation
   champ par champ, la version fusionnée retourne dans le core, différences par rôle
   exprimées en config.
5. **Le gros morceau : `attendance` / `attendance_geo`** : fusion des deux
   implémentations (biométrie/géoloc/offline côté employee + retouches core
   postérieures), tests de pointage complets requis.
6. **Clôture** : baseline vide → basculer la garde en zéro tolérance (interdire tout
   chemin partagé non-shim, plus besoin de baseline) et fermer #7652.

## 6. Tranche 2 — réconciliation attendance (data + provider) et couche d'état auth (employee)

Livrée sans compilateur (toujours pas de `flutter`/`dart` en sandbox), donc
limitée aux fusions dont la sûreté est démontrable par analyse statique :

1. **`attendance` — la « divergence croisée » du §2 est fusionnée dans le core**
   (étape 5 du plan, volets data/provider) :
   - `leopardo_core/.../attendance/data/attendance_repository.dart` devient le
     **superset canonique** : apports employee (`work_type`/`punch_note` dans les
     payloads de pointage, règle F-21 « 1er pointage gagne » sur la file hors-ligne,
     `sessions`/`summary`/`session_number` dans `decodeTodayResponse`,
     `getMyAnomalies`, `getTodayTasks`, `completeTask`) + acquis core conservés
     (garde payload #3406, détection hors-ligne #5407, endpoints manager
     anomalies/corrections/day-detail).
   - `attendance_provider.dart` core = état superset (`todaySessions`,
     `daySummary`, upsert de session) + notices `overtime`/`break`.
   - Le modèle `attendance_anomaly.dart` (employee-only) remonte dans
     `leopardo_core/lib/features/attendance/models/`.
   - **Différence par app exprimée en config, pas par fork** (case 2 de l'issue) :
     `AttendanceFeatureConfig` (`.../attendance/config/attendance_feature_config.dart`),
     défaut manager/RH ; `leopardo_employee/lib/main.dart` surcharge
     `attendanceFeatureConfigProvider` via `ProviderScope(overrides: [...])`
     (ton self-service du message hors-zone).
   - Côté employee : `attendance/data` + `attendance/providers` deviennent des
     shims de ré-export (les validateurs `validate-mobile-location-readiness.ps1` /
     `validate-mobile-notification-production-proof.ps1` exigent l'existence des
     chemins) ; `history_screen.dart` (12 lignes de diff cosmétiques) est supprimé,
     l'app route sur l'écran core.
2. **`auth` (employee), couche d'état** : le diff `auth_provider.dart` /
   `auth_repository.dart` core↔employee était **exclusivement commentaires +
   formatage** (vérifié par diff normalisé sans commentaires/espaces) — fork
   résiduel sans delta fonctionnel. Le provider employee devient un shim, le
   repository local est supprimé, `core_providers.dart` cesse de shadow-déclarer
   `authRepositoryProvider`/`attendanceRepositoryProvider` (une seule instance
   Riverpod pour toute l'app, celle du core — le logout 401 du notifier attendance
   agit désormais sur le MÊME `authProvider` que le routeur).
3. **Baseline** : 48 → 43 entrées (`--update-baseline`).

Restent divergents (baseline, étapes 3–5 du plan) : les écrans
`attendance_screen.dart` (2 432 L, sessions/tâches/biométrie) et
`monthly_summary_screen.dart` côté employee (réécriture UI — fusion d'écran
injustifiable sans `flutter analyze`/tests), les écrans `auth` brandés par app,
`absences`, `settings`, `attendance_geo` et les forks `auth` des apps satellites.

_Audit et tranche 1 — session Zentor 2026-09-19, issue #7652. Tranche 2 (même session) : fusion attendance data/provider + auth state layer._
