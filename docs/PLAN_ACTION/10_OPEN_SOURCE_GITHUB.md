# 10 — OPEN SOURCE & AMELIORATION GITHUB

**Objectif :** Rendre le depot GitHub attractif pour les contributeurs open-source, reduire le bus factor, et construire une communaute.

---

## 1. Etat actuel du depot

### Points forts

- README professionnel avec badges et diagrammes
- Documentation abondante (40+ fichiers)
- CI/CD en place (10 workflows)
- Templates GitHub (bug report, feature request, PR)
- CODE_OF_CONDUCT.md et ../../dev-hub/CONTRIBUTING.md presents
- SECURITY.md present

### Points faibles pour attirer des devs

- Pas de `good first issue` tagges
- Pas de labels organises
- Pas de ../../dev-hub/DEVELOPMENT.md clair pour les nouveaux contributeurs
- Pas de Docker Compose pour le setup en 1 commande
- Dependencies non triviales a installer (PHP 8.4, PostgreSQL 16, Flutter)
- Pas de Gitpod/Codespaces/DevContainer configuration
- README pointe vers `your-org/leopardo-rh` au lieu du vrai repo
- Pas de releases GitHub (tags, changelog formattes)
- Pas de project board visible

---

## 2. Actions pour ameliorer le depot

### 2.1 Docker Compose pour dev (setup en 1 commande)

```yaml
# docker-compose.yml (racine)
services:
  api:
    build:
      context: ./api
      dockerfile: Dockerfile.dev
    ports:
      - "8000:8000"
    volumes:
      - ./api:/var/www/html
    depends_on:
      - postgres
      - redis
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_DATABASE: leopardo
      DB_USERNAME: leopardo
      DB_PASSWORD: secret
      REDIS_HOST: redis

  postgres:
    image: postgres:16-alpine
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: leopardo
      POSTGRES_USER: leopardo
      POSTGRES_PASSWORD: secret
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  dashboard:
    build:
      context: ./admin-dashboard
      dockerfile: Dockerfile.dev
    ports:
      - "3000:3000"
    volumes:
      - ./admin-dashboard:/app
      - /app/node_modules

  web:
    build:
      context: ./web
      dockerfile: Dockerfile.dev
    ports:
      - "3001:3001"
    volumes:
      - ./web:/app
      - /app/node_modules

  traccar:
    image: traccar/traccar:latest
    ports:
      - "8082:8082"
    volumes:
      - traccar-data:/opt/traccar/data

volumes:
  pgdata:
  traccar-data:
```

### 2.2 DevContainer (GitHub Codespaces / VS Code)

```json
// .devcontainer/devcontainer.json
{
    "name": "Leopardo RH Dev",
    "dockerComposeFile": "../docker-compose.yml",
    "service": "api",
    "workspaceFolder": "/var/www/html",
    "features": {
        "ghcr.io/devcontainers/features/node:1": {"version": "20"},
        "ghcr.io/nicklasoverby/devcontainer-features/flutter:1": {}
    },
    "postCreateCommand": "composer install && php artisan migrate --seed",
    "customizations": {
        "vscode": {
            "extensions": [
                "bmewburn.vscode-intelephense-client",
                "shufo.vscode-blade-formatter",
                "Dart-Code.flutter",
                "bradlc.vscode-tailwindcss"
            ]
        }
    }
}
```

### 2.3 Labels GitHub organises

Creer ces labels avec `gh label create` :

| Label | Couleur | Description |
|-------|---------|-------------|
| `good first issue` | #7057ff | Facile pour un nouveau contributeur |
| `help wanted` | #008672 | Besoin d'aide de la communaute |
| `bug` | #d73a4a | Quelque chose ne fonctionne pas |
| `enhancement` | #a2eeef | Nouvelle fonctionnalite |
| `documentation` | #0075ca | Amelioration de la documentation |
| `module:payroll` | #e4e669 | Module Paie |
| `module:leave` | #e4e669 | Module Conges |
| `module:recruitment` | #e4e669 | Module Recrutement |
| `module:training` | #e4e669 | Module Formation |
| `module:tracking` | #e4e669 | Module Tracking vehicules |
| `module:ai` | #e4e669 | Module IA |
| `module:billing` | #e4e669 | Module Billing |
| `surface:api` | #bfdadc | Backend API |
| `surface:web` | #bfdadc | Dashboard web |
| `surface:mobile` | #bfdadc | App mobile Flutter |
| `surface:kiosk` | #bfdadc | Kiosk ZKTeco |
| `priority:critical` | #b60205 | Bloquant |
| `priority:high` | #d93f0b | Important |
| `priority:medium` | #fbca04 | Normal |
| `priority:low` | #0e8a16 | Bas |
| `i18n` | #c5def5 | Internationalisation |
| `ci/cd` | #c5def5 | Integration continue |
| `tests` | #c5def5 | Tests |

### 2.4 Good First Issues

Creer au moins 10 issues taguees `good first issue` :

