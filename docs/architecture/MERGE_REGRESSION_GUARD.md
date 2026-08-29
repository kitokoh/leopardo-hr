# Garde anti-régression de merge

> **Issue :** [Closes #5519](https://github.com/kitokoh/leopardo-hr/issues/5519)
> **Job CI :** Hygiene Guards (`architecture-check.yml`) — tourne sur PR et push main.

## Contexte

Session 2026-08-25 : les merges parallèles ont cassé `main` 4 fois en une
journée, chacune silencieusement (le bootstrap PHP ou des routes tombent sans
erreur de merge) :

1. `AccountingServiceProvider` corrompu (bloc dupliqué + accolade parasite) — `syntax error` cassant artisan/PHPStan/tests (#5377, corrigé #5495) ;
2. routes publiques du portail perdues (`documents/shared/{token}` + `/download`) — controller existant mais non routé (#5495/#5377, corrigé #5504) ;
3. double `use` `AttendanceDayClosureController` dans `routes/geo.php` — `Cannot use ... because the name is already in use` (#5406, corrigé #5504) ;
4. 4 collisions de séquence migrations (#5497).

## Les 3 gardes

| Garde | Vérifie | Détecte |
|---|---|---|
| `check-public-routes.sh` | Les 38 routes publiques canoniques (`public-routes-canonical.txt`) existent dans `php artisan route:list` | Route publique perdue (controller non routé) |
| `check-duplicate-use-imports.sh` | Aucun `use` dupliqué / alias `as` dupliqué dans `routes/**` + `Providers/**` | Bootstrap des routes cassé |
| `check-providers-syntax.sh` | `php -l` sur tous les `*ServiceProvider.php` | Provider corrompu (blocs dupliqués, accolades parasites) |

Toute modification de la liste canonique passe par une PR documentée (une
suppression = route réellement retirée, jamais « plus routée par erreur »).

## Exécution locale

```bash
bash dev-hub/tools/check-public-routes.sh api
bash dev-hub/tools/check-duplicate-use-imports.sh api
bash dev-hub/tools/check-providers-syntax.sh api          # nécessite PHP
bash dev-hub/tools/tests/check-public-routes.test.sh      # 2 scénarios (sans PHP)
bash dev-hub/tools/tests/check-duplicate-use-imports.test.sh  # 3 scénarios
bash dev-hub/tools/tests/check-providers-syntax.test.sh   # 2 scénarios (PHP_BIN stub)
```

## Rollback

- Revert du commit du garde ; scripts bash autonomes sans état.
- Si un garde bloque une PR : corriger la cause (restaurer la route, dédoublonner
  l'import, réparer le provider) — ne pas désactiver le garde.
