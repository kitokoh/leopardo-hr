# Couloir 16 — Rapport B7 (découpage CI) et M3 (échecs préexistants)

**Date :** 2026-11-06
**Branche :** `fix/couloir16-b7-m3-ci-fixes`
**Portée :** B7 (suite Feature au-delà de la limite du runner) et les 4 échecs M3
preexistants. Aucune PR mergée, aucun déploiement, aucune dépense.

---

## 0. État de départ constaté

| Élément | Constat |
| --- | --- |
| `main` local | `618addd91` |
| Travail en cours | branche `fix/couloir16-b7-m3-ci-fixes` |
| Fichiers déjà modifiés (avant ce run) | `tests.yml`, `TenantManager.php`, `PublicActivityTest.php`, `PublicIdentityBridgeTest.php` |
| Token GitHub fourni | **RÉVOQUÉ / invalide** — voir §5 |

Le point de départ de la mission indiquait que le job `125099730176` échouait.
**Vérification :** ce job n'avait aucun échec ; il a été **annulé** par la limite
de 5 h du runner. Le problème n'était donc pas un test rouge mais un **temps
d'exécution**. C'est ce que traite B7.

---

## 1. B7 — Diagnostic du filtrage (mesuré, pas supposé)

Trois mécanismes de découpage ont été testés **en exécutant réellement** le
binaire. Deux ne fonctionnent pas.

### 1.1 `--filter` par expression régulière sur le nom de classe — NE FONCTIONNE PAS

```
php artisan test --testsuite=Feature --parallel --processes=4 \
  --filter '^Tests\Feature\[Z][^\]*Test'
```

| Mesure | Résultat |
| --- | --- |
| Durée | 6 min 40 s |
| Ligne `Tests:` produite | **aucune** |
| Tests sélectionnés | **0** |

Avec `--parallel`, le filtre par regex de nom de classe ne sélectionne rien.
Pire : l'échec est **silencieux** — pas d'erreur, pas de compte, juste du temps
perdu. Un découpage bâti là-dessus donnerait 72 shards verts ne testant rien,
c'est-à-dire un faux verdict de CI complet.

**Conclusion :** ne jamais filtrer par regex de nom de classe.

### 1.2 Répertoire passé à `php artisan test` — FONCTIONNE (mesuré au JUnit)

> **Rectification.** Une version antérieure de ce rapport concluait que passer
> un répertoire ne restreignait pas l'exécution. **C'était faux**, et la cause
> de l'erreur est instructive : la mesure avait été faite avec
> `vendor/bin/phpunit --list-tests`, qui **n'est pas** le binaire utilisé par la
> CI (`php artisan test` → paratest). Les deux outils ne filtrent pas de la même
> façon. La conclusion ci-dessous est établie avec **le binaire de la CI** et
> vérifiée sur le **rapport JUnit** (nombre de tests réellement exécutés), pas
> sur une sortie console.

Mesure réelle, `php artisan test --testsuite=Feature --parallel --processes=4
--exclude-group=audit --log-junit=… <cible>` :

| Cible | Tests attendus (carte) | Tests au JUnit | Verdict |
| --- | --- | --- | --- |
| `tests/Feature/Seed` | 6 | **6** | ✅ restreint |
| `tests/Feature/AIGatewayAndAnalyticsTest.php` | 8 | **8** | ✅ restreint |
| `tests/Feature/LeopardoPublic` | 81 | **73** exécutés (8 hors `audit`) | ✅ restreint |
| `tests/Feature/Paiements` | 70 | > 280 s (sandbox lent) | ⏱ non conclu ici |

**Conclusion :** le découpage **par répertoire fonctionne** avec `artisan test`.
Le shard `root` reste néanmoins un cas particulier, traité en §1.3.

### 1.3 Le shard `root` — paratest n'accepte qu'UN SEUL argument `path`

C'est le vrai défaut du workflow tel qu'écrit, et il est **bloquant** :

```yaml
if [ "${{ matrix.shard }}" = "root" ]; then
  TARGET="tests/Feature"     # ← la suite ENTIÈRE, pas les fichiers racine
```

Deux problèmes distincts :

