# ADR BC-41-001 — Multi-brand et surfaces publiques spécialisées par verticale

- **Statut** : accepté
- **Date** : 2026-11-17
- **Périmètre** : `api/app/Modules/Branding/**`, `api/routes/modules/branding*.php`,
  `api/database/migrations/tenant/*_brands_*`, `front/web/src/modules/public-surface/**`,
  `front/web/src/app/s/**`, `front/mobile_apps/leopardo_public/lib/features/brand/**`
- **Hors périmètre (intact)** : `api/app/Modules/Showcase/**` (BC-27 Vitrine),
  `api/app/Modules/Platform/**` (super admin), `front/zkteco-kiosk/**`,
  `front/web-offline/**`, `api/app/Modules/EdgeSync/**`
- **Branche** : `feat/multibrand-public-surfaces`
- **Documentation** : `docs/architecture/MULTIBRAND_PUBLIC_SURFACES.md`

## Contexte

L'audit DDD des fronts web (2026-11-17) a établi deux constats qui motivent cette décision.

1. **Le mot « marque » n'existe pas dans le modèle.** Le branding est un JSON libre
   dans `companies.metadata.branding`. Conséquence directe : **une entreprise ne peut
   avoir qu'une seule marque**. Un groupe qui exploite deux enseignes (un restaurant
   et une pharmacie, ou deux restaurants de positionnements différents) doit créer
   deux tenants — donc deux jeux de données, deux facturations, deux équipes.
2. **Le branding est logé dans le module HR** (`Modules/HR/.../CompanyBrandingController.php`).
   C'est un concept de *tenant*, pas de RH. Une verticale Restaurant dépend du module
   RH pour son identité visuelle.

Côté surface publique, le socle existe déjà et il est bon :

- **BC PAGES** (`Modules/Pages`) porte l'agrégat `Page`, la vérification de propriété,
  les domaines personnalisés (TXT + CNAME + certificat, fail-closed) et un rendu SSR
  public isolé.
- **Le port `PageBlockProvider`** inverse déjà la dépendance : chaque vertical fournit
  ses blocs (`restaurant.menu`, `retail.catalog`) sans que Pages importe son code.
- **`PublicPagePresenter`** applique une liste blanche stricte et calcule les actions
  à partir de l'état réel du tenant.

Ce qui manque n'est donc pas le rendu : c'est **l'identité**. Une `Page` appartient à
un `Site` (un établissement), et un `Site` appartient à un tenant. Il n'existe aucun
niveau intermédiaire qui dise « cette enseigne, avec cette identité, sur ce domaine,
pour cette verticale ».

## Décision

### 1. Un nouveau bounded context : **BC-41 BRANDING**

`Brand` est un **agrégat racine** appartenant au tenant, et **multi-instances** :

```
Company (tenant)
  └── Brand (1..n)            ← NOUVEAU : l'enseigne, son identité, son domaine
        ├── palette           ← couleurs + contraste WCAG calculé serveur
        ├── identity          ← nom public, baseline, logo, favicon, OG
        ├── surface           ← host personnalisé, préfixe de chemin, locales
        └── vertical          ← restaurant | retail | pharmacy | travel | …
              └── Site (0..n) ← les établissements de cette enseigne
                    └── Page  ← la présence publique d'un établissement (BC PAGES)
```

`Brand` **ne remplace pas** `Page` : il la **qualifie**. Une Page reste la surface
d'un établissement ; la Brand est l'enseigne qui porte l'identité, le domaine et le
profil de verticale. Une Page sans Brand explicite hérite de la Brand par défaut du
tenant — c'est ce qui rend la migration non destructive.

### 2. `Brand` est un agrégat, pas un sac de champs

Les invariants sont portés par le domaine, pas par le contrôleur :

| Invariant | Pourquoi |
|---|---|
| **Une seule Brand par défaut par tenant** | index partiel unique en base ; deux défauts rendraient la résolution ambiguë |
| **Slug unique par tenant, normalisé, non réservé** | le slug est une URL publique ; deux Brands sur le même slug = collision d'identité |
| **`on_primary` calculé serveur (WCAG 2.1)** | un tenant qui choisit un vert clair ne doit pas obtenir un libellé blanc illisible |
| **Publier exige `identity.display_name`** | une surface publique sans nom d'enseigne n'est pas une surface |
| **Suspendre exige un motif** | une suspension sans motif n'est pas auditable |
| **`vertical` est un registre fermé** | une verticale inconnue n'a pas de profil de surface, donc pas de rendu |

