# ADR GOV-002 — Versionnement de la famille PHPStan : de la mise en quarantaine au dégel

- **Statut** : accepté
- **Date** : 2026-10-23
- **Périmètre** : `api/composer.json` (`phpstan/phpstan`, `larastan/larastan`)
- **Garde CI concernée** : `PHPStan — Strict (Core/Modules/Shared, level 8)`
- **PRs** : #8836 (mise en quarantaine), #8837 (dégel + correctifs)
- **Antécédent** : `docs/adr/GOV-001-interfaces-orphelines.md`

## Contexte

`Le point de départ est factuel.` La PR #8836 a **figé** la famille d'analyse PHPStan, parce qu'un passage en 2.3.x faisait échouer la garde
`PHPStan — Strict (Core/Modules/Shared, level 8)` avec **42 erreurs**.

```jsonc
// état après #8836 (mise en quarantaine)
"larastan/larastan": "3.10.*",
"phpstan/phpstan": "2.2.9",
```

Deux contraintes exactes, et non des plages larges, pour empêcher un
`composer update` de repasser en 2.3.x par accident.

**Le figement était une dette délibérée, pas une solution.** Il gelait toute la
famille — y compris les correctifs de bugs et les évolutions de Larastan qui
exigent `^2.3` — et il créait une deuxième dette : la prochaine PR de dépendances
aurait buté sur ces deux lignes sans savoir pourquoi.

**Une prémisse de #8836 était fausse.** Le diagnostic alors retenu était
« phpstan 2.2 casse `ModuleRegistryGuardTest` ». Vérification faite : `main`
tournait **déjà** en phpstan 2.2.9 + larastan 3.10.0, et `ModuleRegistryGuardTest`
y passait. La régression venait de **2.3.x** sur la garde Strict level 8, et
portait sur 16 fichiers de code, pas sur un test de registre.

## Décision

### 1. La famille est dégelée en 2.3.x / 3.13.x

```jsonc
"larastan/larastan": "^3.13",
"phpstan/phpstan": "^2.3",
```

Les deux sont **couplés** : `larastan 3.13.1` exige `phpstan ^2.3.0`. Ils doivent
bouger ensemble — c'est précisément pourquoi une contrainte exacte sur l'un
bloquait l'autre.

### 2. Contrainte de version : plages `^`, pas d'épinglage

Aucun `*` exact dans `composer.json`. Un correctif d'analyseur doit pouvoir
arriver par `composer update`. Si un futur bump casse la garde, la réponse est un
**allowlist ciblé dans la baseline** ou un correctif de code — jamais un
épinglage silencieux de la famille entière.

### 3. Les 16 erreurs de 2.3.x ont été corrigées en code, pas en baseline

C'est le cœur de cet ADR. Le réflexe « ajouter 16 entrées à
`phpstan-strict-baseline.neon` » aurait été plus rapide et **aurait été une
faute** : chaque erreur révélait un typage réellement imprécis.

| Cause | Correction |
|---|---|
| Closures `map()`/`flatMap()` annotées `: array` / `: Collection` | Annotations retirées : elles écrasaient le type précis de `item()` |
| `list<...>` déclaré après `Collection::map()` | `array<int, ...>` — `map()` préserve les clés, la séquentialité n'est pas prouvable |
| Propriété `int<0, max>` assignée depuis un `int` large | Gardes métier explicites (`RuntimeException` sur coût négatif / quantité non numérique) |
| `with(['rel:id,name'])` | `select('id','name')` dans la closure — syntaxe non résolue par Larastan 3.13 |
| `sum('alias_db_raw')` | Closure de somme à type de retour explicite |
| Closure `DB::transaction()` annotée `: array` | Annotation retirée, `@var` porté par la variable locale |

**Règle générale qui en découle** : une erreur d'analyseur n'est un faux positif
que si on le **prouve**. Tant que le fichier en échec est identique à `main`, la
cause est l'analyseur — mais le défaut de typage, lui, est réel et se corrige.

### 4. Condition de rollback explicite

Revenir à la paire précédente (`2.2.9` / `3.10.*`) n'est légitime que si **les
deux** conditions sont réunies :

1. le blocage est **bloquant pour une release** (pas pour un chantier), **et**
2. le correctif de code demanderait un refactor hors périmètre (ex. toucher
   l'architecture d'un module).

Même dans ce cas, l'épinglage doit être **daté en commentaire** dans
`composer.json` et référencer cet ADR. Un épinglage sans référence est une dette
invisible.

## Conséquences

- **Positif** : plus de blocage sur les correctifs Larastan ; les types sont
  réellement plus précis dans 8 fichiers ; la prochaine PR de dépendances ne
  butera plus sur une contrainte exacte inexpliquée.
- **Coût** : la baseline PHPStan Strict a dû être nettoyée (3 entrées sont
  devenues orphelines, supprimées avec les contrats morts de GOV-001).
- **Risque assumé** : un futur `composer update` peut remonter en 2.4.x et
  réintroduire des erreurs. C'est **voulu** : la garde CI doit être le signal,
  pas une version figée.
- **Vérification locale** : l'analyse complète dépasse le plafond mémoire d'un
  sandbox à 2 Go (`SIGKILL` du worker PHPStan). Elle se mène **par module** avec
  la même configuration — même verdict, temps d'exécution plus long. À ne pas
  confondre avec une analyse partielle : `phpstan-strict.neon` et sa baseline
  sont bien appliqués intégralement.

## Références

- `api/phpstan.neon`, `api/phpstan-strict.neon`, `api/phpstan-strict-baseline.neon`
- `.github/workflows/architecture-check.yml` (job Strict level 8)
- `docs/adr/GOV-001-interfaces-orphelines.md`
- PR #8836 (quarantaine), PR #8837 (dégel)
