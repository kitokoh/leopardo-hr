# Quick Start Guide — Leopardo RH

Welcome to Leopardo RH! This guide will help you get your environment set up and make your first API call in minutes.

## Fast Track (Docker) — recommandé

**Prérequis** : Docker >= 24 + Docker Compose v2 (`docker compose`, pas `docker-compose`)

```bash
# 1. Cloner le dépôt
git clone https://github.com/kitokoh/leopardo-hr.git
cd leopardo-hr

# 2. Copier l'environnement (comme documenté dans DEVELOPMENT.md)
cp api/.env.example api/.env

# 3. Démarrer les services
docker compose up -d --build postgres redis api

# 4. Installer les dépendances + générer la clé APP_KEY
docker compose exec api composer install --no-interaction --prefer-dist
docker compose exec api php artisan key:generate --force

# 5. Redémarrer l'API pour qu'elle prenne en compte la nouvelle APP_KEY
#    (php artisan serve lit .env une seule fois au démarrage — cf. issue #1591)
docker compose restart api

# 6. Lancer les migrations (schema public + shared_tenants) et le seed de démo
docker compose exec api php artisan leopardo:migrate --seed --demo
```

Votre API est maintenant disponible sur `http://localhost:8000/api/v1/health`.

> **Pourquoi `leopardo:migrate` et pas `artisan migrate` ?**
> Leopardo RH utilise un modèle multi-tenant hybride à deux schemas PostgreSQL.
> La commande custom `leopardo:migrate` bascule le `search_path` et joue les
> migrations `database/migrations/public/` puis `database/migrations/tenant/`
> dans le bon ordre. Voir `docs/architecture/MULTITENANCY.md`.

---

## Frontend (optionnel)

### Admin Dashboard (Vue.js)

```bash
cd front/admin-dashboard
cp .env.example .env        # ou configurer VITE_API_URL si nécessaire
npm install
npm run dev                 # http://localhost:5173
```

### Vitrine Web (Next.js)

```bash
cd front/web
cp .env.local.example .env.local   # #R11 : NEXT_PUBLIC_API_URL doit pointer sur http://localhost:8000
npm install
npm run dev                        # http://localhost:3000
```

---

## Installation manuelle (PHP/Laravel sans Docker)

**Prérequis** : PHP >= 8.4.1, Composer >= 2.6, PostgreSQL.

```bash
cd api

# 1. Dépendances
composer install

# 2. Environnement
cp .env.example .env
php artisan key:generate          # obligatoire avant la migration

# 3. Migrations
php artisan leopardo:migrate --seed

# 4. Serveur de développement
php artisan serve
```

---

## App Mobile (Flutter)

> Les apps mobiles sont dans `front/mobile_apps/` (`leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`).

```bash
cd front/mobile_apps/leopardo_employee
flutter pub get
flutter run
```

---

## Comptes démo

Après `--seed --demo`, les comptes suivants sont disponibles :

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Manager principal | `ahmed.benali@techcorp-algerie.dz` | `password123` |

---

## Prochaines étapes

- [Architecture du système](architecture/ARCHITECTURE.md)
- [Guide de développement complet](../DEVELOPMENT.md)
- [Directives de contribution](contributing/GUIDELINES.md)
- [Référence API](api/README.md)
