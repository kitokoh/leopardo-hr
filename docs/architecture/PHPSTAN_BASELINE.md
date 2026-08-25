# Dette PHPStan — rapport de baseline (issue #5448)

> Mesure du 2026-08-25 · généré par `dev-hub/tools/check-phpstan-baseline-debt.sh`
> Garde par module : `dev-hub/tools/check-phpstan-baseline-delta.sh` (PA2-ARCH-005, branchée dans `architecture-check.yml`).

## Métrique

Les trois fichiers baseline cumulent **4 081 entrées ignorées** (niveau strict inclus) :

| Fichier baseline | Entrées |
|---|---|
| `api/phpstan-baseline.neon` | 1 168 |
| `api/phpstan-modules-baseline.neon` | 42 |
| `api/phpstan-strict-baseline.neon` (niveau 8) | 2 871 |
| **Total** | **4 081** |

## Répartition du niveau strict (2 871 entrées) par module

| Module | Entrées |
|---|---|
| `tests/Feature` | 1 972 |
| `app/Http` | 173 |
| `app/Modules/HR` | 105 |
| `app/Modules/Attendance` | 85 |
| `tests/Unit` | 69 |
| `app/Modules/Payroll` | 66 |
| `app/Modules/Planning` | 55 |
| `app/Modules/EdgeSync` | 49 |
| `app/Core/Auth` | 37 |
| `app/Modules/SmartAttendance` | 35 |
| `app/Modules/Billing` | 34 |
| `app/Modules/Notification` | 26 |
| `app/Core/Feature` | 25 |
| autres (`app/Jobs`, `app/Exceptions`, `app/Console`…) | ~60 |

## Objectifs de réduction

- **-20 % d'entrées en 4 semaines** (≈ 570 entrées strict), puis **-10 %/mois**.
- Par module, du plus gros contributeur au plus petit :
  1. `tests/Feature` (1 972) — typage des fixtures et assertions (`@var`, `assertIsArray`, génériques).
  2. `app/Http` (173) — code legacy pré-module : types de retour + `@var` précis.
  3. `app/Modules/HR` (105) puis `Attendance` (85) — types génériques des relations Eloquent.
- Règle d'or : **jamais de re-baseline d'une erreur corrigée** ; une PR qui touche un module ne peut pas augmenter sa baseline (garde PA2-ARCH-005).

## Gardes en place

1. `check-phpstan-baseline-delta.sh` — ratchet par module, exécuté sur chaque PR dans `architecture-check.yml`.
2. `check-phpstan-baseline-debt.sh --ci` — ratchet global « 0 nouvelle entrée » vs `main` (métrique de pilotage).

## Suivi

Le rapport est régénéré à chaque lot de réduction ; ce fichier doit refléter la dernière mesure.