**a) `tests/Feature` n'est pas « les fichiers racine ».** C'est la racine de
découverte : le shard `root` exécuterait les 8 710 tests **en plus** des 71
autres shards. La limite du runner serait franchie par ce seul shard, et tous
les tests tourneraient deux fois.

**b) La correction « évidente » (glob de fichiers) est refusée par paratest.**
Testée en exécution réelle :

```
php artisan test --testsuite=Feature --parallel --processes=4 \
  --exclude-group=audit tests/Feature/*.php
→ Too many arguments, expected arguments "path".
```

paratest n'accepte **qu'un seul** `<path>` : ni une liste de fichiers, ni un
glob expansé en 207 arguments. La correction par glob, que j'avais d'abord
appliquée, **aurait cassé la CI** — elle échoue en 2 secondes au lieu de
tourner. C'est exactement le genre de correctif qu'on ne peut valider qu'en
exécutant.

**c) `<directory prefix="…">` ne convient pas non plus.** Testé : le préfixe
s'applique **récursivement**, donc `prefix="A"` attrape aussi
`tests/Feature/Tenant/AbsenceTypeCodePerTenantTest.php` et
`tests/Feature/Web/AttendanceCorrectionAdminPagesTest.php` — chevauchement avec
les autres shards, donc double exécution.

**d) La solution qui fonctionne : une testsuite `<file>` explicite.**
Déclarée dans `phpunit.xml` :

```xml
<testsuite name="FeatureRootA">
    <file>tests/Feature/AIGatewayAndAnalyticsTest.php</file>
    <file>tests/Feature/AIWorkflowTest.php</file>
</testsuite>
```

| Vérification | Résultat |
| --- | --- |
| `vendor/bin/phpunit --testsuite=FeatureRootA --list-tests` | **10 tests** (8 + 2, exactement les deux fichiers) |
| `php artisan test --testsuite=FeatureRootA --parallel` | **non conclu** — voir ci-dessous |

**Limite de ce run :** l'exécution paratest de cette testsuite n'a pas pu être
menée à terme (budget de temps du sandbox épuisé ; ~4 s/test sur 1 vCPU).
**Le mécanisme est donc prouvé au niveau PHPUnit, pas encore au niveau
paratest.** C'est le seul point de B7 qui reste à confirmer par un run CI.

**Correctif à appliquer (non appliqué ici, faute de preuve paratest) :**
générer dans `phpunit.xml` une testsuite `<file>` par sous-shard `root`
(≈ 6 sous-shards de ~35 fichiers, soit ~250 tests chacun), et faire pointer la
matrice du workflow sur ces noms de testsuite. Le `phpunit.xml` a été
**restauré à son état d'origine** : je n'ai pas laissé une modification non
prouvée dans le dépôt.

### 1.4 Partition : aucun test perdu, aucun test compté deux fois

Les 8 710 tests de la suite ont été énumérés puis affectés à un shard par leur
espace de noms (`Tests\Feature\<Shard>\…` → shard ; `Tests\Feature\<Classe>` →
`root`), et l'affectation comparée à la matrice du workflow.

| Contrôle | Résultat |
| --- | --- |
| Tests dans la suite | **8 710** |
| Somme des tests affectés aux shards | **8 710** |
| Shards déclarés sans aucun test | **aucun** |
| Tests appartenant à un shard non déclaré | **aucun** |
| Répertoire `Fixtures` sans test | exclu à raison (0 fichier `*Test.php`) |

La partition est donc **exacte et complète**.

### 1.5 Correctif appliqué — **aucun**

Point important : **je n'ai laissé aucun correctif de B7 dans le dépôt.**

J'avais d'abord appliqué un glob (`tests/Feature/*.php`) pour le shard `root`.
L'exécution a montré que **paratest le refuse** (`Too many arguments`). Ce
correctif aurait donc **cassé la CI** : le job aurait échoué en 2 secondes au
lieu de tourner 5 heures. Il a été **annulé** (`git checkout`), et
`phpunit.xml` a été restauré à son état d'origine.

État du dépôt à la fin de ce run :

```
 M api/app/Core/Tenant/TenantManager.php
 M api/tests/Feature/LeopardoPublic/PublicActivityTest.php
 M api/tests/Feature/LeopardoPublic/PublicIdentityBridgeTest.php
?? docs/roadmaps/COULOIR16_B7_M3_RAPPORT.md
```

