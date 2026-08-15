# Mini-spec — Issue #3952

## Problème

Les 3 apps Flutter tenant (`leopardo_employee`, `leopardo_hr`, `leopardo_manager`)
ne compilent pas sur main : l'argument nommé `maxRetriesOverride` est présent
**deux fois** dans le même appel `requestWithRetry` (×8 sites, mauvaise fusion de
deux commits QA — l'un ajoutant `maxRetriesOverride: 0`, l'autre « disable
retries for mutations »). Erreur de compile-time Dart
(`The named parameter 'maxRetriesOverride' is already defined`) → tout build des
3 apps échoue. Aucune garde CI ne l'a attrapé (#3822 : `flutter test` des apps
tenant non exécuté sur main).

Sites concernés :

| App | Sites (lignes d'origine) |
|---|---|
| `leopardo_employee` | register:21-25, google-signin:69-73, logout:111-116, company-requests:145-150 |
| `leopardo_hr` | logout:109-114, company-requests:143-148 |
| `leopardo_manager` | logout:109-114, company-requests:143-148 |

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `maxRetriesOverride` dans un même appel `requestWithRetry` | 1 occurrence max |
| `flutter analyze` employee/hr/manager | 0 erreur (CI mobile) |
| Régression #3822 (tests Flutter en CI) | Aucune — contrat intact |

## Correctif

Suppression de l'occurrence dupliquée (la ligne argument autonome
`maxRetriesOverride: 0,` qui suivait l'occurrence inline) sur les 8 sites.
L'intention métier est conservée : les mutations d'auth restent sans retry
(`maxRetriesOverride: 0` gardé une seule fois par appel).

## Validation

Scan automatisé multi-appels (détection de doublon dans chaque parenthèse
équilibrée) : 0 doublon restant sur `front/mobile_apps/**/*.dart`. CI mobile
(`flutter analyze` ×6 apps) requise pour la validation compile — SDK Flutter
absent de la sandbox.

Closes #3952
