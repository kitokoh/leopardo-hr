# Architecture multi-brand des surfaces publiques

> **BC-41 BRANDING** — spécification de référence.
> Décision : [`docs/adr/BC-41-001-multibrand-public-surfaces.md`](../adr/BC-41-001-multibrand-public-surfaces.md)
> Registre : [`dev-hub/governance/bounded-context-registry.json`](../../dev-hub/governance/bounded-context-registry.json)

## 1. Le besoin, en une phrase

Un tenant doit pouvoir exploiter **plusieurs enseignes**, chacune avec **son identité**,
**son domaine** et **un site public spécialisé pour sa verticale** — sans dupliquer le
socle de rendu, sans casser la vitrine existante, et sans qu'une verticale doive
modifier le code des autres.

## 2. Ce qui existe déjà (et qu'on ne refait pas)

| Brique | Module | Rôle | Statut |
|---|---|---|---|
| `Page` | BC PAGES | présence publique d'un **établissement**, publication ≠ vérification | réutilisé tel quel |
| `PageCustomDomain` | BC PAGES | domaine perso : TXT → CNAME → certificat, fail-closed | réutilisé tel quel |
| `PageBlockProvider` | BC PAGES | port : chaque verticale fournit ses blocs | **patron repris** |
| `PublicPagePresenter` | BC PAGES | DTO public en liste blanche, actions calculées | réutilisé tel quel |
| `Showcase` | BC-27 | vitrine 1-clic du tenant | **hors périmètre, intact** |

Le manque n'était pas le rendu : c'était **l'identité**. Il n'existait aucun niveau
disant « cette enseigne, avec cette identité, sur ce domaine, pour cette verticale ».

## 3. Le modèle

```
Company (tenant)
  └── Brand (1..n)                     BC-41 — l'enseigne
        ├── slug, name, vertical
        ├── palette   { primary, accent, surface, on_primary(calculé) }
        ├── identity  { display_name, tagline, logo_url, favicon_url, og_image_url }
        ├── surface   { host, path_prefix, locales[], default_locale }
        └── status    draft | published | suspended
              │
              ├── Site (0..n)          BC-02 — les établissements de l'enseigne
              │     └── Page (0..1)    BC PAGES — la surface d'un établissement
              │
              └── SurfaceProfile       BC-41 — fourni par la VERTICALE
                    ├── capabilities   commander | réserver | RDV | devis | ordonnance
                    ├── blockTypes     restaurant.menu, retail.catalog, …
                    └── theme          tokens de rendu par défaut
```

### 3.1 Pourquoi `Brand` et pas `Site`

`Site` est un **établissement d'exploitation** : une adresse, des horaires, un GPS.
Deux enseignes peuvent partager un établissement (un corner pharmacie dans une
épicerie), et une enseigne peut avoir plusieurs établissements. Ce sont deux axes
orthogonaux — les confondre interdirait les deux cas.

### 3.2 Pourquoi `Brand` et pas `Page`

`Page` est la surface **d'un établissement** : elle porte le contenu, la vérification
de propriété, la modération. `Brand` est l'**enseigne** : elle porte l'identité, le
domaine, le profil de verticale. Une Page sans Brand explicite hérite de la Brand par
défaut du tenant — c'est ce qui rend la migration non destructive.

## 4. Invariants du domaine

Portés par l'agrégat, pas par le contrôleur. Chacun est couvert par un test.

| # | Invariant | Mécanisme |
|---|---|---|
| I1 | Une seule Brand `is_default` par tenant | index partiel unique `WHERE is_default` |
| I2 | Slug unique par tenant, normalisé, non réservé | `Brand::normalizeSlug()` + unique `(company_id, slug)` |
| I3 | `on_primary` calculé serveur, contraste ≥ 4.5:1 | `BrandPalette::withContrastingForeground()` |
| I4 | Publier exige `identity.display_name` | `Brand::publish()` lève `BrandPublishException` |
| I5 | Suspendre exige un motif d'allowlist | `Brand::suspend()` + `SUSPENSION_REASONS` |
| I6 | `vertical` ∈ registre fermé | enum `BrandVertical` |
| I7 | Un host ne peut appartenir qu'à une Brand | unique `host` (partiel, hors NULL) |
| I8 | Une Brand suspendue ne sert pas | `Brand::isPubliclyVisible()` |

