# Monorepo Tooling — Leopardo HR

## Vue d'ensemble

| Couche | Outil | Usage |
|---|---|---|
| JS/TS (web + admin) | scripts npm racine avec `--prefix` (pas de workspaces) | Lint, test, build |
| Flutter (mobile) | **Melos** | Analyse, test, build, l10n, codegen |
| Backend (Laravel) | Makefile (existant) | Tests, migrations, qualité |

> **Note** : ce dépôt n'utilise pas npm workspaces — `front/web` et
> `front/admin-dashboard` gardent chacun leur propre `package-lock.json`.
> Turbo a été retiré (2026-07) : il n'était pas réellement invoqué par la CI
> (`web-ci.yml` utilise `npm run lint`/`npm ci && npm run build`, pas `turbo`)
> et sans workspaces son graphe de dépendances/cache n'apportait aucun
> bénéfice réel. Si le besoin de cache/parallélisation redevient concret,
> réintroduire Turbo **en même temps** qu'une vraie migration vers les
> workspaces npm, pas séparément.

---

## JavaScript / TypeScript

### Prérequis
```bash
node >= 20
npm  >= 10
```

### Setup
```bash
# Depuis la racine : installe seulement les deps racine (aucune, hors engines).
# Chaque app gère son propre npm install :
npm install --prefix front/web
npm install --prefix front/admin-dashboard
```

### Commandes racine
```bash
npm run lint          # lint web + admin
npm run test          # tests web (Jest)
npm run build         # build web + admin

npm run web:dev       # Next.js dev server
npm run web:e2e       # Playwright (web)
npm run admin:dev     # Vite dev server (admin)
```

---

## Flutter / Dart (Mobile)

### Prérequis
```bash
flutter >= 3.22
dart    >= 3.3
melos   >= 6.0
```

### Installation de Melos
```bash
dart pub global activate melos
```

### Setup
```bash
melos bootstrap   # ou: npm run mobile:bootstrap
```

### Commandes
```bash
melos run analyze       # dart analyze sur tous les packages
melos run format:fix    # dart format --fix sur tous
melos run test          # flutter test sur tous
melos run test:coverage # avec couverture
melos run gen           # build_runner codegen
melos run l10n          # flutter gen-l10n

# Cibles depuis la racine (shorthand)
npm run mobile:test
npm run mobile:analyze
npm run mobile:l10n
```

### Filtrer un package
```bash
melos run test --scope=leopardo_employee
melos run analyze --scope=leopardo_core
```

---

## CI/CD

Les workflows GitHub Actions utilisent ces commandes :
- `web-ci.yml` → `npm ci` puis `npm run lint` / `npm run build` (par app, sans Turbo)
- `mobile-apps-ci.yml` → `melos bootstrap && melos run analyze && melos run test`

---

## Packages Flutter

| Package | Rôle | Dépend de |
|---|---|---|
| `leopardo_core` | Design system, services partagés | — |
| `leopardo_employee` | App employé | `leopardo_core` |
| `leopardo_hr` | App manager/RH dédiée | `leopardo_core` |
| `leopardo_manager` | App manager/RH | `leopardo_core` |
| `leopardo_platform_admin` | App super-admin | `leopardo_core` |
