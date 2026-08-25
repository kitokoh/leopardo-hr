# Dette baseline PHPStan Strict — métrique & réduction (#5448)

> Dernière mise à jour : 2026-08-25 · Voir l'issue [#5448](https://github.com/kitokoh/leopardo-hr/issues/5448)

## Pourquoi cette dette est dangereuse

La baseline `api/phpstan-strict-baseline.neon` (level 8, `app/Core`, `app/Modules`,
`app/Shared`) compte **8 264 lignes** (vérifié 2026-08-25). Une baseline énorme masque
de vraies erreurs : la Phase 5 ADR-0016 a fait ressortir 8 erreurs réelles quand des
fichiers ont quitté la couverture baseline.

## Outillage (#5448)

- `dev-hub/tools/check-phpstan-baseline-debt.sh report` — rapport de dette par module
  (entrées, occurrences, fichiers, part du total, classes d'erreur) — tri du plus gros
  contributeur au plus petit pour piloter la réduction.
- `dev-hub/tools/check-phpstan-baseline-debt.sh guard <base> <head>` — garde CI
  **« 0 nouvelle entrée »** : toute PR qui ajoute une entrée baseline ou augmente un
  count échoue (ratchet global, complémentaire du ratchet par module PA2-ARCH-005
  `check-phpstan-baseline-delta.sh`). Branche : `.github/workflows/architecture-check.yml`.

## État de la dette (2026-08-25)

Sortie du rapport (`check-phpstan-baseline-debt.sh report api`) :

```
== PHPStan baseline debt report (#5448) ==
Baseline files: phpstan-strict-baseline.neon phpstan-baseline.neon phpstan-modules-baseline.neon

--- phpstan-strict-baseline.neon (1360 entrées, 2845 occurrences, 522 fichiers) ---
  Dette par module (tri décroissant d'occurrences) :
    tests/Feature                              1959 occ    595 entrées
    app/Http                                    169 occ    158 entrées
    app/Modules/HR                              103 occ     84 entrées
    app/Modules/Attendance                       85 occ     78 entrées
    tests/Unit                                   67 occ     42 entrées
    app/Modules/Payroll                          65 occ     52 entrées
    app/Modules/Planning                         55 occ     43 entrées
    app/Modules/EdgeSync                         49 occ     40 entrées
    app/Core/Auth                                36 occ     33 entrées
    app/Modules/SmartAttendance                  35 occ     33 entrées
    app/Modules/Billing                          33 occ     32 entrées
    app/Modules/Notification                     26 occ     25 entrées
    app/Core/Feature                             25 occ     23 entrées
    app/Modules/Platform                         15 occ     14 entrées
    app/Jobs                                     13 occ     11 entrées
    app/Modules/Cabinet                          13 occ     13 entrées
    app/Modules/Fleet                            12 occ     12 entrées
    app/Exceptions                                9 occ      1 entrées
    app/Modules/Growth                            9 occ      9 entrées
    app/Listeners                                 8 occ      4 entrées
    app/Modules/Cameras                           8 occ      8 entrées
    app/Modules/Recruitment                       8 occ      8 entrées
    app/Modules/Onboarding                        7 occ      7 entrées
    app/Modules/Marketing                         6 occ      5 entrées
    app/Console                                   5 occ      5 entrées
    app/Core/Tenant                               5 occ      5 entrées
    app/Shared                                    5 occ      5 entrées
    app/Modules/Absence                           4 occ      4 entrées
    app/Mail                                      3 occ      3 entrées
    app/Modules/Expense                           3 occ      3 entrées
    app/Notifications                             2 occ      2 entrées
    app/Providers                                 2 occ      2 entrées
    app/Enums                                     1 occ      1 entrées
  Dette par classe d'erreur (top 10) :
    property.notFound                          1179 occ
    argument.type                               498 occ
    missingType.iterableValue                   245 occ
    property.nonObject                          235 occ
    argument.templateType                       107 occ
    assign.propertyType                          98 occ
    missingType.generics                         84 occ
    nullsafe.neverNull                           80 occ
    method.notFound                              76 occ
    return.type                                  73 occ
```

## Réduction par lots

Règle : **1 agent par module**, jamais de re-baseline d'une erreur corrigée. Ordre : du
plus gros contributeur au plus petit, en évitant les modules en cours de refonte par
d'autres agents (Accounting, Attendance, Payroll, HR, SmartAttendance).

- Lot 1 (2026-08-25) : module **Planning** — 39 entrées retirées (voir diff de la PR #5448) ; 4 entrées `missingType.generics` (HasFactory réellement utilisé) conservées — trait retiré de Project/Task (code mort).
- Cible : **-20 % d'entrées en 4 semaines**, puis **-10 %/mois**.

## Classes d'erreur dominantes (strict)

| classe | occurrences | correctif type |
|---|---|---|
| `property.notFound` | 1179 | `@property` sur modèles / `@var` précis |
| `argument.type` | 498 | types de paramètres (string vs string\|false…) |
| `missingType.iterableValue` | 245 | `@param array<...>` / `@return array<...>` |
| `property.nonObject` | 235 | null-check avant accès propriété |
| `argument.templateType` | 107 | génériques `Builder<TModel>`… |
| `assign.propertyType` | 98 | types de propriétés |
| `missingType.generics` | 84 | `@use HasFactory<T>` / `Builder<TModel>` |
| `nullsafe.neverNull` | 80 | nullsafe inutile → retirer |
| `method.notFound` | 76 | méthode inexistante / `@mixin` |
| `return.type` | 73 | types de retour |

## Coordination

- #5410 : fix PHPStan Strict en cours sur main (tests HR/TVA + EmployeeResource) —
  les retraits de baseline de #5410 sont les bienvenus, ils réduisent le même fichier.
- Tout nouveau fichier PHP : 0 occurrence baselinée autorisée (garde #5448 + PA2-ARCH-009).