1. Ajouter les traductions manquantes en arabe pour le module absences
2. Creer un factory pour le modele Contract
3. Ajouter la validation email unique par company dans EmployeeController
4. Ecrire le test Feature pour GET /api/v1/departments avec pagination
5. Ajouter le champ `manager_id` au modele Employee
6. Creer le composant `StatusBadge` reutilisable dans admin-dashboard
7. Ajouter le dark mode toggle dans le dashboard
8. Corriger les typos dans la documentation francaise
9. Ajouter le middleware RequestId pour les logs
10. Creer le seeder pour les TaxSlabs de l'Algerie

### 2.5 ../../dev-hub/DEVELOPMENT.md

```markdown
# Development Guide

## Prerequisites

- Docker & Docker Compose (recommande)
- OU : PHP 8.4, Composer, PostgreSQL 16, Node.js 20, Flutter 3.x

## Quick Start (Docker)

    git clone https://github.com/kitokoh/leopardo-hr.git
    cd leopardo-hr
    docker compose up -d
    docker compose exec api php artisan migrate --seed

    # API:       http://localhost:8000
    # Dashboard: http://localhost:3000
    # Vitrine:   http://localhost:3001
    # Traccar:   http://localhost:8082

## Quick Start (local)

    cd api && composer install && cp .env.example .env
    php artisan key:generate && php artisan migrate --seed
    php artisan serve

    cd front/admin-dashboard && npm install && npm run dev
    cd front/web && npm install && npm run dev

## Running Tests

    cd api && vendor/bin/pest
    cd front/admin-dashboard && npx playwright test
    cd front/mobile && flutter test

## Project Structure

    api/                  # Laravel 11 API (PHP 8.4)
    front/admin-dashboard/      # Admin platform (Next.js)
    web/                  # Public website (Next.js)
    mobile/               # Employee app (Flutter)
    front/zkteco-kiosk/         # Biometric kiosk
    shared/               # Shared i18n catalogs
    docs/                 # Documentation
    ../../dev-hub/scripts/              # Utility scripts

## Contributing

See ../../dev-hub/CONTRIBUTING.md for guidelines.
```

### 2.6 Releases GitHub

Commencer a taguer les releases :

```bash
git tag -a v0.1.0 -m "MVP Release — Core HR & Attendance"
git push origin v0.1.0
```

Creer une GitHub Release avec le changelog formate.

### 2.7 GitHub Project Board

Creer un project board public avec les colonnes :

- Backlog
- Ready
- In Progress
- Review
- Done

Lier les issues aux items du board.

### 2.8 README corrections

- Remplacer `your-org/leopardo-rh` par `kitokoh/leopardo-hr`
- Ajouter un GIF de demo ou screenshot du dashboard
- Ajouter les liens Discord/Telegram pour la communaute
- Ajouter la section "Star History"

---

## 3. Licence

Le projet est actuellement sous licence MIT. Pour un produit SaaS open-source, envisager :

- **AGPL-3.0** — Force les modifications a etre partagees (comme ERPNext)
- **MIT** — Permet tout usage (actuel)
- **BSL (Business Source License)** — Open-source mais pas pour usage commercial concurrent (comme Sentry)

Recommandation : rester MIT pour attirer des contributeurs. La valeur est dans le SaaS, pas dans le code.

---

## 4. Communaute

### Actions

- [ ] Creer un serveur Discord "Leopardo RH Community"
- [ ] Ajouter un lien Discord dans le README
- [ ] Publier sur GitHub Discussions (activer la feature)
- [ ] Poster sur Reddit r/selfhosted, r/opensource, Hacker News "Show HN"
- [ ] Creer un compte Twitter/X @LeopardoRH pour les updates
- [ ] Publier un article "Building an HR SaaS for Africa" sur Dev.to/Medium

---

## 5. Taches

- [x] **T-OSS-01** : Creer `docker-compose.yml` et les Dockerfiles — **FAIT** (`docker-compose.yml` + `front/web/Dockerfile.dev`)
- [x] **T-OSS-02** : Creer `.devcontainer/devcontainer.json` — **FAIT** (`.devcontainer/devcontainer.json`)
- [x] **T-OSS-03** : Creer `dev-hub/DEVELOPMENT.md` — **FAIT**
- [ ] **T-OSS-04** : Creer les labels GitHub (script `../../dev-hub/scripts/setup-labels.sh`)
- [ ] **T-OSS-05** : Creer 10 issues "good first issue"
- [ ] **T-OSS-06** : Corriger les URLs dans le README
- [ ] **T-OSS-07** : Ajouter des screenshots/GIF au README
- [ ] **T-OSS-08** : Creer la premiere release GitHub (v0.1.0)
- [ ] **T-OSS-09** : Activer GitHub Discussions
- [ ] **T-OSS-10** : Creer le serveur Discord
- [ ] **T-OSS-11** : Publier sur Reddit/HN/Dev.to
- [ ] **T-OSS-12** : Creer le GitHub Project Board public
- [ ] **T-OSS-13** : Ajouter Codespaces badge au README