### 3. Un port `SurfaceProfileProvider` — le site spécialisé vient de la verticale

Chaque verticale déclare **son** profil de surface publique via un port, exactement
comme elle déclare ses blocs pour Pages :

```php
interface SurfaceProfileProvider
{
    public function vertical(): string;              // 'restaurant'
    public function profile(): SurfaceProfile;       // capacités, blocs, thème, parcours
}
```

Enregistrement par tag de conteneur `branding.surface_profiles`. `Branding` ne connaît
**jamais** la classe concrète d'une verticale : si la verticale n'est pas installée,
le tag est vide et le registre ne contient que le profil `generic`.

C'est ce qui répond à « chaque verticale doit avoir un site web spécialisé destiné au
grand public » : le profil déclare les **capacités** (commander, réserver, prendre
rendez-vous, demander un devis, délivrer une ordonnance) et les **blocs** attendus.
Le front web rend le profil, il ne code pas une page par verticale.

### 4. Le front web consomme un contrat, pas une implémentation

`front/web/src/modules/public-surface/` est structuré en couches :

```
domain/          entités, value objects, règles pures (aucun import réseau)
application/     cas d'usage (resolveSurface, buildTheme, selectBlocks)
infrastructure/  client HTTP vers /api/public/brands/{slug}
components/      rendu React (reçoit un DTO, ne fetch jamais)
```

La règle est vérifiable : **aucun fichier de `components/` n'importe
`infrastructure/`**. C'est la correction du défaut principal relevé par l'audit
(148 fichiers de route qui font leur propre I/O).

### 5. Mobile : même logique, sans casser l'existant

`leopardo_public` reçoit une feature `brand` en trois couches. Les applications
verticales existantes (`leopardo_pos_restaurant`, `leopardo_travel_agent`, …) ne sont
**pas** modifiées : elles continuent de fonctionner avec leur thème propre. La feature
`brand` est **additive** et optionnelle — une app qui ne la branche pas garde son
comportement actuel.

## Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| **Étendre `company_showcases`** (BC-27) | sa clé d'unicité est `company_id` : une vitrine par tenant. Casser cette contrainte en production sur des données vivantes, pour un modèle dont les états diffèrent (`verified`/`suspended` n'existent pas côté Showcase), est un risque sans contrepartie. Et Vitrine est hors périmètre. |
| **Ajouter `brand_id` à `sites` seulement** | ne résout pas l'identité : deux enseignes partageant un établissement resteraient impossibles, et le domaine resterait porté par la Page. |
| **Un front par verticale** (`front/restaurant-web`, `front/pharmacy-web`, …) | duplication massive : 12 verticales × i18n × SEO × consentement × accessibilité. Le profil de surface donne la spécialisation sans dupliquer le socle. |
| **Garder le branding dans HR** | un tenant Retail dépendrait du module RH pour son identité visuelle. La dépendance est inversée : c'est HR qui devrait dépendre de Branding, pas l'inverse. |

## Conséquences

**Positives**

- Un tenant peut exploiter plusieurs enseignes, chacune avec son domaine, son identité
  et son profil de verticale.
- Ajouter une verticale = un `SurfaceProfileProvider` + ses blocs. Aucune modification
  de Branding ni du front.
- Le branding sort de HR : la dépendance redevient unidirectionnelle.
- Le contraste WCAG est calculé une fois, côté serveur, pour toutes les surfaces.

**Négatives / coûts assumés**

- Une migration additive (`brands`) et un backfill : chaque tenant existant reçoit une
  Brand par défaut dérivée de `companies.metadata.branding`. Le backfill est idempotent
  et ne supprime rien.
- Deux notions coexistent temporairement (`companies.metadata.branding` et `brands`).
  La double lecture est explicite et documentée ; la suppression de l'ancienne clé est
  un lot ultérieur, jamais le premier.
- `Page` gagne une relation optionnelle vers `Brand`. Une Page orpheline de Brand reste
  valide (repli sur la Brand par défaut du tenant).

## Références

- Audit DDD des fronts web, 2026-11-17
- `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` (BC-41)
- `docs/architecture/MULTIBRAND_PUBLIC_SURFACES.md` (spécification complète)
- `api/app/Modules/Pages/Domain/Blocks/PageBlockProvider.php` (le port dont celui-ci
  reprend le patron)
