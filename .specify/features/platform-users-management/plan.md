## Plan technique (2 volets, 1 PR)
**Backend** (`api/`):
- Nouveau contrôleur `PlatformUsersController` sous `/admin` (middleware `auth:super_admin_api`, `throttle:platform-sensitive`) :
  - `GET /admin/users` — pagination (per_page clampé), recherche `search` sur `first_name/last_name/email` (ILIKE), tri allowlisté (created_at, last_login_at, email), enrichi entreprise liée via `user_employee_links` + `public.companies` (PlatformCompanyLookup pattern), statut `is_active`, dernier login.
  - `GET /admin/users/{user}` — détail + entreprise + rôle.
  - `PATCH /admin/users/{user}` — `is_active` booléen ; 422 si l'id = utilisateur courant.
- Tests Feature (auth, pagination, search, self-disable, 404).
- openapi.yaml + matrice frontend/API + CHANGELOG.

**Admin** (`front/admin-dashboard/`):
- `UsersView.vue` : appel `GET /api/v1/admin/users` (pagination + recherche), table réelle, toggle statut via PATCH, états loading/empty/error. Supprimer le générateur mock.
- `UserDetailView.vue` : `GET /api/v1/admin/users/{id}` + fallback si 404 ; corriger le crash (ne plus dépendre de `dashboardStore.users`).
- `CreateUserModal`/`EditUserModal` : si hors contrat backend, les retirer de l'UI (aucun bouton mort) — décision : le périmètre v1 = liste + recherche + statut + détail.
- Vérifier `src/services/api.js` normalizeApiPath (`/v1/admin/users` → `/api/v1/admin/users`).
