# RUNBOOK BETA ENV SETUP
# Version 1.1 | 2026-05-02

But: preparer un environnement Beta exploitable avant la recette humaine.
Ce runbook est aligne sur l'infrastructure **Render (App) + Neon (PostgreSQL)**.

## Checklist infrastructure (Render / Neon)

- [x] Web Service cree sur Render (FrankenPHP)
- [x] Base de donnees PostgreSQL creee sur Neon.tech
- [x] `DATABASE_URL` (ou `DB_URL`) renseignee dans les secrets Render
- [x] TLS actif (automatique par Render sur le domaine .onrender.com ou custom)
- [x] Healthcheck configure sur `/api/v1/health` (doit chercher `"status":"ok"`)

## Checklist application (Automatique via Docker/Entrypoint)

Les etapes suivantes sont normalement gerees par `api/docker-entrypoint.sh` au startup :
- Optimisation Laravel (`config:cache`, `route:cache`, `view:cache`)
- Bootstrap des tables de migration (`public.migrations`, `shared_tenants.migrations`)
- Migrations automatiques (`migrate --force --isolated`)
- Seeders de base et demo (si `DEMO_SEED_ONCE=true`)

## Variables d'environnement MVP attendues sur Render

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=pgsql`
- `CACHE_STORE=file`
- `SESSION_DRIVER=file`
- `QUEUE_CONNECTION=sync`
- `FILESYSTEM_DISK=local`
- `TENANCY_DEFAULT_TYPE=shared`
- `RUN_MIGRATIONS=true` (pour activer les migrations au boot)

## Donnees minimales de recette

- 1 company active
- 1 manager actif
- 1 employee actif
- 1 ou 2 logs de pointage existants pour verifier dashboard, historique et PDF

## Verification express

- `GET /api/v1/health` -> 200 (doit retourner `{"status":"ok", ...}`)
- `GET /login` -> 200
- login manager -> redirect `/dashboard`
- fiche employe accessible
- quick estimate OK
- PDF OK

## Blocage immediat

Stop Beta si :
- login impossible
- erreur 500 sur dashboard
- PDF KO
- fuite tenant
- RBAC manager/employee incoherent
