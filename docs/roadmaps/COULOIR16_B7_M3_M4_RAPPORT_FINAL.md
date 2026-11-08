# Couloir 16 — Rapport de fin de lot (reprise B7 / M3 / M4)

**Date** : 8 novembre 2026
**Dépôt** : `kitokoh/leopardo-hr` (compte Kitokoh)
**Branche de travail** : `fix/couloir16-lighthouse-public-route`, `fix/couloir16-b7-contention`, `fix/couloir16-connection-matrix-refresh`
**PR ouvertes, jamais mergées** : #9183, #9185, #9186

---

## 0. Ce que ce run a réellement fait

Le run précédent avait livré B7 (#9161) et M3 (#9160). **Le propriétaire a mergé les deux.** Ce run a donc commencé par **mesurer ce que ces livraisons avaient produit en CI réelle** — et le résultat contredit une partie du travail livré.

Trois constats, dans l'ordre d'importance :

1. **Le découpage B7 produisait 229 faux échecs.** Corrigé (#9183).
2. **Le constat B7 lui-même est partiellement périmé** : la suite complète termine désormais en ~82 min.
3. **Trois critères d'acceptation de M4 étaient déclarés mais non satisfaits.** Corrigés (#9186).

---

## 1. B7 — le découpage produisait 229 faux échecs

### La mesure qui tranche

Même commit, deux exécutions :

| | suite complète (`tests.yml`, run 42408094007) | shards (`backend-feature-shards`, run 42408094079) |
|---|---|---|
| tests | 8769 | 8367 |
| échecs | 126 | 135 |
| **erreurs** | **21** | **254** |

**Sur les 254 tests en ERREUR dans les shards, 229 PASSENT dans la suite complète.**

Signatures fautives, **absentes de la suite complète** :
- **209 × `statement timeout`** (SQLSTATE 57014)
- **19 × `relation "public.companies" does not exist`** (42P01)

Exemple vérifié : `Tests\Feature\Console\ModulesConsolidateApplyTest::Apply converges a divergent company` → **erreur** dans le shard `Console`, **ok** dans la suite complète.

### La cause

Les 77 jobs étaient lancés **sans `max-parallel`**, donc simultanément. La contention de ressources transforme le `statement_timeout = '300s'` (posé par `setup-backend-db`) en erreurs, et fait échouer des tests qui passent seuls.

**Un outil de découpage qui invente des échecs est pire que pas d'outil** : il envoie corriger des bugs inexistants.

### Le constat B7 est partiellement périmé

Le job `Backend` de `tests.yml` **termine désormais en ~82 min** (run 42408094007 : 4947,98 s, 8610 passed, 147 failed pré-existants). La suite n'a plus besoin d'être découpée pour tenir dans le runner.

### Le correctif (#9183)

1. Le workflow ne se déclenche plus que **manuellement** (`workflow_dispatch`) — il ne concurrence plus `tests.yml`, qui reste la **seule** source de verdict.
2. `max-parallel: 6` borne la contention quand on le lance à la main.

### Ce qui reste valide et vérifié

- Le **mécanisme** de découpage : testsuite `<file>` explicite, seul honoré par paratest (vérifié en exécution réelle).
- La **partition** sur `origin/main` : **1259 fichiers, 0 doublon, 0 manquant**.
- Le **générateur** : `--check` → exit 0, `php -l` → clean.

### Durées par shard (relevées — décision B7-a)

L'équilibrage par *nombre de méthodes* n'équilibre pas le *temps* :

| shard | durée |
|---|---|
| Security | 5833 s |
| FeatureRoot3 | 4864 s |
| FeatureRoot1 | 3586 s |
| … | … |
| Queue | 2 s |
| Logging | 0,3 s |

Un rééquilibrage par durée mesurée serait nécessaire — mais il n'a plus d'objet puisque le workflow redevient un diagnostic manuel.

---

## 2. M3 — les 4 échecs préexistants (rappel, mergé en #9160)

Les 4 échecs n'étaient pas des « tests à réparer » : **trois défauts réels** et un constat d'audit périmé.

- **`TenantManager::withinTenant()`** exécutait `SET search_path` **sans garde** dans son `finally` : après une requête en échec, PostgreSQL refuse toute instruction (`25P02`), donc le nettoyage **remplaçait** l'exception d'origine — un `23502` (NOT NULL) devenait un `25P02` illisible. **Ce correctif améliore le diagnostic en production.**
- **`restaurant_orders.branch_id` NOT NULL** dans le test.
- **Profil public non résoluble** : feature `restaurantmanager` inactive + établissement non publié → le 404 était le comportement *fail-closed correct*.

**Preuves exécutées** : `PublicActivityTest` **14 passed / 39 assertions** ; shard `LeopardoPublic` **73 passed / 378 assertions**. Aucune assertion affaiblie ; celle du bridge test **renforcée**.

**Préexistence prouvée par exécution** (worktree séparé sur `origin/main`) : `PublicActivityTest` **3 failed / 11 passed**, `PublicIdentityBridgeTest` **1 failed / 7 passed** → les 4 échecs, confirmés.

---

## 3. M4 — trois critères déclarés mais non satisfaits (#9186)

### 3.1 Budget de poids « vérifié en CI » — il ne l'était pas

`PUBLIC_APP_BUDGETS.md` §4 fixe des budgets par image (tuile ≤ 40 Ko, en-tête ≤ 120 Ko). **Aucun contrôle de poids n'existait** : le budget était écrit, jamais mesuré.

**Correctif** : `front/web/scripts/check-public-image-budget.mjs` — mesure les images **versionnées** (`front/web/public/**`), échoue au-delà du plafond par fichier (300 Ko) ou du total (1,5 Mo). Nouveau job `image-budget` **BLOQUANT** dans `lighthouse.yml`, séparé du job `lighthouse` (non bloquant, PA2-QA-008) : un poids est une contrainte dure, un score non.

**Pourquoi des plafonds et non les cibles du document** : les valeurs de §4 sont des **propositions non calibrées** (§7). Les figer fermerait des lots sur des chiffres inventés.

**Preuves** : `--report` → **17 fichiers, 650,3 Ko, exit 0** ; plafond abaissé à 10 Ko → **exit 1** (la garde mord) ; job CI **passé** (conclusion `success`).

**Périmètre honnête** : le script ne mesure **pas** les images de commerces, qui viennent de l'API à l'exécution et relèvent du pipeline d'images côté service.

### 3.2 Lighthouse sur la route publique — elle n'était pas auditée

`PUBLIC_APP_BUDGETS.md` désigne `/decouvrir` comme la route PWA publique et Lighthouse comme sa mesure. Or `lighthouserc.json` auditait neuf URLs, **toutes des pages du produit RH**, aucune route publique.

**Correctif** : `/decouvrir` ajoutée à la liste auditée (job toujours non bloquant).

**Preuve** : `lighthouse-public-route.test.ts` → **3 passed** ; garde vérifiée en retirant `/decouvrir` → **1 failed**.

### 3.3 Cache de la dernière recherche hors ligne — il n'existait pas

`DiscoveryExplorer.fetchResults()` basculait **directement** sur l'état d'erreur : une recherche réussie puis une coupure réseau affichaient « Impossible de charger les résultats » — alors que la réponse précédente était encore en mémoire.

**Correctif** : `front/web/src/lib/offline/public-discovery-cache.ts` — persiste la dernière recherche réussie, la restitue hors ligne, et l'écran **le dit** (« Réseau indisponible — voici votre dernière recherche »).

**Décisions de conception** :
- **La position n'est jamais mise en cache** : une recherche par proximité (`near`) n'écrit ni ne lit le cache ; un test vérifie l'absence de `near`/`latitude`/`longitude` dans l'entrée persistée.
- **Pas de file d'attente ici** : une recherche est une **lecture**, pas une écriture — rien à rejouer au retour du réseau, donc pas de clé d'idempotence (contrairement à la file des messages).
- **Tolérance** : `localStorage` absent (SSR, navigation privée), plein (`QuotaExceededError`) ou corrompu → dégradation vers « pas de cache », jamais un plantage ; une entrée invalide est **purgée**.

**Preuves exécutées** :
- `public-discovery-cache.test.ts` → **8 passed**
- `discovery-explorer.test.tsx` → **14 passed** (dont 2 nouveaux : cache restitué + erreur honnête sans cache)
- **e2e Playwright** `-g "hors ligne" --project=chromium` → **1 passed (23,8 s)**

**Piège mesuré en e2e** : `apiFetch` réessaie 2 fois avec un backoff de 3 s puis 6 s (cold-start Render) ; l'échec n'est définitif qu'après ~9 s, donc le délai par défaut de 5 s expirait **avant** que le cache ne prenne le relais. Timeout porté à 20 s.

### 3.4 i18n

Clé `discovery.offlineCacheNotice` ajoutée aux **4 langues** (fr, en, ar RTL, tr) via `shared/i18n/locales/`, puis :
- `sync-web.js` → `I18N_SYNC_WEB_OK`
- `sync-mobile.js` → `I18N_SYNC_MOBILE_OK`
- `check-i18n-catalog-parity.sh` → parité OK

---

## 4. Seuils de qualité — aucun mesuré

| seuil | état |
|---|---|
| Premier écran utile < 3 s en réseau lent simulé | **en attente** — pas d'appareil Android, pas de bridage réseau |
| Taille d'app et poids d'images dans le budget | **partiel** — poids des images versionnées mesuré (650,3 Ko) et gardé en CI ; taille d'app Flutter non mesurable (pas de Flutter) |
| Zéro plantage sur les 20 dernières exécutions e2e | **en attente** — pas de campagne e2e complète |
| Lighthouse mobile mesuré avant/après | **en attente** — route désormais auditée, chiffres à venir du run CI |
| Couverture sur la logique critique | **en attente** — pas de seuil chiffré calibré |

**Aucun seuil n'est déclaré tenu.** Un seuil non mesurable dans cet environnement est déclaré en attente, jamais atteint.

---

## 5. Décisions en attente

| # | décision | état |
|---|---|---|
| **B7-a** | Granularité du découpage | **sans objet** — le workflow redevient un diagnostic manuel ; durées relevées si besoin |
| **B7-b** | `--processes=4` sur 2 vCPU | **sans objet** — idem |
| **B1/B2** | Appareil de référence, porte (c) | **en attente** — aucune dépense engagée |
| **M2** | Porte (b) | **en attente** — L1 et contrat L4 |
| **M4** | Distribution aux testeurs (Firebase App Distribution) | **en attente** — porte (c) |
| **M4** | Lighthouse avant/après chiffré | **en attente** — run CI sur la route nouvellement auditée |

---

## 6. Portes respectées

- **Une PR par lot, ouverte et jamais mergée** : #9183, #9185, #9186.
- **Aucun merge, aucun déploiement, aucun paiement, aucune dépense.**
- **Aucun Bounded Context ajouté.**
- **Aucune ville, aucun pays, aucune catégorie codés en dur.**
- **Aucun test cité sans exécution ; aucun seuil non mesuré présenté comme tenu.**
- **M2 non touchée** (porte (b)).

---

## 7. Fichiers livrés

| fichier | rôle |
|---|---|
| `.github/workflows/backend-feature-shards.yml` | découpage → diagnostic manuel, `max-parallel: 6` |
| `api/dev-tools/generate-feature-shards.php` | générateur, avertissement contention |
| `front/web/scripts/check-public-image-budget.mjs` | garde de poids des images publiques |
| `.github/workflows/lighthouse.yml` | job `image-budget` bloquant + `/decouvrir` auditée |
| `front/web/src/lib/offline/public-discovery-cache.ts` | cache de la dernière recherche |
| `front/web/src/app/decouvrir/discovery-explorer.tsx` | restitution hors ligne + avis de cache |
| `front/web/src/lib/offline/__tests__/public-discovery-cache.test.ts` | 8 tests |
| `front/web/src/app/decouvrir/__tests__/discovery-explorer.test.tsx` | 14 tests |
| `front/web/e2e/leopardo-public-parcours.spec.ts` | parcours hors ligne e2e |
| `shared/i18n/locales/{fr,en,ar,tr}.json` | clé `discovery.offlineCacheNotice` |
| `CHANGELOG.md` | entrées des trois correctifs |

---

*Le token d'accès reste à révoquer par le propriétaire.*
