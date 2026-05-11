# Contributing to Leopardo RH

Merci de contribuer a Leopardo RH ! Ce guide explique comment participer au projet.

## Prerequis

- PHP 8.4+
- Composer 2+
- PostgreSQL 16+
- Redis 7+ (optionnel, pour queues/cache)
- Node.js 20+ (pour le frontend web)
- Docker & Docker Compose (recommande)

## Demarrage rapide

```bash
# Cloner le depot
git clone https://github.com/kitokoh/leopardo-hr.git
cd leopardo-hr

# Demarrer avec Docker Compose
docker compose up -d

# Ou sans Docker :
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Voir [DEVELOPMENT.md](DEVELOPMENT.md) pour les instructions detaillees.

## Workflow de contribution

1. **Fork** le depot sur GitHub
2. **Creer une branche** depuis `main` :
   ```bash
   git checkout -b feat/ma-feature
   ```
3. **Implementer** votre changement
4. **Tester** localement :
   ```bash
   cd api && php artisan test
   ```
5. **Commit** avec un message clair :
   ```bash
   git commit -m "feat: description courte du changement"
   ```
6. **Push** et creer un **Pull Request** vers `main`

## Conventions de commit

Nous suivons [Conventional Commits](https://www.conventionalcommits.org/) :

- `feat:` — Nouvelle fonctionnalite
- `fix:` — Correction de bug
- `docs:` — Documentation
- `test:` — Ajout/modification de tests
- `refactor:` — Refactoring sans changement de comportement
- `chore:` — Maintenance (CI, deps, etc.)

## Structure du projet

```
api/                    # Backend Laravel (PHP)
├── app/
│   ├── Http/Controllers/Api/V1/   # Controleurs API
│   ├── Models/                     # Modeles Eloquent
│   ├── Services/                   # Services metier (DDD)
│   ├── Policies/                   # RBAC Policies
│   └── Events/                     # Domain Events
├── tests/Feature/                  # Tests fonctionnels
├── database/migrations/            # Migrations PostgreSQL
└── routes/                         # Routes API

front/                  # Frontends
├── admin-dashboard/    # Dashboard admin (Next.js)
├── web/                # Vitrine publique (Next.js)
└── mobile/             # App mobile (Flutter)

docs/                   # Documentation projet
```

## Regles de code

- **PHP** : PSR-12, utiliser Laravel Pint (`./vendor/bin/pint`)
- **TypeScript** : ESLint + Prettier
- **Tests** : Chaque feature doit avoir des tests. Ne pas modifier les tests existants sans raison.
- **Migrations** : Idempotentes sur PostgreSQL (voir [AGENTS.md](AGENTS.md))
- **API** : Documenter les endpoints dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`
- **Changelog** : Ajouter une entree dans `CHANGELOG.md` pour chaque changement visible

## Good First Issues

Cherchez les issues taguees [`good first issue`](https://github.com/kitokoh/leopardo-hr/labels/good%20first%20issue) pour commencer. Ces issues sont specifiquement selectionnees pour les nouveaux contributeurs.

## Signaler un bug

Utilisez le template [Bug Report](https://github.com/kitokoh/leopardo-hr/issues/new?template=bug_report.md).

## Proposer une fonctionnalite

Utilisez le template [Feature Request](https://github.com/kitokoh/leopardo-hr/issues/new?template=feature_request.md).

## Code de conduite

Ce projet suit le [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). En participant, vous acceptez de respecter ces regles.

## Licence

En contribuant, vous acceptez que vos contributions soient sous la meme [licence MIT](LICENSE) que le projet.