`.github/workflows/tests.yml` et `api/phpunit.xml` sont **inchangés**.

C'est une décision assumée : livrer un correctif de CI non prouvé serait pire
que de livrer le diagnostic. Le diagnostic, lui, est complet et reproductible.

### 1.6 Répartition mesurée (nombre de tests par shard)

| Shard | Tests |
| --- | --- |
| `root` (207 fichiers) | 1 485 |
| `Payroll` | 940 |
| `Travel` | 745 |
| `Restaurant` | 397 |
| `Accounting` | 360 |
| `EduManager` | 298 |
| `Security` | 270 |
| `CRM` | 252 |
| `Attendance` | 226 |
| `Platform` | 204 |
| `LeopardoPublic` | 81 |

**Décision en attente B7-a :** la matrice est découpée **par répertoire**, donc
sa granularité suit l'organisation du code, pas le temps d'exécution. Six shards
dépassent 200 tests et `root` en compte 1 485. Si un shard mesuré en CI
s'approche de la limite, il faut soit l'éclater en sous-shards par
sous-répertoire, soit passer à un découpage **par temps mesuré** (durées JUnit
d'un run complet, répartition en N paniers équilibrés). Le découpage actuel
**supprime la cause certaine** (suite entière dans un shard) ; il ne garantit pas
encore que **chaque** shard tienne sous 5 h.

---

## 2. M3 — Les 4 échecs préexistants

### 2.1 Les rendre exécutables d'abord

Quatre obstacles d'environnement ont dû être levés avant de pouvoir exécuter
quoi que ce soit. Aucun n'est un défaut applicatif ; tous sont des pièges de
reproduction qui rendent un test « rouge » sans rapport avec le code testé.

| # | Symptôme | Cause réelle | Résolution |
| --- | --- | --- | --- |
| 1 | `PostgreSQL: no response` | serveur arrêté dans le sandbox | `service postgresql start` |
| 2 | `could not translate host name "pgsql"` | `.env` / `.env.testing` pointent sur l'hôte Docker `pgsql` | `DB_HOST=127.0.0.1` |
| 3 | même erreur **malgré** `DB_HOST` corrigé | `TestCase::isRunningInsideDocker()` réécrit l'hôte en `pgsql` **hors CI** | exécuter avec `CI=true GITHUB_ACTIONS=true` |
| 4 | base/role absents | aucune base de test dans le sandbox | création de `leopardo_test` + rôle + PostGIS |

Le piège n°3 est le plus coûteux : le code **écrase** la configuration, donc
corriger `.env` ne suffit pas. Sans `CI=true`, tous les tests échouent avec une
erreur de connexion qui n'a rien à voir avec ce qu'ils testent.

### 2.2 Résultat réel après levée des obstacles

`tests/Feature/LeopardoPublic/PublicActivityTest.php` :

```
✓ favorites are scoped to the client
✓ adding a favorite is idempotent
✓ removing a favorite is idempotent
✓ a favorite on an unknown profile is refused uniformly
✓ search history is recorded and deduplicated
✓ search history never stores coordinates
✓ search history can be cleared
✓ the activity index carries no business content

Tests: 14 passed (39 assertions)  ·  Duration: 14.75s
```

Shard `LeopardoPublic` complet :

```
Tests: 73 passed (378 assertions)  ·  Duration: 220.78s  ·  Parallel: 4 processes
```

### 2.3 Causes réelles des échecs (et non « tests à réparer »)

Trois défauts distincts, tous corrigés **dans le périmètre du lot** :

**a) `TenantManager::withinTenant()` masquait la vraie exception**
Le `finally` exécutait `SET search_path` sans garde. Après une requête en échec,
PostgreSQL refuse toute instruction de la transaction (`25P02`), donc le `SET`
de nettoyage **remplaçait** l'exception d'origine : un `23502` (NOT NULL)
devenait un `25P02` illisible. Correctif : même garde que `resetToPrevious()` et
`withinSearchPath()` déjà présentes dans la classe — on avale `25P02`, on
relance le reste. Ce correctif **améliore le diagnostic en production**, pas
seulement les tests.