## 5. Le port `SurfaceProfileProvider`

C'est la pièce qui donne à chaque verticale **son** site spécialisé sans dupliquer le
socle. Le patron est identique à `PageBlockProvider` (BC PAGES) : inversion de
dépendance par tag de conteneur.

```php
interface SurfaceProfileProvider
{
    /** La verticale propriétaire (doit correspondre à un cas de BrandVertical). */
    public function vertical(): string;

    /** Le profil de surface : capacités, blocs attendus, thème par défaut. */
    public function profile(): SurfaceProfile;
}
```

Enregistrement, dans le service provider **de la verticale** :

```php
$this->app->tag([RestaurantSurfaceProfile::class], 'branding.surface_profiles');
```

`Branding` résout le tag et construit un `SurfaceProfileRegistry` figé. Si la verticale
n'est pas installée, le tag est vide : le registre ne contient que `generic`, et aucune
`class_exists` n'est appelée.

### 5.1 Ce que déclare un profil

| Champ | Rôle | Exemple (restaurant) |
|---|---|---|
| `capabilities` | ce que le visiteur peut FAIRE | `order`, `reserve`, `directions` |
| `blockTypes` | les blocs attendus sur la surface | `core.hero`, `restaurant.menu`, `core.contact` |
| `theme` | tokens de rendu par défaut | `radius: 0.75rem`, `density: comfortable` |
| `primaryCta` | l'action mise en avant | `order` |

Le front web **rend le profil**, il ne code pas une page par verticale. Ajouter une
verticale = un provider + ses blocs. Zéro modification de Branding, zéro modification
du front.

## 6. Couches — API

```
api/app/Modules/Branding/
├── Domain/
│   ├── Enums/            BrandStatus, BrandVertical
│   ├── Exceptions/       BrandPublishException, BrandSlugException, …
│   ├── Models/           Brand
│   ├── Palette/          BrandPalette (value object, contraste WCAG)
│   ├── Surface/          SurfaceProfile, SurfaceProfileProvider, SurfaceProfileRegistry
│   └── Services/         BrandResolver (host/slug → Brand servie)
├── Application/
│   └── Actions/          CreateBrand, UpdateBrand, PublishBrand, SuspendBrand, …
├── Infrastructure/
│   └── Services/         BrandBackfillService (companies.metadata.branding → brands)
├── Interfaces/Api/V1/
│   ├── Controllers/      BrandController (tenant), PublicBrandController (public)
│   ├── Requests/         StoreBrandRequest, UpdateBrandRequest
│   └── Resources/        BrandResource, PublicBrandResource
└── Providers/            BrandingServiceProvider
```

**Règle de dépendance** : `Domain` n'importe rien de `Application`, `Infrastructure`
ou `Interfaces`. `Interfaces` ne contient aucune règle métier.

## 7. Couches — front web

```
front/web/src/modules/public-surface/
├── domain/
│   ├── brand.ts              Brand, BrandPalette, BrandIdentity (types + gardes)
│   ├── surface-profile.ts    SurfaceProfile, SurfaceCapability
│   └── theme.ts              buildThemeTokens() — fonction PURE, testable sans réseau
├── application/
│   ├── resolve-surface.ts    cas d'usage : slug → SurfaceView (ou null)
│   └── select-blocks.ts      filtre les blocs rendables selon le profil
├── infrastructure/
│   └── brand-api.ts          client HTTP vers /api/public/brands/{slug}
└── components/
    ├── PublicSurfaceShell.tsx    applique le thème, rend le chrome
    ├── SurfaceHero.tsx
    ├── SurfaceCapabilityBar.tsx  les CTA du profil
    └── SurfaceBlockRenderer.tsx  dispatch par type de bloc
```

