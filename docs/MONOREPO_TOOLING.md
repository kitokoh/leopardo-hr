# Monorepo Tooling — Leopardo HR

## Vue d'ensemble

| Couche | Outil | Usage |
|---|---|---|
| JS/TS (web + admin) | npm workspaces + **Turbo** | Lint, test, build avec cache |
| Flutter (mobile) | **Melos** | Analyse, test, build, l10n, codegen |
| Backend (Laravel) | Makefile (existant) | Tests, migrations, qualité |

---

## JavaScript / TypeScript

### Prérequis
```bash
node >= 20
npm  >= 10
```

### Setup
```bash
npm install   # depuis la racine — installe tous les workspaces
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

### Avec Turbo (cache)
```bash
npx turbo build       # build tous les packages JS avec cache
npx turbo lint        # lint avec cache
npx turbo test        # tests avec cache
```

> Turbo met en cache les artefacts de build. Un second `turbo build` sans changement
> prend < 1 seconde.

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
- `web-ci.yml` → `npx turbo lint test build`
- `mobile-apps-ci.yml` → `melos bootstrap && melos run analyze && melos run test`

---

## Packages Flutter

| Package | Rôle | Dépend de |
|---|---|---|
| `leopardo_core` | Design system, services partagés | — |
| `leopardo_employee` | App employé | `leopardo_core` |
| `leopardo_manager` | App manager/RH | `leopardo_core` |
| `leopardo_platform_admin` | App super-admin | `leopardo_core` |
