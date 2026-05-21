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

## Acces features Plan 18.2

Le portail client applique les modules visibles depuis trois sources, dans cet ordre :

1. `auth/me.data.capabilities`
2. `auth/me.data.company.features`
3. `auth/me.data.plan.features`

En absence de contrat explicite, le module reste disponible afin de ne pas couper les clients historiques. Une valeur `false`, `disabled`, `locked` ou equivalente bloque le module ; une valeur `trial` laisse le module utilisable avec un badge Trial.

Modules controles cote UI :

| Module | Route | Cles reconnues |
| --- | --- | --- |
| Employes | `/employees` | `employees`, `employee_management`, `can_view_employees`, `can_create_employees` |
| Pointages | `/attendance` | `attendance`, `time_tracking`, `can_view_attendance` |
| Absences | `/absences` | `absences`, `leave_management`, `can_view_absences` |
| Contrats | `/contracts` | `contracts`, `can_view_contracts` |
| Paie | `/payroll` | `payroll`, `pay_slips`, `can_view_payroll`, `can_manage_payroll` |
| Formation | `/training` | `training`, `can_view_training` |
| Rapports | `/reports` | `reports`, `analytics`, `can_view_reports` |
| Facturation | navigation plan | `billing`, `can_manage_billing` |
| Integrations | navigation plan | `integrations`, `api_access`, `webhooks`, `can_manage_integrations` |

Les modules non inclus affichent une page d upgrade explicite au lieu de rendre la page metier ou de produire une 404.

## Points restants Plan 18

- Ajouter la recuperation mot de passe reelle cote backend/web quand le flux email sera pret.
- Persister le tracking produit cote backend/data warehouse quand l endpoint analytics sera stabilise ; le tracking navigateur local est deja couvert dans `CLIENT_UX_OBSERVABILITY.md`.
- Completer les gates backend si un endpoint critique n applique pas encore les feature flags serveur.
- Brancher l onboarding incomplet et les notifications temps reel lorsque les endpoints dedies seront stabilises.
