# Client Login Readiness - Plan 18

Date : 2026-05-21

## Parcours valide

Le parcours web client attendu est :

1. Vitrine publique `front/web` -> CTA connexion client.
2. `/auth/login` -> `POST /api/v1/auth/login`.
3. Hydratation session -> `GET /api/v1/auth/me`.
4. Redirection role client -> `/dashboard`.
5. Dashboard manager -> `GET /api/v1/dashboard/summary` et `GET /api/v1/dashboard/recent-activity?limit=5`.

Les roles tenant (`manager`, `employee`, `rh`, `comptable`) restent dans l'espace client. Le role `super_admin` est redirige vers `NEXT_PUBLIC_ADMIN_URL` si la variable existe, sinon vers `/dashboard` en fallback pour eviter un ecran mort.

## Variables requises

| Variable | Surface | Exemple | Obligatoire |
| --- | --- | --- | --- |
| `NEXT_PUBLIC_API_URL` | Vitrine / portail client | `https://gestionemployerbackend.onrender.com/api/v1` | Oui |
| `NEXT_PUBLIC_ADMIN_URL` | Redirection super admin | `https://leo-admin.pages.dev` | Recommande |
| `BASE_URL` | Playwright preview/staging | URL preview Vercel | CI preview |
| `PLAYWRIGHT_WEB_SERVER_COMMAND` | Playwright local | `npm run dev` | Local seulement |

## Garde anti-regression

Le smoke `front/web/e2e/auth-client-smoke.spec.ts` couvre maintenant :

- connexion manager/RH valide jusqu'au dashboard tenant ;
- affichage/masquage du mot de passe ;
- mauvais identifiants avec message API lisible et maintien sur `/auth/login` ;
- session expiree sur le dashboard avec purge du token local et retour login.

## Points restants Plan 18

- Ajouter la recuperation mot de passe reelle cote backend/web quand le flux email sera pret.
- Brancher le tracking produit `login_success`, `login_failed`, `dashboard_loaded`.
- Ajouter les verrous UI par feature/plan dans le dashboard client.
