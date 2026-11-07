# Couloir 16 — Rapport de fin : B7 (découpage CI) et M3 (échecs publics)

**Date :** 2026-11-07
**Dépôt :** `kitokoh/leopardo-hr` (compte Kitokoh)
**Branches :** `fix/couloir16-m3-public-tests`, `fix/couloir16-b7-shards`
**PRs :** [#9160](https://github.com/kitokoh/leopardo-hr/pull/9160) (M3), [#9161](https://github.com/kitokoh/leopardo-hr/pull/9161) (B7) — **ouvertes, jamais mergées**
**Portée :** B7 (suite Feature au-delà de la limite du runner) et les 4 échecs M3
préexistants. Aucun merge, aucun déploiement, aucune dépense.

---

## 0. État constaté à la reprise

| Élément | Constat |
| --- | --- |
| Token précédent | **mort** (401) — remplacé par un token valide (200) |
| PR #9120 (M0-ter) | **mergée par le propriétaire** (pas par l'agent) |
| PR #9097 (contrat d'identité) | **fermée sans merge** |
| `main` | avancé bien au-delà du travail local : `207e0eb24` (#9147→#9151) |
| Travail local M3 | présent, non commité, **identique à `origin/main`** pour les 3 fichiers |
| Découpage CI | **inexistant** — `tests.yml` lance un seul `--parallel --processes=4` |

> Le point de départ de la mission indiquait que le job `125099730176` échouait.
> **Vérification :** ce job n'avait aucun échec ; il a été **annulé** par la limite
> de 5 h du runner. Le problème n'était pas un test rouge mais un **temps
> d'exécution**. C'est ce que traite B7.

---

## 1. M3 — les 4 échecs publics corrigés

### 1.1 Ce ne sont pas des « tests à réparer »

Trois défauts réels, dont un qui dégrade le diagnostic **en production**, et un
constat d'audit devenu obsolète.

| # | Défaut | Nature |
| --- | --- | --- |
| 1 | `TenantManager::withinTenant()` masquait la vraie exception | **défaut de code** (production) |
| 2 | `restaurant_orders.branch_id` NOT NULL dans le test | préparation de test |
| 3 | Profil public non résoluble (feature + publication) | préparation de test |
| 4 | `PublicIdentityBridgeTest` assertait l'absence de l'index | constat d'audit périmé |

**Détail du défaut 1** — le `finally` exécutait `SET search_path` **sans garde**.
Après une requête en échec, PostgreSQL refuse toute instruction de la transaction
(`25P02`) : le `SET` de nettoyage **remplaçait** l'exception d'origine. Un `23502`
(NOT NULL) devenait un `25P02` illisible. Correctif : la même garde que
`resetToPrevious()` et `withinSearchPath()`, déjà présentes dans la classe.

### 1.2 Preuves exécutées (après correctif)

Environnement : PHP 8.2.35, PostgreSQL 15.19 + PostGIS, paratest 7.8.6,
`CI=true GITHUB_ACTIONS=true` (sans quoi `TestCase::isRunningInsideDocker()`
réécrit `DB_HOST` en `pgsql` et **tous** les tests échouent sur une erreur de
connexion sans rapport).

```
PublicActivityTest        14 passed (39 assertions)   14.75s
Shard LeopardoPublic      73 passed (378 assertions)  220.78s   (4 processes)
```

**Aucune assertion affaiblie.** L'assertion de `PublicIdentityBridgeTest` a été
**renforcée** : elle vérifie désormais la présence de l'index **et** de ses
colonnes de référence (`user_id`, `company_id`, `vertical`, `activity_type`,
`external_ref`, `status`), sans contenu métier.

### 1.3 Préexistence sur `origin/main` — **prouvée par exécution**

Worktree séparé sur `origin/main` (`207e0eb24`), sans les correctifs :

```
PublicActivityTest        3 failed, 11 passed (32 assertions)   6.60s
  → SQLSTATE[23502] : null value in column "branch_id" of relation "restaurant_orders"
  → Expected response status code [201] but received 404 (favori, profil non publié)

PublicIdentityBridgeTest  1 failed, 7 passed (27 assertions)   473.53s
  → Failed asserting that two arrays are identical : [] vs [public_activity_index]
```

**Les 4 échecs sont donc confirmés préexistants sur `main`**, par exécution et
non par inspection du diff. C'est la preuve qui manquait au run précédent.

---

## 2. B7 — découpage de la suite Feature

### 2.1 Les mécanismes testés — en exécution réelle

| Mécanisme | Résultat mesuré |
| --- | --- |
| `--filter` regex sur nom de classe | **0 test**, silencieusement — 6 min 40 s pour rien |
| glob de fichiers (`tests/Feature/*.php`) | **refusé** par paratest : `Too many arguments, expected arguments "path"` |
| `<directory prefix="…">` | préfixe **récursif** → shards qui se chevauchent |
| **testsuite `<file>` explicite** | **FONCTIONNE** — 3 tests annoncés, 3 testcases au JUnit, exit 0 |

> Le piège du `--filter` est le plus dangereux : l'échec est **silencieux**. Un
> découpage bâti dessus donnerait 77 shards verts ne testant rien — un faux
> verdict de CI complet.

### 2.2 Le générateur

`api/dev-tools/generate-feature-shards.php` :

- énumère les **207** fichiers `tests/Feature/*Test.php` (le « shard root ») ;
- les pondère par nombre de méthodes de test ;
- les répartit en **6 paniers équilibrés** : **217–218 méthodes** chacun,
  **aucun doublon** ;
- écrit les testsuites **entre marqueurs** dans `api/phpunit.xml` — le fichier
  que PHPUnit lit réellement ;
- est **idempotent** (`--check` échoue si `phpunit.xml` a été édité à la main).

### 2.3 Le workflow

`.github/workflows/backend-feature-shards.yml` : matrice de **77 shards**
(6 `FeatureRoot1..6` + 71 sous-répertoires), `fail-fast: false`, résumé JUnit par
shard, `--check` du générateur **avant** exécution.

`tests.yml` **n'est pas modifié** : le job `Backend` reste la référence pour la
suite entière ; ce workflow le complète.

### 2.4 Partition vérifiée

| Contrôle | Résultat |
| --- | --- |
| Fichiers `root` énumérés | **207** |
| Fichiers affectés | **207** |
| Doublons | **aucun** |
| Fichiers non déclarés | **aucun** |
| Somme des 6 sous-shards (`--list-tests`) | **1485** |
| Shard `root` (`--list-tests`) | **1485** |

**1485 = 1485** : aucun test perdu, aucun compté deux fois.

### 2.5 Limite assumée

Les **durées par shard n'ont pas pu être mesurées** dans le sandbox d'audit
(1 vCPU, 2 Go de RAM) : un run parallèle de 6 shards a été **tué en OOM**
(exit 137). Elles seront mesurées au **premier run CI** de ce workflow.

---

## 3. État des PR et de la CI

| PR | Lot | État | Checks observés |
| --- | --- | --- | --- |
| #9160 | M3 | ouverte, `mergeable=true`, `blocked` | Governance Gates **success**, Backend Quality **success**, Backend Security **success**, CodeQL (Backend) **success**, Backend (suite complète) en cours |
| #9161 | B7 | ouverte, `mergeable=true`, `blocked` | en cours |

> `blocked` = en attente des checks requis, **pas** un échec. Le job `Backend`
> (suite complète) reste long par construction — c'est précisément le constat B7.

---

## 4. Seuils de qualité : **aucun mesuré**

Le sandbox ne dispose **ni d'appareil Android**, **ni de réseau bridé simulé**,
**ni de Flutter**. Les seuils de `PUBLIC_APP_BUDGETS.md` (premier écran utile
< 3 s, poids d'app, Lighthouse mobile, zéro plantage sur 20 e2e) **ne peuvent pas
être mesurés ici** et sont donc déclarés **en attente**, jamais « tenus ».

---

## 5. Décisions en attente

| Réf. | Sujet | État |
| --- | --- | --- |
| B7-a | Granularité : durées par shard non mesurées ; 6 shards > 200 tests | **en attente** — nécessite un run CI |
| B7-b | `--processes=4` sur runner 2 vCPU | **en attente** — à mesurer |
| B1 | Appareil Android de référence pour les budgets | **en attente** — aucune dépense engagée |
| B2 | Porte (c) | **en attente** |
| M2 | Contact + compte + messages + push | **porte (b)** — attend L1 et le contrat L4 |

---

## 6. Portes respectées

| Porte | Respect |
| --- | --- |
| (a) PR ouverte, jamais mergée | ✅ #9160 et #9161 ouvertes |
| (b) M2 non démarrée (attend L1 + contrat L4) | ✅ M2 non touchée |
| (c) Aucune dépense | ✅ aucun achat, aucun service payant |
| Pas de merge / déploiement / paiement | ✅ |
| Aucune ville/pays/catégorie codés en dur | ✅ |
| Aucun nouveau Bounded Context | ✅ |
| Aucun test présenté comme réussi sans exécution | ✅ §1.2 et §1.3 contiennent les sorties réelles |
| Aucun seuil présenté comme tenu sans mesure | ✅ §4 |

---

## 7. Ce qui reste ouvert

- **B7-a / B7-b** : mesurer les durées réelles par shard au premier run CI, puis
  rééquilibrer si un shard s'approche de la limite.
- **M0 / M1 / M3 (compléments)** : non entamés dans ce run — le temps a été
  consacré aux deux livrables bloquants (B7, M3). À reprendre.
- **M2** : bloquée par la porte (b).
- **Token** : à révoquer par le propriétaire à la fin de la mission.