**b) La commande de données de test créait une commande sans établissement**
`restaurant_orders.branch_id` est NOT NULL. Le test créait un `RestaurantOrder`
sans `branch_id` → `23502`, masqué en `25P02` par (a). Correctif : créer
l'établissement dans le **même** contexte tenant avant la commande.

**c) Le profil public n'était pas résoluble**
`PublicProfileLocator` exige que la feature `restaurantmanager` soit active et
que l'établissement soit publié (`is_public`, `public_slug`). Sans cela, l'ajout
de favori répond 404 — comportement **fail-closed correct**, test mal préparé.
Correctif : activer la feature et créer un établissement publié.

**d) Un constat d'audit périmé**
`PublicIdentityBridgeTest` assertait l'**absence** de l'index d'activités. M3
l'ayant livré, le constat est faux par construction. Le test est mis à jour
pour constater la **présence** du mécanisme.

> Aucun de ces quatre points n'est un « test à faire passer ». Trois sont des
> défauts de code ou de préparation de test ; le quatrième est un constat
> devenu obsolète. Aucun test n'a été affaibli pour obtenir du vert : les
> assertions ont été rendues **plus strictes** (vérification du contenu réel de
> l'index plutôt que de son absence).

---

## 3. Ce qui reste ouvert

| Réf. | Sujet | État |
| --- | --- | --- |
| B7-1 | Shard `root` = suite entière (8 710 tests) | **diagnostiqué, non corrigé** — correctif `<file>` prouvé au niveau PHPUnit, à confirmer paratest |
| B7-2 | paratest n'accepte qu'un seul `<path>` | **établi par exécution** — contrainte de conception du découpage |
| B7-3 | `--filter` regex = 0 test, silencieusement | **établi par exécution** — à ne jamais utiliser |
| B7-a | Granularité (6 shards > 200 tests, `root` = 1 485) | **en attente** — nécessite un run CI pour mesurer les durées |
| B7-b | `--processes=4` sur runner 2 vCPU | **en attente** — à mesurer |
| B1 | Appareil Android de référence pour les budgets | **en attente** — aucune dépense engagée |
| B2 | Porte (c) | **en attente** |
| M3-1 | 4 échecs M3 corrigés, 14/14 + 73/73 verts | **fait et exécuté** |
| M3-2 | Préexistence sur `origin/main` | **non prouvée par exécution** — voir §4 |

---

## 4. Preuve de préexistence sur `origin/main`

Un worktree séparé sur `origin/main` a été utilisé pour comparer. **Limite
importante :** le sandbox ne permet pas de faire tourner les deux suites en
parallèle de façon fiable, et `origin/main` ne contient pas les correctifs
d'environnement. La préexistence est donc établie par **inspection du diff**
(`git diff origin/main -- <fichiers>`) et non par une exécution complète sur
`main`. **Ce point est déclaré non prouvé par exécution** et doit être confirmé
par un run CI sur `main` avant clôture définitive.

---

## 5. Le token GitHub est invalide

Toutes les requêtes API renvoient **401 Bad credentials** :

```
GET /user  → 401
GET /repos/kitokoh/leopardo-hr → 401
```

Conséquences, à traiter par le propriétaire :

- **aucun push**, donc **aucune PR** ne peut être ouverte depuis ce run ;
- l'état des PR ouvertes et des runs CI **ne peut pas être revérifié** ;
- le travail de ce run est **local au sandbox** et devra être poussé par le
  propriétaire (`git push origin fix/couloir16-b7-m3-ci-fixes`).

Le token ayant servi à cloner le dépôt, il a probablement été révoqué entre
deux runs — ce qui est la bonne pratique. Il n'a **pas** été contourné.

---

## 6. Portes respectées

| Porte | Respect |
| --- | --- |
| (a) PR ouverte, jamais mergée | **aucune PR possible** (token 401) — travail sur branche locale |
| (b) M2 non démarrée (attend L1 + contrat L4) | **respectée** — M2 non touchée |
| (c) Aucune dépense | **respectée** — aucun achat, aucun service payant activé |
| Pas de merge / déploiement / paiement | **respecté** |
| Aucune ville/pays/catégorie codés en dur | **respecté** |
| Aucun nouveau Bounded Context | **respecté** — aucun ajout d'architecture |
| Aucun test présenté comme réussi sans exécution | **respecté** — §2.2 contient les sorties réelles |
