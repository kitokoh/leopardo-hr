# RUNBOOK BETA ENV SETUP
# Version 1.1 | 2026-05-03

But: preparer un environnement Beta exploitable sur infrastructure Render + Neon.

## Checklist infrastructure (Render + Neon)

- [ ] Compte Neon.tech actif avec base PostgreSQL 16
- [ ] Connection string Neon recuperee (DB_URL)
- [ ] Web Service Render cree avec Docker context `api/`
- [ ] Dockerfile path positionne sur `Dockerfile.prod`
- [ ] Instance type positionne sur `Free` ou `Starter`
- [ ] Health Check Path positionne sur `/api/v1/health`
- [ ] Auto-Deploy GitHub desactive (pilotage par workflow `deploy-main.yml`)

## Checklist application (Variables d'environnement Render)

- [ ] `APP_KEY` positionnee
- [ ] `DB_CONNECTION=pgsql`
- [ ] `DB_URL` (Connection string Neon avec sslmode=require)
- [ ] `DB_SEARCH_PATH=shared_tenants,public`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `RUN_MIGRATIONS=true` (pour le premier bootstrap)
- [ ] `FILESYSTEM_DISK=local`
- [ ] `TENANCY_DEFAULT_TYPE=shared`

## Donnees minimales de recette (Seeders)

- [ ] `php artisan db:seed --class=DatabaseSeeder` execute (via bootstrap Render)
- [ ] `DEMO_SEED_ONCE=true` active pour injecter le jeu de donnees pilote
- [ ] Verifier presence des managers de demo (Ahmed, Fatima)
- [ ] Verifier presence de l'employe de demo (Karim)

## Verification express

- [ ] `GET /api/v1/health` -> 200 (checks database: ok)
- [ ] `GET /login` -> 200
- [ ] Login manager -> redirect `/dashboard`
- [ ] Consultation employe -> ok
- [ ] Generation PDF recu -> ok (DomPDF test)

## Blocage immediat

Stop Beta si:
- Login impossible (401/500)
- Erreur 500 persistante sur le dashboard
- PDF corrompu ou vide
- Fuite de donnees inter-tenant (Isolation Test KO)
- `search_path` non respecte (relation "employees" not found)
