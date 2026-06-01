# Rapport Plan 69.4 - Smoke API platform admin

Date : 2026-06-01  
Backend : `https://gestionemployerbackend.onrender.com/api/v1`  
Compte : `admin@leopardo-rh.com`  
Reference main validee : `ee6bd5db`

## Verdict

**Go apres corrections #682/#683/#684.** Le compte super-admin demo expose par `/api/v1/demo-users` est de nouveau utilisable, et le parcours platform admin critique fonctionne sur Render.

## Probleme detecte

- `/api/v1/demo-users` annonçait `admin@leopardo-rh.com / password123`.
- `POST /platform/auth/login` retournait `401 INVALID_CREDENTIALS`.
- Apres correction credentials, la creation entreprise fonctionnait, mais l'ouverture immediate de la fiche client (`health`, `subscription`, `features`) retournait `404`, puis `500`, a cause du `search_path` PostgreSQL et du chargement non qualifie de `Company`.

## Corrections livrees

- `DemoCompanyOnceSeeder` resynchronise le mot de passe demo `password123` et retire le 2FA demo si necessaire.
- Ajout de `PlatformCompanyLookup` pour charger les societes plateforme depuis `public.companies`.
- Les endpoints detail platform admin utilisent le lookup qualifie :
  - `GET /platform/companies/{company}/health`
  - `GET /platform/companies/{company}/subscription`
  - `GET /platform/companies/{company}/features`

## Preuve Render finale

- `POST /platform/auth/login` : OK, role `super_admin`.
- `GET /platform/auth/me` : OK.
- `GET /platform/companies?per_page=20` : OK.
- `GET /platform/companies/health?limit=5` : OK.
- `GET /platform/plans` : OK.
- `POST /platform/companies` : OK.
- `GET /platform/companies/{id}/health` apres creation : OK.
- `GET /platform/companies/{id}/subscription` apres creation : OK, plan `Starter`, statut `trial`.
- `GET /platform/companies/{id}/features` apres creation : OK, `rh=true`.

Entreprise de smoke creee :

- `Plan69 Smoke 20260601180054`
- `a1eb7235-b00c-47d0-b4aa-9c36de886663`

Note : il n'existe pas encore d'endpoint public de suppression entreprise platform ; les societes `Plan69 Smoke ...` restent donc visibles comme traces QA.
