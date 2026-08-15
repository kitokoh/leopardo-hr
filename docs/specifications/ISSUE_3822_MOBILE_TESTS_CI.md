# Mini-spec — Issue #3822

## Problème

La CI mobile analyse et build les 6 apps Flutter mais n'exécute `flutter test`
que pour `leopardo_core` (7 tests) et `leopardo_employee` (3 tests). Les apps
`leopardo_hr` (21 fichiers de test), `leopardo_manager` (21) et
`leopardo_platform_admin` (1) produisent des APK sans que leurs routes,
onboarding, guards, modèles et écrans soient jamais testés. Conséquence
démontrée : la régression de compile #3952 (`maxRetriesOverride` dupliqué ×8)
est passée en prod sur main sans que la CI mobile la détecte.

Inventaire des tests (main, 2026-08-15) :

| Application | Fichiers de test | CI avant |
|---|---:|---|
| `leopardo_core` | 7 | ✅ `flutter test` |
| `leopardo_employee` | 3 | ✅ `flutter test` |
| `leopardo_hr` | 21 | ❌ jamais |
| `leopardo_manager` | 21 | ❌ jamais |
| `leopardo_platform_admin` | 1 | ❌ jamais |
| `leopardo_marketing` | 0 | ❌ (pas de `test/`) |

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `flutter test` par app possédant des tests | Exécuté pour les 6 apps de la matrice (dossier `test/` présent) |
| App sans tests (Marketing) | Étape sautée avec `::warning::` explicite, pas d'échec |
| Actions CI existantes | Inchangées (`flutter analyze` avant les tests, build après) |
| actionlint | 0 erreur |

## Correctif

`.github/workflows/mobile-apps-ci.yml`, job `flutter-analyze` : les deux étapes
conditionnelles (`Run core widget tests`, `Run employee critical tests`) sont
remplacées par une étape unique `Run app test suite` exécutée sur chaque projet
de la matrice, gardée par `[ -d test ]` (bash) : si l'app a des tests, ils
tournent en CI ; sinon warning explicite (#3822 follow-up : socle smoke
Marketing).

## Validation

`actionlint` et parse YAML verts en local. Exécution réelle `flutter test` :
CI mobile (`flutter analyze` + `flutter test` ×6 apps) — SDK Flutter absent de
la sandbox. Gardes mobiles existantes (`check-mobile-manifest-routes.sh`, etc.)
inchangées.

Closes #3822
