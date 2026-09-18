# Plan technique — espace gérant Travel (épic #7642)

**Branch**: `bc/bc-24-espace-gerant-web` | **Spec**: ./spec.md

## Architecture

Calque du vertical modèle Restaurant (`front/web/src/app/(dashboard)/restaurant/`) :
un hub `/travel` + sous-pages, pages `'use client'` posées dans `ModulePageShell`
(`@/components/module-page-shell`), icônes lucide-react, Tailwind.

```
(dashboard)/travel/
  page.tsx        ← A1 hub : KPIs GET /travel/reports/dashboard + tuiles + lien portail
  network/        ← A2 CRUD stations/offices/routes/stops
  trips/          ← A3 voyages, tarifs, publish/cancel, manifeste
  bookings/       ← A4 réservations, billets, check-in
  reports/        ← A5 rapports + export async
  portal/         ← existant (voyageur) — NE PAS toucher
```

## Patterns imposés (relevés dans restaurant/)

- **API** : `apiFetch(path, init?)` de `@/lib/api-client` — chemins relatifs `'/travel/...'`
  (le préfixe `/api/v1` est porté par le proxy same-origin, auth via cookie httpOnly).
  Réponses enveloppées `{ data: T }` : `const payload = await res.json();
  (payload as { data?: T }).data`.
- **i18n** : `getPreferredLocale()` de `@/lib/i18n` + `t(locale, 'travel.xxx', fallbackFr)`
  de `@/lib/i18n/locale-catalog` ; catalogues sources `shared/i18n/locales/{fr,en,tr,ar}.json`
  (bloc plat `"travel": { "travel.a.b": … }`), miroir front régénéré par
  `node shared/i18n/sync/sync-web.js`.
- **Nav** : `front/web/src/lib/client-features.ts` — `CLIENT_MODULES` (gating
  `featureKeys: ['travelagency','travel_agency']`, `scope: 'business'`,
  `vertical: 'travel'`) + `ROUTE_TO_MODULE` pour la résolution route → module ;
  libellés localisés dans `front/web/src/lib/i18n.ts` (`dashboard.modules`, 4 locales).
- **Dashboard payload** (`TravelReportService::dashboard`) :
  `{ date, period_days, trip_id, sales_today, passengers, revenue_minor,
  confirmed_minor, cancellations, occupancy_rate (0..1), trips_count }`.

## Fichiers touchés (A1)

1. `front/web/src/app/(dashboard)/travel/page.tsx` — hub gérant (remplace le redirect).
2. `front/web/src/lib/client-features.ts` — `travel` → `/travel`, nouvelle clé
   `travel_portal` → `/travel/portal`, `ROUTE_TO_MODULE` pour network/trips/bookings/reports.
3. `front/web/src/lib/i18n.ts` — libellé nav `travel_portal` (4 locales).
4. `shared/i18n/locales/{fr,en,tr,ar}.json` + miroir `front/web/src/lib/i18n/locales/`
   (via sync) — clés `travel.home.*` et `travel.common.loading` manquantes.

## Vérification

`cd front/web && npm run lint && npx tsc --noEmit` avant chaque commit ; 1 commit par issue
(`feat(travel): … (Closes #N)`), CHANGELOG racine au dernier commit du lot ; pas de push.
