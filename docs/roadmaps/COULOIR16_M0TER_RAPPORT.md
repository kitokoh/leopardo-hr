# Couloir 16 — Rapport de fin de lot M0-ter

**Date** : 3 novembre 2026
**Lot** : M0-ter — compléments M0 (budgets + données synthétiques)
**PR** : [#9120](https://github.com/kitokoh/leopardo-hr/pull/9120) — **ouverte, jamais mergée** (porte (a))
**Branche** : `feat/couloir16-m0-ter-budgets-synthetic-data`
**Commits** : `8ad0c658d` (livrable), `c950f94f6` (correctif issu de l'exécution)

---

## 1. Ce qui a été fait, et ce qui existait déjà

La mission demandait de **terminer** le travail là où il s'était arrêté, sans refaire l'existant. État trouvé :

| Élément | État trouvé | Action |
|---|---|---|
| M0 — architecture, contrats, seuil de catégories | livré (PR #9101) | rien refait |
| M0 — `PUBLIC_PROFILE_DATA_MODEL.md` | livré | rien refait |
| M0 — maquettes 360 px et RTL | livré (`docs/design/couloir16-mockups/`) | rien refait |
| **M0 — budgets** | **absent** | **créé** |
| **M0 — générateur de données synthétiques** | **absent** | **créé** |
| M1 — Accueil + fiche | livré (PR #9108) | rien refait |
| M1-bis — manifeste PWA | livré (PR #9111), **issue #9103 restée ouverte** | vérifié par exécution, issue clôturée |
| M1-ter | livré (PR #9113) | rien refait |
| M3 — activités, favoris | livré, **4 tests rouges préexistants** | signalé, non corrigé (hors périmètre) |
| M2 — contact, compte, messages | **non démarré** | non démarré — porte (b) |

**Le job CI `125099730176` n'avait aucun échec** : il a été **annulé par la limite de 5 h** du job, pas par un test rouge. Le triage JUnit confirmait `0 échec(s), 0 signature(s)`. La suite feature complète du dépôt dépasse donc la limite du runner — c'est un fait d'infrastructure, pas un défaut du code.

---

## 2. Preuves exécutées

Un environnement complet a été monté : PHP 8.2 + `pdo_pgsql`, PostgreSQL 15.19 + PostGIS, `composer install`, migrations complètes, `.env` local, `npm ci` sur `front/web`.

### 2.1 Générateur de données synthétiques — 11 tests, 65 assertions

```
$ php artisan test tests/Feature/LeopardoPublic/PublicSyntheticDataTest.php

   PASS  Tests\Feature\LeopardoPublic\PublicSyntheticDataTest
  ✓ it creates one tenant per configured city                            0.31s
  ✓ it is idempotent                                                     0.14s
  ✓ published and unpublished establishments coexist                     0.10s
  ✓ every published establishment has a globally unique public slug      0.12s
  ✓ unpublished establishments have no public slug                       0.11s
  ✓ it writes the category threshold as platform data                    0.12s
  ✓ clean removes only prefixed tenants                                  0.12s
  ✓ it refuses an unknown city                                           0.08s
  ✓ it limits execution to one city                                      0.10s
  ✓ the three cities cover three scripts                                 0.07s
  ✓ no city name is hardcoded in the command                             0.08s

  Tests:    11 passed (65 assertions)
  Duration: 1.50s
```

### 2.2 Non-régression backend — suite publique complète

```
$ php artisan test tests/Feature/LeopardoPublic/
  Tests:    77 passed, 4 failed (398 assertions)
  Duration: 330.87s
```

### 2.3 Les 4 échecs sont préexistants — prouvé par comparaison

Reproduits **à l'identique sur `origin/main`**, dans un worktree séparé, sans aucun de mes changements :

```
$ git worktree add /workspace/wt_main origin/main
$ php artisan test .../PublicActivityTest.php .../PublicIdentityBridgeTest.php
  Tests:    4 failed, 18 passed (59 assertions)
```

| Test | Cause réelle | Lot |
|---|---|---|
| `PublicActivityTest` ×3 | `SQLSTATE[25P02]` — transaction avortée puis `SET search_path` | M3 |
| `PublicIdentityBridgeTest::case 4` | assertion « aucun index d'activités » — l'index M3 existe, l'assertion est périmée | M3 |

**Aucun de ces échecs n'est imputable à M0-ter.** Ils sont signalés, pas corrigés : corriger M3 dans une PR M0 mélangerait deux lots et rendrait la revue impossible.

### 2.4 Frontend — surface publique

```
$ npx jest src/app/decouvrir
Test Suites: 4 passed, 4 total
Tests:       31 passed, 31 total
```

Dont le manifeste PWA (8 tests) qui prouve la clôture de l'issue #9103.

### 2.5 i18n

```
$ node shared/i18n/validators/validate.js
I18N_VALIDATION_OK (4 locales)

$ bash dev-hub/tools/check-i18n-catalog-parity.sh
parité i18n OK (backend api/lang, shared/i18n, ARB mobile — 4 langues)
```

---

## 3. Le défaut que seule l'exécution a révélé

C'est le point le plus important de ce rapport.

Les 11 tests échouaient d'abord **tous** avec `Aucun plan en base`. À la lecture, la commande semblait correcte : `Schema::hasTable('plans')` puis `DB::table('plans')`. Instrumentation :

```
hasTable(plans)=false
hasTable(public.plans)=true
search_path=shared_tenants, public
```

Sous le `search_path` des tests, `Schema::hasTable('plans')` renvoie **faux** alors que la table existe. La commande aurait donc **échoué en test tout en fonctionnant en production**, où le `search_path` diffère.

**Ce défaut est invisible à la relecture et visible en une exécution.** C'est exactement la raison pour laquelle un test non exécuté ne vaut pas un test réussi — et pourquoi le rapport précédent, qui déclarait les tests « écrits pour la CI », ne pouvait pas conclure.

Corrigé par qualification explicite (`public.plans`), dans la commande **et** dans le `setUp` du test.

### Trois autres défauts trouvés en écrivant

1. **Double préfixe de slug** — `$company->slug` porte déjà `pub-synth-` ; le préfixer à nouveau produisait `pub-synth-pub-synth-valmora-syn-01`.
2. **`created_at` écrasé à chaque rejeu** — `updateOrInsert` le réécrivait, faisant mentir la date de création d'un établissement inchangé.
3. **`Str::slug` sur la clé de ville** — `wadi_nour` → `wadi-nour` ; le test comparait la clé brute et aurait échoué **sur la ville arabe uniquement** — un faux négatif qui ne se serait vu qu'à l'exécution.

---

## 4. Seuils mesurés

**Aucun seuil de performance n'a été mesuré.** Le document `PUBLIC_APP_BUDGETS.md` le dit en tête : ses valeurs sont des **propositions**, pas des mesures. Le sandbox n'a ni appareil Android, ni réseau bridé, ni Flutter.

| Seuil | Cible | Mesuré | Statut |
|---|---|---|---|
| Premier écran utile PWA | ≤ 3,0 s | — | **non mesuré** |
| Poids JS initial | ≤ 180 Ko | — | **non mesuré** |
| APK arm64 | ≤ 22 Mo | — | **non mesuré** |
| Démarrage Flutter à froid | ≤ 2,5 s | — | **non mesuré** |
| Plantages e2e | 0 / 20 | — | **non mesuré** |
| Couverture logique critique | ≥ 80 % | — | **non mesuré** |

**Ce qui a été mesuré, en revanche** : 11 tests backend verts (65 assertions), 31 tests frontend verts, 77/81 tests publics backend verts avec 4 échecs préexistants prouvés hors périmètre, i18n valide sur 4 locales.

Un lot ne se ferme pas si un seuil est dépassé — mais un seuil non mesuré n'est ni dépassé ni atteint. **M0-ter ne peut donc pas être déclaré « seuils tenus »** : il peut être déclaré « livrables produits, tests exécutés, seuils en attente de la campagne §7 ».

---

## 5. Décisions en attente

| # | Décision | Qui | Impact si non tranchée |
|---|---|---|---|
| B1 | Modèle exact de l'appareil de référence | Lead + design | budgets de temps non calibrés |
| B2 | Achat ou emprunt de l'appareil — **porte (c), dépense** | Lead | bloque la campagne §7 |
| B3 | Seuils Lighthouse bloquants en CI vs campagne | Lead + qualité | la CI peut fermer un lot sur du bruit |
| B4 | Politique de dégradation des images | Design | budget image théorique |
| B5 | Budget mémoire Flutter atteignable sur 3 Go ? | Mobile | à mesurer, pas à supposer |
| B6 | Les 4 échecs M3 : dans M3 ou correctif dédié ? | Lead | la suite publique reste rouge |
| B7 | La suite feature complète dépasse la limite de 5 h du runner CI — découper en shards ? | Lead + infra | chaque PR attend 5 h pour un résultat partiel |

**B7 est nouveau et important** : le job `125099730176` a été annulé à 5 h sans échec. Tant que la suite n'est pas découpée, aucune PR ne peut obtenir un verdict complet de la CI. Ce n'est pas un défaut du couloir 16, mais il bloque la preuve par la CI pour tous les lots.

---

## 6. Portes

| Porte | État |
|---|---|
| **(a) fusion de PR** | PR #9120 **ouverte**, jamais mergée par l'agent |
| **(b) M2** | **non démarrée** — attend L1 et le contrat L4 |
| **(c) dépense** | **aucune engagée** — B2 signalé, pas acheté |

---

## 7. Ce que ce lot ne prétend pas avoir fait

- Il **n'a pas** mesuré les seuils de performance.
- Il **n'a pas** exécuté la suite feature complète du dépôt (elle dépasse la limite du runner).
- Il **n'a pas** corrigé les 4 échecs M3 — ils sont hors périmètre et prouvés préexistants.
- Il **n'a pas** démarré M2.
- Il **n'a pas** créé de compte utilisateur, de commande ni de réservation dans le générateur — les raisons sont dans le docblock de la commande.
- Il **n'a pas** mergé, déployé, ni modifié de permission GitHub.

---

## 8. Prochaine étape proposée

1. **Revue de la PR #9120** par le lead (porte (a)).
2. **Trancher B7** (découpage de la suite CI) — sans cela, aucun lot ne peut être prouvé par la CI.
3. **Trancher B2** (appareil de référence) pour lancer la campagne de calibration §7.
4. **Traiter les 4 échecs M3** dans le lot M3 ou un correctif dédié (B6).
5. **M2 reste bloquée** jusqu'à L1 et le contrat L4 — ne pas la démarrer.
