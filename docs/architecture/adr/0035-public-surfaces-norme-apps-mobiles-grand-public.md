# ADR 0035 — La doctrine `public-surfaces` s'applique aussi aux apps mobiles grand public

## Statut

**Acceptée** — 2026-11-19

**Étend** : ADR-0034 (`public-surfaces` est la norme du front grand public), qui ne couvrait que le web.

**Décideurs** : owner (validation) — rédigée par l'agent Dev
**Liens** : #9640 (cette décision) · #9641 (garde de couche) · #9642 (registre fail-closed) · ADR-0034 (doctrine web) · ADR-0026 (ModuleRegistry, même principe de registre versionné)

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

- Elle ne dit rien des apps professionnelles, qui continuent d'utiliser `TenantTheme`.
- Elle ne tranche pas la migration des features non découpées (voir ci-dessus).
- Elle ne crée pas d'espace candidat côté mobile : le contrat API n'en a pas.

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
- ADR-0034, ADR-0026
