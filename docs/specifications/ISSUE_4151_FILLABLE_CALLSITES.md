# ISSUE 4151 — Sites `create()`/`update()` non-fillable (régression #3677)

> Spec rédigée selon la méthodologie spec-kit (`.github/skills/speckit-*`) —
> feature de correction, pas de nouvelle capacité.

## Contexte

Le durcissement fillable #3677 a retiré de `$fillable` :
- **Employee** : `company_id`, `role`, `manager_role`, `status`, `salary_base` ;
- **User** : `status` ;
- **SuperAdmin** : `status`, `two_fa_secret` (`role` n'a jamais été une colonne).

Mais ~280 sites (tests **et** code applicatif) passaient encore ces clés dans
`create([...])` / `update([...])` → Eloquent les **abandonne silencieusement** :
le manager de `ManagerValidationTest` était créé `role=employee` → 403 en
cascade ; `VerifyTrialSignup` créait le manager principal sans rôle ; le seeder
`DemoDzSeeder` créait des comptes demo `role=employee`. Suite Feature rouge
depuis le merge #3677 (masqué par la famine CI #3545).

## Décision

Pattern #3677 appliqué uniformément, sans `forceCreate` dans le code applicatif :

| Site | Avant | Après |
|------|-------|-------|
| `create()` assigné | `$v = M::query()->create([...sensibles...])` | `$v = M::query()->make([...fillable...]); $v->role = ...; $v->save();` |
| `create()` non assigné (tests) | `M::query()->create([...])` | `M::query()->forceCreate([...])` (précédent repo, tests uniquement) |
| `update([...])` | `$v->update(['status' => x])` | `$v->fill([...fillable...]); $v->status = x; $v->save();` |
| `SuperAdmin::create(['role' => ...])` | clé fantôme ignorée | clé retirée du tableau (pas de colonne) |
| `firstOrCreate($match, $values)` | valeurs sensibles dans `$values` | `$match` conservé, valeurs sensibles posées explicitement + `save()` |

## Critères d'acceptation

1. Zéro site restant `create([...'role'...])` / `update([...'status'...])` sur
   Employee/User/SuperAdmin (vérifié par scan statique, `REMAINING SITES: 0`).
2. `php -l` OK sur les 75 fichiers transformés.
3. Suite Feature verte (CI) — notamment `ManagerValidationTest`,
   `SmartAttendance*`, `AuthLogin*`, `PlatformAuthTest`.
4. Entrée CHANGELOG.
