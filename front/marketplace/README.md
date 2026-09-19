# Leopardo Marché — web client grand public

Vitrine marchande multi-vendeurs de Leopardo (BC-17 RETAIL, phase 2 — issue #7809).
Le grand public y découvre les produits publiés en ligne par les boutiques Leopardo,
constitue un panier, commande en invité (paiement à la livraison) et suit sa commande
avec une référence + un jeton de suivi.

Application **Next.js (App Router) indépendante de `front/web`** :

- aucune authentification, aucun cookie de session, aucun proxy ;
- appels directs à l'API publique `/api/v1/public/market/*` ;
- français uniquement (v1), mobile-first, Tailwind CSS 4.

## Configuration

| Variable | Défaut | Rôle |
|---|---|---|
| `NEXT_PUBLIC_MARKET_API_BASE` | `https://gestionemployerbackend.onrender.com` | Base de l'API (sans `/api/v1`) |

## Développement

```bash
npm install
npm run dev      # http://localhost:3000
npm run lint     # ESLint, zéro warning toléré
npm run build    # build de production
npm run start    # sert le build
```

## Pages

- `/` — accueil (héros + nouveautés + boutiques à découvrir) ;
- `/produits` — recherche, filtres prix/boutique, tri, pagination ;
- `/produits/[id]` — fiche produit, ajout au panier ;
- `/boutiques`, `/boutiques/[slug]` — annuaire et vitrines des boutiques ;
- `/panier` — panier localStorage (`leopardo_marche_cart`), groupé par boutique ;
- `/commande` — checkout invité : 1 commande créée **par boutique**, clé
  d'idempotence `crypto.randomUUID()` conservée pour re-soumission sûre ;
- `/confirmation` — références + jetons de suivi (sauvegardés en localStorage) ;
- `/suivi` — suivi public (`?ref=&token=`), timeline
  `pending → confirmed → ready → shipped → delivered` (+ `cancelled`).

## Déploiement

Projet Vercel dédié, racine `front/marketplace` (voir `vercel.json` : framework
`nextjs`, headers de sécurité, déploiement limité aux branches `main`/`staging`
et aux commits touchant ce dossier). Définir `NEXT_PUBLIC_MARKET_API_BASE`
dans l'environnement Vercel si la base API diffère du défaut.
