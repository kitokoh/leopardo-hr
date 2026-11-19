# ADR 0035 — La doctrine `public-surfaces` s'applique aussi aux apps mobiles grand public

## Statut

**Acceptée** — 2026-11-19

**Étend** : ADR-0034 (`public-surfaces` est la norme du front grand public), qui ne couvrait que le web.

**Décideurs** : owner (validation) — rédigée par l'agent Dev
**Liens** : #9640 (cette décision) · #9641 (garde de couche) · #9642 (registre fail-closed) · #9645-#9648 (élargissement aux apps professionnelles) · ADR-0034 (doctrine web) · ADR-0026 (ModuleRegistry, même principe de registre versionné)

**Révision 2026-11-19 (#9648)** : le périmètre de la **règle de couche** est élargi à toutes les apps de `front/mobile_apps`. La **doctrine de surface** reste grand public. Voir « Révision — élargissement de la règle de couche ».

**Révision 2026-11-19 (#9652-#9654)** : la règle de couche s'étend au **front web** (`front/web/src/modules`), avec `copy/` traité comme une couche dont le domaine ne dépend jamais. Voir « Révision — élargissement au front web ».

**Révision 2026-11-19 (#9657-#9659)** : `modules/vitrine` passe de `flat` à **`layered`**. La révision précédente l'avait explicitement laissé hors périmètre ; cette exclusion est levée. Voir « Révision — `vitrine` passe en `layered` ».

---

## Contexte

### Ce qu'ADR-0034 a établi, et ce qu'elle n'a pas couvert

ADR-0034 fait de `front/web/src/modules/public-surfaces` la norme du front **web** grand public : découpe en cinq couches, `domain/` sans import framework, registre de verticales fail-closed, règle de couche opposée par un test.

Elle ne dit rien du **mobile**. Or `front/mobile_apps/leopardo_public` est la même surface produit, sur un autre canal : un visiteur qui scanne le QR code d'un commerce arrive sur la même fiche, avec la même promesse (consulter sans compte). Deux canaux, une seule doctrine — sinon la même verticale finit par porter deux identités visuelles et deux vocabulaires.

### L'état réel avant cette décision

`leopardo_public` avait une **ébauche**, pas une architecture :

| Constat | Fichier | Problème |
|---|---|---|
| `domain/` importait le framework | `lib/features/surface/domain/surface_identity.dart` | importait `material_ui` **et** `app_colors` — violation de couche, aucune garde ne l'empêchait |
| Registre non validé | même fichier | `const Map` nue : ni doublon détecté, ni identité validée, ni fail-closed testé |
| Aucune règle opposable | — | rien n'empêchait de réintroduire un import interdit |
| Features non découpées | `discovery/`, `business_profile/`, `activity/` | `data/`, `models/`, `screens/`, `widgets/` — pas de `domain/` ni d'`application/` |
| Registre mort | — | `surfaceRegistry` n'était appelé **nulle part** dans `lib/` |

Le dernier point est le plus important : une identité de surface qui ne s'affiche jamais n'est pas une architecture, c'est un commentaire. L'ébauche aurait passé une revue rapide tout en ne produisant aucun effet visible.

### Périmètre

Sur les 12 apps de `front/mobile_apps`, **une seule** est grand public : `leopardo_public`. Les 11 autres (`employee`, `manager`, `hr`, `accounting`, `marketing`, `platform_admin`, `travel_agent`, `cameras`, `pos_retail`, `pos_restaurant`, `core`) sont professionnelles ou partagées, et restent hors périmètre : elles portent la marque de leur tenant (`TenantTheme`), pas une identité de surface publique.

---

## Décision

### 1. La découpe en cinq couches s'applique à `leopardo_public`

```
lib/features/<feature>/
├── domain/          ← pur : aucun `package:`, seulement `dart:`
├── application/     ← orchestration, cas d'usage
├── infrastructure/  ← réseau, stockage, ponts vers les tokens
└── components/      ← widgets de la feature
```

Une feature peut ne pas avoir toutes les couches ; elle ne peut pas en inverser l'ordre.

### 2. `domain/` est pur, et c'est un test qui le garantit

`test/architecture/layer_guard_test.dart` parcourt `lib/features/**/domain/` et échoue si :

- un fichier importe un `package:` (framework, état, réseau, UI partagée) ;
- un fichier importe une couche supérieure (`application/`, `infrastructure/`, `components/`, `screens/`, `widgets/`, `data/`, `models/`, `providers/`) ;
- **aucun** fichier de domaine n'est trouvé (sinon un renommage de dossier rendrait la garde silencieusement vide).

La garde contient un **auto-test** : elle soumet une source fautive à son propre analyseur et vérifie qu'elle la signale. Sans cela, une regex cassée passerait au vert pour toujours.

### 3. Les couleurs sont des chaînes dans le domaine, des `Color` dans l'infrastructure

Le domaine ne connaît que `#RRGGBB`. La conversion a lieu une seule fois, dans `infrastructure/surface_palette_tokens.dart`, qui lit `AppColors` et construit les identités. Conséquences :

- le domaine est testable sans monter Flutter ;
- la garde `validate-mobile-color-tokens` (aucun littéral `Color(0x…)` hors de `app_colors.dart`) garde une frontière claire ;
- `onPrimary` est **calculé** (`pickReadableOn`), jamais saisi : une palette dont l'action s'éclaircit ne peut pas produire un libellé illisible.

### 4. Le registre est fail-closed et validé à la construction

`createSurfaceRegistry` lève si deux identités partagent une verticale, si deux vitrines revendiquent le même domaine, ou si une identité est invalide (nom vide, domaine malformé, hex invalide, contraste AA insuffisant). `get()` rend `null` pour une verticale inconnue — **jamais** une identité par défaut.

### 5. Les codes API sont déclarés, pas déduits

`SurfaceVertical` porte `apiTypeCodes` en plus de `code`. L'API de découverte émet `hospitality_property` là où le registre web nomme la verticale `hospitality`. Sans cette table, la fiche d'un hôtel ne résolvait jamais son identité et retombait silencieusement sur le thème neutre — défaut invisible en test unitaire, visible en production sur une seule verticale.

Le premier élément de `apiTypeCodes` est toujours `code` : un écart doit être déclaré explicitement, jamais déduit par convention.

### 6. Le registre doit être branché

Une identité qui n'est consommée par aucun écran ne satisfait pas cette ADR. Le point d'intégration est `business_profile_screen` : la fiche résout l'identité de sa verticale et applique `surfaceTheme()`. Une verticale inconnue garde le thème public neutre.

### 7. Les chemins et domaines sont alignés sur le registre web

`basePath` et `canonicalDomain` d'une verticale mobile sont identiques à ceux de `front/web/src/modules/public-surfaces/<vertical>/identity/`. Un test verrouille les huit chemins. Une vitrine mobile et sa vitrine web ne peuvent pas se retrouver sur deux hôtes différents.

---

## Conséquences

### Positives

- Une seule doctrine pour les deux canaux grand public : ajouter une verticale se fait au même endroit conceptuel, avec les mêmes invariants.
- La règle de couche est **exécutable** : elle survit aux correctifs pressés.
- Le domaine est testable sans Flutter, donc rapide en CI.
- L'écart `hospitality` / `hospitality_property` est désormais impossible à réintroduire silencieusement.

### Négatives / coûts assumés

- **Une indirection de plus** : une couleur traverse `AppColors` → `surface_palette_tokens` → hex → `surface_theme` → `Color`. C'est le prix de la testabilité du domaine ; il est payé une fois par verticale.
- **Les features existantes ne sont pas encore découpées** : `discovery/`, `business_profile/`, `activity/` gardent `data/`/`models/`/`screens/`. La garde ne les couvre pas (elle ne vérifie que les `domain/` existants). Leur migration est un travail distinct, non bloquant.
- **`leopardo_public` reste mono-marque** : `PublicTheme` demeure le thème par défaut ; `surfaceTheme` ne s'applique qu'aux fiches de verticale. Un visiteur qui navigue entre un restaurant et une pharmacie voit deux identités, ce qui est voulu.

### Ce que cette ADR ne décide pas

- Elle ne dit rien de l'**identité de surface** des apps professionnelles, qui continuent d'utiliser `TenantTheme` (voir la révision ci-dessous : la règle de couche, elle, s'y applique).
- Elle ne tranche pas la migration des features non découpées (voir ci-dessus).
- Elle ne crée pas d'espace candidat côté mobile : le contrat API n'en a pas.

---

## Révision — élargissement de la règle de couche (#9645-#9648)

### Le constat qui a motivé la révision

La version initiale de cette ADR arrêtait la garde de couche à `leopardo_public`. C'était une erreur de raisonnement : la garde de couche est une règle **structurelle**, pas une règle de thème. Un `domain/` qui importe `material_ui` est un défaut dans n'importe quelle app, grand public ou professionnelle.

### L'état réel des 12 apps (audit 2026-11-19, `main` @ `93ff87300`)

| App | `domain/` | Fichiers Dart | État déclaré |
|---|---|---|---|
| `leopardo_public` | **2** (`surface/`, `brand/`) | 36 | `layered` |
| `leopardo_core` | 0 | 180 | `flat` |
| `leopardo_employee` | 0 | 55 | `flat` |
| `leopardo_manager` | 0 | 40 | `flat` |
| `leopardo_travel_agent` | 0 | 18 | `flat` |
| `leopardo_cameras` | 0 | 16 | `flat` |
| `leopardo_pos_retail` | 0 | 15 | `flat` |
| `leopardo_platform_admin` | 0 | 15 | `flat` |
| `leopardo_accounting` | 0 | 14 | `flat` |
| `leopardo_pos_restaurant` | 0 | 12 | `flat` |
| `leopardo_hr` | 0 | 10 | `flat` |
| `leopardo_marketing` | 0 | 10 | `flat` |

**Aucune app professionnelle n'a de `domain/`.** Étendre naïvement la garde de `leopardo_public` aurait donc produit une garde **vide** : elle serait passée au vert en ne vérifiant rien — exactement le défaut que l'assertion « trouve au moins un fichier de domaine » corrige côté public.

### Décision 1 — la règle de couche s'étend, la doctrine de surface non

Deux choses distinctes, désormais séparées explicitement :

| | Doctrine de surface | Règle de couche |
|---|---|---|
| Contenu | identité par verticale, registre fail-closed, palette WCAG, `surfaceTheme` | `domain/` pur, pas d'inversion de dépendance |
| Périmètre | **grand public uniquement** (`leopardo_public`) | **toutes les apps** de `front/mobile_apps` |
| Pourquoi | une app professionnelle porte la marque de son tenant (`TenantTheme`), pas une identité de surface | un `domain/` impur est un défaut partout |

### Décision 2 — un registre déclare l'état de chaque app

`dev-hub/tools/mobile-layer-registry.txt` porte une ligne par app : `layered` ou `flat`, avec une note. La garde échoue si :

- une app **présente sur disque** est **absente du registre** — une nouvelle app doit être classée explicitement, jamais hériter d'un défaut par oubli ;
- une app du registre est **absente du disque** — app supprimée sans mise à jour ;
- une app déclarée `layered` n'a **aucun** `domain/` — renommage silencieux ;
- une app déclarée `flat` **a** un `domain/` — le registre ment, il faut la reclasser.

C'est la même doctrine que `mobile-feature-duplication-baseline.txt` (#7652) : la dette est **gelée et visible**, elle ne peut que décroître, et elle ne peut pas grossir en silence.

### Décision 3 — la garde est un script, pas seulement un test Dart

`dev-hub/tools/check-mobile-layer-guard.sh` exécute la même règle **sans Flutter**, en secondes, sur toutes les apps. Il est branché dans `.github/workflows/mobile-layer-guard.yml`.

Le test Dart de `leopardo_public` **reste en place** : il vérifie la règle au plus près du code, avec son auto-test. Les deux ne se remplacent pas — le script couvre l'ensemble, le test couvre le détail.

Le script porte lui aussi un **auto-test** : il soumet une source fautive à son propre analyseur et échoue si elle n'est pas signalée. Sans cela, une regex cassée passerait au vert pour toujours.

### Décision 4 — aucune violation réelle à corriger, et c'est documenté

L'audit a cherché les inversions de couche dans les 11 apps professionnelles. **Aucune n'a été trouvée.** Les cas examinés, et pourquoi ils sont légitimes :

| Cas | Verdict |
|---|---|
| `core/providers/core_providers.dart` importe `features/*/data/*_repository.dart` (9 apps) | **Composition root.** C'est le seul endroit où les dépendances sont câblées ; l'interdire obligerait à disperser les providers dans chaque feature. |
| `core/navigation/app_shell.dart` importe `features/*/screens/*` (`leopardo_public`) | **Composition root.** Le shell est le point d'assemblage de la navigation. |
| `leopardo_pos_retail/lib/core/api/retail_pos_stock_data_source.dart` importe `leopardo_core/features/stock/data/stock_repository.dart` | **Adaptateur.** La classe `implements StockDataSource` au-dessus du Dio propre au POS (file offline Hive, base URL injectée). Ce n'est pas une inversion : c'est une implémentation d'interface, et elle évite de dupliquer `StockScreen`. |
| `features/*/data/` ou `models/` important `screens/`, `widgets/`, `providers/` | **Aucun cas.** Vérifié sur les 11 apps. |
| `features/*/models/` important `dio`/`http` | **Aucun cas.** Vérifié sur les 11 apps. |

Ces cas sont documentés ici plutôt que corrigés : les « corriger » reviendrait à casser des composition roots, c'est-à-dire à dégrader le code pour satisfaire une règle mal formulée. La règle de couche porte sur `domain/`, pas sur `core/`.

### Ce que la révision ne change pas

- `leopardo_public` reste la seule app `layered` ; les 11 autres restent `flat`, dette déclarée.
- Aucune app professionnelle ne reçoit d'identité de surface ni de `surfaceTheme`.
- Aucun code applicatif n'est modifié par cette révision : elle ajoute une garde, un registre et un workflow.

---

## Révision — élargissement au front web (#9652-#9654)

### Le constat

ADR-0034 fait de `front/web/src/modules/public-surfaces` la norme du front grand public, mais la règle de couche n'était **opposable nulle part** :

| Garde existante | Périmètre réel | Ce qu'elle ne couvre pas |
|---|---|---|
| `dev-hub/tools/check-layer-purity.sh` | `api/app/Modules`, `api/app/Core` (PHP) | tout le front web |
| `modules/public-surface/__tests__/layer-guard.test.ts` | module **singulier** `public-surface` (13 fichiers) | le pluriel `public-surfaces` (173 fichiers, 8 verticales) |
| — | — | `modules/vitrine` (188 fichiers) |

Une règle qui n'a pas de garde est une intention de revue. Elle ne survit pas à trois mois de pression produit.

### Décision 1 — la règle de couche s'applique à tout `front/web/src/modules`

`dev-hub/tools/check-web-layer-guard.sh` vérifie chaque `**/domain/` d'un module déclaré `layered`. Il refuse :

- un import de framework UI (`react`, `react-dom`, `next`) ;
- un import d'une couche supérieure (`application/`, `infrastructure/`, `components/`, `copy/`, `data/`, `hooks/`, `types/`) ;
- un import qui remonte hors du module (`../../../`) ;
- un alias de chemin (`@/`, `~/`) — il peut désigner n'importe quelle couche ;
- un accès réseau ou navigateur (`fetch(`, `localStorage`, `sessionStorage`, `document.cookie`, `window.`) hors commentaire.

### Décision 2 — `copy/` est une couche, et le domaine ne la connaît pas

C'est la distinction que cette révision ajoute, et elle n'est pas cosmétique. `copy/` est le **catalogue de localisation** du module : c'est le seul endroit où une chaîne visible par un visiteur peut vivre (`check-i18n-diff.js` exempte `/public-surfaces/copy/`). Un composant **doit** l'importer. Un `domain/` **ne doit pas** : sinon une correction éditoriale change un comportement du domaine, et le domaine n'est plus testable sans charger la copie.

Le pont est explicite : `copy/seat-labels.ts` assemble les libellés de `retail-copy.ts` en un `SeatLabels` typé, que le domaine reçoit en paramètre. Le domaine ne produit plus aucun texte visible.

### Décision 3 — le registre web est fail-closed

`dev-hub/tools/web-layer-registry.txt` déclare l'état des 8 modules. Échouent :

- un module **présent sur disque** et **absent du registre** ;
- un module du registre **absent du disque** ;
- un module déclaré `layered` **sans** `domain/` ;
- un module déclaré `flat` **avec** un `domain/`.

État au 2026-11-19 :

| Module | État | Note |
|---|---|---|
| `public-surfaces` | `layered` | 8 verticales + `kernel`, 26 fichiers de domaine |
| `public-surface` | `layered` | module singulier BRANDING (BC-41), `domain/` brand + theme |
| `vitrine` | `layered` | front vitrine professionnel — `domain/` pur + `infrastructure/` depuis #9657 |
| `toolbox` | `flat` | outils horizontaux (102 fichiers) |
| `library` | `flat` | bibliothèque de contenus (36 fichiers) |
| `onboarding` | `flat` | parcours d'inscription (12 fichiers) |
| `marketing` | `flat` | pages marketing (10 fichiers) |
| `assistant` | `flat` | assistant conversationnel (9 fichiers) |

`vitrine` est passé `layered` en #9657 : la révision précédente l'avait laissé `flat` en le déclarant hors périmètre, cette exclusion est levée (voir la révision suivante).

### Décision 4 — deux violations réelles, dont une que l'audit par imports n'avait pas vue

**1. `travel/domain/seat-layout.ts` importait `copy/retail-copy`.** Trouvée par l'audit des imports. Corrigée : le domaine reçoit un `SeatLabels` ; `SeatLabels.statuses` est typé **total** sur `SeatStatusValue | 'aisle' | 'absent'`, donc ajouter un état sans libellé ne compile plus — la garantie d'exhaustivité qu'apportait le cast `as` est conservée et devient vérifiée par le compilateur.

**2. `retail/domain/buyer.ts` lisait et écrivait `window.localStorage`.** Trouvée par **la garde elle-même**, pas par l'audit : elle ne se voit pas dans les imports, seulement dans le corps des fonctions. Le domaine faisait de l'E/S navigateur, donc n'était pas testable sans DOM. L'E/S part dans `retail/infrastructure/buyer-session-storage.ts` ; le domaine garde la forme du profil, sa validation et la règle d'avis vérifié. L'API publique de `retail/index.ts` est inchangée.

C'est l'argument le plus fort pour une garde exécutable plutôt qu'une revue : la seconde violation était invisible à la lecture des imports.

### Ce que cette révision ne change pas

- `modules/vitrine` et `/business/*` ne sont **pas** refondus : seul leur état est déclaré.
- Le front Super admin et les consoles du dashboard tenant restent hors périmètre.
- Aucun module `flat` n'est converti : la dette est déclarée, pas résorbée.

---

## Révision — `vitrine` passe en `layered` (#9657-#9659)

### Ce que la révision précédente disait

`modules/vitrine` était déclaré `flat` et explicitement **hors périmètre de refonte** : « c'est le front commercial destiné aux professionnels et à leurs entreprises ». Le registre le rendait visible sans imposer un chantier non décidé.

Cette exclusion est **levée**. Le module est découpé et reclassé `layered`.

### Constat mesuré (main @ 05187853a)

Le module (192 fichiers) n'avait **aucun** `domain/`. Sa logique métier pure était dispersée dans `lib/` et `data/`, mêlée à des fichiers qui font de l'E/S :

| Fichier d'origine | Nature réelle | Destination |
|---|---|---|
| `lib/trade-intent.ts` | pur — allowlists, normalisation fail-closed, clé d'idempotence | `domain/` |
| `lib/pack-signup.ts` | pur — slugs de packs, mapping slug → code catalogue | `domain/` |
| `lib/locale-href.ts` | pur — préservation de `?lang=` dans les hrefs | `domain/` |
| `lib/antispam-fields.ts` | pur — constantes anti-bot partagées serveur/client | `domain/` |
| `lib/image-props.ts` | pur — décision `unoptimized` pour les SVG locaux | `domain/` |
| `data/currency.ts` | pur — devises, taux, conversion, formatage | `domain/` |
| `data/vitrine-numbers.ts` | pur — chiffres canoniques dérivés | `domain/` |
| `data/geo/map-paths.ts` | pur — tracés SVG générés | `domain/` |
| `data/supported-countries.ts` | **mixte** — registre pur + `fetch()` | scindé |
| `lib/consent.ts` | **E/S** — `document.cookie`, `localStorage`, `window.dispatchEvent` | scindé |

### Décision 1 — le domaine est extrait, l'E/S part en infrastructure

Deux fichiers portaient les deux responsabilités à la fois. Ils sont scindés, pas déplacés :

**`consent`** — le domaine garde la forme du consentement (`ConsentState`, `ConsentPayload`), sa validation de version (`parseConsent` rend `null` sur une version périmée, donc re-sollicitation), les fabriques pures (`deniedConsent`, `acceptAllConsent`, `buildConsentState`) et la traduction en Consent Mode v2 (`toConsentMode`). L'E/S — lecture du cookie, `localStorage`, émission du `CustomEvent` — part dans `infrastructure/consent-storage.ts`.

**`supported-countries`** — le registre canonique `SUPPORTED_COUNTRIES_FALLBACK` et la **règle** de normalisation d'une réponse (`normalizeSupportedCountries` : filtrer les pays indisponibles, ignorer un code qui n'est pas un ISO-3166 alpha-2, retomber sur le registre statique plutôt que rendre une liste vide) restent dans le domaine. Le `fetch` part dans `infrastructure/supported-countries-client.ts`.

La règle de normalisation était auparavant **inaccessible au test** : elle vivait dans le corps d'une fonction `async` qui appelait `fetch`, donc la tester exigeait un mock de `fetch` — on testait le mock, pas la règle. Elle est maintenant une fonction pure.

### Décision 2 — l'API publique du module est inchangée

Aucun composant ne change de comportement. Les imports sont redirigés, pas réécrits : `@/modules/vitrine/lib/consent` → `@/modules/vitrine/infrastructure/consent-storage` pour l'E/S, `@/modules/vitrine/domain/consent` pour les constantes et types purs. `SignupForm` importe le type depuis le domaine et la fonction depuis l'infrastructure.

### Décision 3 — la garde couvre désormais `vitrine`

`vitrine` passe `layered` dans `dev-hub/tools/web-layer-registry.txt`. La garde vérifie ses **10 fichiers de domaine** (38 au total sur les 3 modules `layered`).

Quatre cas négatifs ont été vérifiés en exécution réelle, chacun échoue :

| Cas injecté | Résultat |
|---|---|
| `import React from 'react'` dans `domain/currency.ts` | `package react interdit dans domain/` |
| `window.localStorage.getItem(...)` dans `domain/currency.ts` | `acces interdit dans domain/ : localStorage`, `window.` |
| `import { Foo } from '../components/Foo'` | `couche /components/ importee depuis domain/` |
| `vitrine` reclassé `flat` alors qu'il a 10 fichiers de domaine | `declare 'flat' mais 10 fichier(s) sous **/domain/ — reclasser en 'layered'` |

### Ce que cette révision ne change pas

- **Aucune règle métier n'est modifiée.** Le découpage déplace du code, il ne le réécrit pas : les 69 tests du domaine et de l'infrastructure passent sans qu'aucune assertion soit touchée, et les 252 tests de composants passent sans modification de comportement.
- `/business/*` (les pages) n'est pas refondu : seuls les fichiers de logique du module sont déplacés.
- Le front Super admin et les consoles du dashboard tenant restent hors périmètre.
- `toolbox`, `library`, `onboarding`, `marketing`, `assistant` restent `flat` : dette déclarée, pas résorbée.

---

## Alternatives écartées

**Étendre ADR-0034 par un paragraphe.** Écartée : ADR-0034 est une décision sur le **web** (elle remplace ADR-0030 et son périmètre est `front/web`). Y ajouter le mobile rendrait son périmètre flou et sa portée difficile à citer. Une ADR qui **étend** explicitement est plus lisible qu'une ADR qui grossit.

**Laisser l'ébauche en l'état.** Écartée : le registre n'était branché nulle part et `domain/` importait le framework. Ce n'était pas une architecture incomplète, c'était une architecture absente avec l'apparence d'une architecture présente — le pire des deux états.

**Dupliquer la garde web en TypeScript-like côté Dart.** Écartée : la garde web analyse des imports ES modules ; la garde Dart analyse des directives `import`/`export`. Le principe est commun, l'implémentation doit être native à chaque langage.

---

## Références

- `front/mobile_apps/leopardo_public/lib/features/surface/domain/surface_identity.dart`
- `front/mobile_apps/leopardo_public/lib/features/surface/infrastructure/surface_palette_tokens.dart`
- `front/mobile_apps/leopardo_public/lib/features/surface/infrastructure/surface_theme.dart`
- `front/mobile_apps/leopardo_public/test/architecture/layer_guard_test.dart`
- `front/mobile_apps/leopardo_public/test/surface_identity_test.dart`
- `front/web/src/modules/public-surfaces/__tests__/layer-guard.test.ts` (équivalent web)
- `dev-hub/tools/check-mobile-layer-guard.sh` (garde partagée, sans Flutter)
- `dev-hub/tools/mobile-layer-registry.txt` (état déclaré de chaque app)
- `dev-hub/tools/check-web-layer-guard.sh` (garde web, sans Node)
- `dev-hub/tools/web-layer-registry.txt` (état déclaré de chaque module web)
- `.github/workflows/web-layer-guard.yml` (exécution CI web)
- `front/web/src/modules/public-surfaces/copy/seat-labels.ts` (pont copie ↔ domaine)
- `.github/workflows/mobile-layer-guard.yml` (exécution CI)
- ADR-0034, ADR-0026
