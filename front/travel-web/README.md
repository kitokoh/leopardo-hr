# Leopardo Travel — site public de réservation de billets (front/travel-web)

Site grand public **Next.js 16 (App Router, TypeScript, Tailwind v4)** de vente
de billets inter-agences (issue #7738, épic #7736). Il consomme UNIQUEMENT les
endpoints publics marketplace du backend (issue #7737) :

| Parcours | Endpoint backend |
|---|---|
| Autocomplete villes | `GET /api/v1/public/travel/marketplace/cities` |
| Recherche de trajets | `GET /api/v1/public/travel/marketplace/trips` |
| Détail trajet + sièges libres | `GET /api/v1/public/travel/marketplace/trips/{id}` |
| Réservation invité (idempotente) | `POST /api/v1/public/travel/marketplace/bookings` |
| Suivi par référence (+ code e-billet) | `GET /api/v1/public/travel/marketplace/bookings/{reference}?code=…` |
| Annulation en ligne | `POST /api/v1/public/travel/marketplace/bookings/{reference}/cancel` |
| E-billet PDF (URL signée) | `GET /api/v1/public/travel/marketplace/tickets/{ticket}/pdf?code=…` |
| Initiation paiement | `POST /api/v1/public/travel/marketplace/payments/initiate` |

## Démarrage

```bash
cd front/travel-web
npm ci
cp .env.local.example .env.local   # optionnel — les défauts pointent l'API dev
npm run dev
```

## Architecture

- **Proxy same-origin** : le navigateur n'appelle jamais le backend
  directement. Toutes les requêtes passent par
  `src/app/api/v1/[...path]/route.ts` qui relaie UNIQUEMENT les chemins
  `public/travel/*` (allowlist fail-closed) vers la cible résolue par
  `src/lib/backend-url.ts` (`API_PROXY_TARGET` > `BACKEND_API_URL` >
  `NEXT_PUBLIC_API_URL` > défaut Render dev). Aucun secret.
- **SSR/SEO** : accueil, résultats et détail trajet sont rendus côté serveur
  (metadata + OpenGraph) ; les pages transactionnelles (checkout,
  confirmation, suivi) sont client.
- **i18n fr/en** : catalogue maison `src/lib/i18n.ts` (fr par défaut),
  bascule persistée en cookie `travel_lang`, lue côté serveur par le layout
  (`<html lang>` correct dès le SSR — pas de mismatch d'hydratation).
- **Paiement** : le checkout livre une **réservation à payer à l'agence**
  (la réservation expire côté backend si elle n'est pas payée à temps).
  L'endpoint public `payments/initiate` existe mais les providers en ligne
  dépendent de la configuration de chaque agence — le paiement en ligne est
  le lot #7739/#7740.

## Qualité

```bash
npm run lint        # eslint src --max-warnings 0
npm run typecheck   # tsc --noEmit
npm run build       # next build
```

App autonome : son propre `package-lock.json`, aucun import hors de
`front/travel-web` (même règle que `front/web`, `turbopack.root` épinglé).