**Règle vérifiée** : aucun fichier de `components/` n'importe `infrastructure/`.
Les composants reçoivent un DTO déjà résolu. C'est la correction du défaut principal
relevé par l'audit (148 fichiers de route qui font leur propre I/O).

## 8. Mobile

`leopardo_public/lib/features/brand/` en trois couches, **additif** :

```
features/brand/
├── domain/          Brand, BrandPalette, BrandTheme (purs)
├── application/     BrandController (ChangeNotifier), resolveBrand()
└── infrastructure/  BrandApiClient
```

Les applications verticales existantes (`leopardo_pos_restaurant`,
`leopardo_travel_agent`, `leopardo_pos_retail`, …) **ne sont pas modifiées**. Elles
gardent leur thème propre. Une app qui veut la marque dynamique branche la feature ;
une app qui ne la branche pas garde son comportement actuel — c'est la garantie de
non-régression.

## 9. Résolution d'une surface publique

Ordre strict, et **404 uniforme** en cas d'échec (jamais 403 : un 403 confirmerait
l'existence de la ressource et permettrait d'énumérer le parc client).

```
GET /api/public/brands/{slug}
  1. normaliser le slug            → invalide ⇒ 404
  2. Brand par slug (tous tenants) → absente ⇒ 404
  3. tenant accessible ?           → suspendu/expiré ⇒ 404
  4. Brand publiée et non suspendue→ sinon ⇒ 404
  5. profil de verticale           → absent ⇒ profil `generic`
  6. construire le DTO public      → liste blanche, jamais toArray()
```

Par host (`GET /api/public/brands/by-host`) : même chaîne, le host remplace le slug.

## 10. Migration et compatibilité

- **Additive** : nouvelle table `brands`, aucune colonne retirée, aucune contrainte
  cassée sur `company_showcases` ni sur `pages`.
- **Backfill idempotent** : chaque tenant reçoit une Brand par défaut dérivée de
  `companies.metadata.branding` (nom, couleurs, logo). Rejouable sans doublon.
- **Double lecture assumée** : `companies.metadata.branding` reste lu tant que le
  backfill n'est pas généralisé. La suppression de l'ancienne clé est un lot ultérieur.
- **`down()`** ne détruit que ce que `up()` a créé.

## 11. Ce qui est explicitement hors périmètre

| Zone | Raison |
|---|---|
| `Modules/Showcase` (BC-27 Vitrine) | mission : laisser intact |
| `Modules/Platform` (super admin) | mission : laisser intact |
| `front/zkteco-kiosk` | mission : laisser intact |
| `front/web-offline`, `Modules/EdgeSync` | mission : laisser intact |
| Applications mobiles verticales existantes | non-régression : on ajoute, on ne modifie pas |

## 12. Critères d'acceptation de la mission

1. Un tenant peut créer **plusieurs** Brands, avec slug, identité, palette et verticale.
2. Une seule Brand par défaut par tenant — garanti par la base, pas seulement par le code.
3. `on_primary` est calculé serveur et respecte WCAG AA (≥ 4.5:1).
4. `GET /api/public/brands/{slug}` renvoie un DTO en liste blanche, avec le profil de
   verticale résolu.
5. Une Brand non publiée ou suspendue renvoie 404, jamais son contenu.
6. Le front web rend une surface à partir du profil, sans I/O dans les composants.
7. Le mobile expose la feature `brand` sans modifier les apps verticales existantes.
8. BC-41 est déclaré dans le registre, avec CODEOWNERS et garde CI verte.
9. `main` reste propre après merge : aucun fichier hors périmètre modifié.
