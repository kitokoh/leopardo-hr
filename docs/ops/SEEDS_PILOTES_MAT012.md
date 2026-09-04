# Seeds pilotes & données synthétiques — MAT-012 (#5870)

> Programme de maturité BC-01 PLATFORM — issue [MAT-012 #5870](https://github.com/kitokoh/leopardo-hr/issues/5870).
> Pattern de référence : `CrmPilotSeeder` (#5743) — environnement reproductible,
> non sensible, démontrable.

## Objectif

Créer des seeds reproductibles par BC/verticale, **sans secret ni donnée
réelle**, pour le développement et la recette — idempotents, nettoyables et
incapables de cibler un tenant de production par erreur.

## Livrables

| Livrable | Rôle |
|---|---|
| `database/seeders/FuelStationPilotSeeder.php` | Tenant déterministe `fuel-pilot-001` (BC-15 FUEL) : station ST-ALG-01, 3 produits, 2 pompes, 1 cuve, 1 shift, 3 ventes synthétiques (montants calculés à la main), pompiste démo |
| `database/seeders/EduManagerPilotSeeder.php` | Tenant déterministe `edu-pilot-001` (BC-16 EDU) : campus, élèves, guardians — **skip gracieux** tant que le module EduManager (#5974) n'est pas sur main |
| `database/seeders/Concerns/GuardsPilotSeeding.php` | Garde d'environnement : refus hors local/development/testing/staging, sauf `ALLOW_PILOT_SEEDING=true` explicite |
| `app/Console/Commands/PilotSeedCommand.php` | `pilot:seed --solution=fuel|edu` (crée) / `--clean` (nettoie le tenant pilote) |

## Critères d'acceptation (vérifiés par `tests/Feature/PilotSeedsTest.php`)

1. **Reproductibles** : valeurs 100 % déterministes (ventes 5 800 / 6 750 /
   1 800 DZD — calcul manuel), emails en `.leopardo.test`, aucun secret réel.
2. **Idempotents** : réexécuter le seeder = skip, aucune donnée dupliquée.
3. **Nettoyables** : `pilot:seed --solution=fuel --clean` supprime le tenant
   pilote et toutes ses lignes (fuel_*, edu_*, employees).
4. **Jamais de cible production** : la garde `GuardsPilotSeeding` lève une
   exception hors environnements autorisés (testé avec `env=production`).

## Usage

```bash
# Développement / recette uniquement
php artisan pilot:seed --solution=fuel        # crée fuel-pilot-001
php artisan pilot:seed --solution=edu         # crée edu-pilot-001 (si module livré)
php artisan pilot:seed --solution=fuel --clean # supprime fuel-pilot-001
```

## Compléments

- Runbooks pilotes : `docs/ops/RUNBOOK_PILOT_FUELSTATION.md`,
  `docs/ops/RUNBOOK_PILOT_EDUMANAGER.md` (seeds `fuel-pilot-001` / `edu-pilot-001`
  désormais implémentés).
- Gates go/no-go : `dev-hub/tools/pilot-gates.json` (MAT-018) — un pilote ne
  part en production qu'après recette signée.
