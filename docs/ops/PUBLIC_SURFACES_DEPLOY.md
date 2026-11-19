# Déploiement des vitrines publiques verticales

Issue #9591. Ce document décrit le déploiement **conditionnel par vitrine** des
six surfaces grand public, et la procédure d'ajout d'une septième.

## Principe

Un front n'est redéployé que si du code qui le concerne a changé — même règle
que la Vitrine, l'API et les apps mobiles. Pour les vitrines verticales, la
sélection est faite par `dev-hub/tools/detect-changed-surfaces.sh`.

| Ce qui change | Vitrines déployées |
|---|---|
| `modules/public-surfaces/pharmacy/**` | pharmacie seule |
| `app/pharmacies/**` | pharmacie seule |
| `modules/public-surfaces/kernel/**` | **les six** (noyau partagé) |
| `modules/public-surfaces/copy/**` | **les six** (catalogue de copie) |
| `api/**`, `front/admin-dashboard/**` | aucune |

Le noyau et la copie sont partagés : les modifier change le rendu de chaque
surface, donc les six partent. Le nier produirait des vitrines désynchronisées.

## Topologie

Un **projet Vercel par vitrine**, `rootDirectory: front/web`. Un projet partagé
servirait six domaines depuis un même build : impossible de déployer la
pharmacie sans republier le restaurant, et impossible d'avoir un cache ou un
rollback indépendant.

| Vitrine | Domaine canonique | Route |
|---|---|---|
| Restaurant | `restaurants.leopardo.app` | `/restaurants` |
| Retail | `marche.leopardo.app` | `/boutiques` |
| Hôtellerie | `sejours.leopardo.app` | `/stay` |
| B2B | `entreprises.leopardo.app` | `/entreprises` |
| Billets | `billets.leopardo.app` | `/billets` |
| Pharmacie | `pharmacies.leopardo.app` | `/pharmacies` |

Les domaines sont surchargeables par `NEXT_PUBLIC_SURFACE_DOMAIN_<VERTICAL>` et
déclarés dans `docs/ops/DOMAINS.md` (garde `check-canonical-domains.sh`).

## Secrets à provisionner (action propriétaire, une fois par vitrine)

Pour chaque verticale, trois secrets de dépôt :

```
VERCEL_SURFACE_RESTAURANT_TOKEN / _ORG_ID / _PROJECT_ID
VERCEL_SURFACE_RETAIL_TOKEN     / _ORG_ID / _PROJECT_ID
VERCEL_SURFACE_HOSPITALITY_TOKEN/ _ORG_ID / _PROJECT_ID
VERCEL_SURFACE_TRADE_TOKEN      / _ORG_ID / _PROJECT_ID
VERCEL_SURFACE_TRAVEL_TOKEN     / _ORG_ID / _PROJECT_ID
VERCEL_SURFACE_PHARMACY_TOKEN   / _ORG_ID / _PROJECT_ID
```

Comportement en l'absence de secrets :

- **PR / dispatch** : `::warning::` + entrée dans le step summary, job vert.
- **push sur `main`** : **échec explicite**. Un push qui touche une vitrine sans
  déploiement possible doit être visible, pas silencieux (#7994 §4).

## Verticales sans projet Vercel dédié (#9638)

Toutes les verticales n'ont pas leur propre projet Vercel. Une verticale peut
être **servie par `front/web` lui-même** : même build, même projet, même
déploiement. C'est le cas d'**Emploi** (`careers`) : `/emploi` est une route de
`front/web`, pas un déploiement séparé.

Ces verticales sont déclarées dans `NO_VERCEL_SURFACES` de
`dev-hub/tools/detect-changed-surfaces.sh`. Elles restent dans `SURFACES` — la
garde de parité et la détection les connaissent, un diff qui les touche est bien
signalé — mais elles sont retirées de `deployable`, la liste que le workflow
transforme en matrice.

Conséquence : un push sur `main` qui ne touche que `careers` ne lance **aucun**
job de déploiement et n'échoue pas faute de secrets. Un push qui touche le noyau
partagé déploie les sept verticales qui ont un projet Vercel, et ignore
`careers`.

Ajouter une verticale à cette liste est un acte explicite : une verticale
oubliée ferait échouer `push main` faute de secrets, ce qui est visible — c'est
le comportement voulu par #7994 §4.

## Ajouter une septième vitrine

1. Créer `front/web/src/modules/public-surfaces/<vertical>/` (domain,
   application, infrastructure, identity, components) et l'ajouter à
   `SURFACE_VERTICALS`.
2. Créer la route `front/web/src/app/<basePath>/page.tsx`.
3. Ajouter la ligne `$'<vertical>\t<basePath>'` dans la table `SURFACES` de
   `detect-changed-surfaces.sh`.
4. Ajouter le domaine dans `docs/ops/DOMAINS.md`.
5. Ajouter les `paths:` du workflow `public-surfaces-deploy.yml` et les trois
   secrets Vercel.

**Oublier l'étape 3 fait échouer la CI** (`check-public-surfaces-deploy-parity.sh`,
job requis) : sans elle, la nouvelle vitrine ne se déploierait jamais —
silencieusement, et seulement en production.

## Vérifier localement

```bash
# Quelles vitrines un diff touche-t-il ?
git diff --name-only origin/main...HEAD | bash dev-hub/tools/detect-changed-surfaces.sh --stdin

# Parité vitrines ↔ déploiement
bash dev-hub/tools/check-public-surfaces-deploy-parity.sh
```

## Publication côté tenant

Une vitrine n'affiche que ce que le tenant a explicitement publié :

| Verticale | Opt-in |
|---|---|
| Restaurant | `is_published_online` sur les produits |
| Retail | `retail_online_settings.enabled` |
| Hôtellerie | opt-in du profil d'hébergement |
| B2B | `trade_company_profiles.is_public` |
| Billets | trajets publiés au marketplace |
| Pharmacie | `pharmacy_public_profiles.enabled` (défaut `false`) |

Tous sont **fail-closed** : sans opt-in, l'entité n'apparaît nulle part côté
public. Un profil à moitié rempli n'est jamais publié.
