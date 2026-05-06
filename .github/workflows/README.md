# GitHub Actions Workflows

Ce dossier contient les workflows CI/CD automatisés pour la vitrine Leopardo.

## Workflows

### 1. **test.yml** - Tests Automatiques
- **Déclencheur**: Push et PR sur main, develop, staging
- **Actions**:
  - Installation des dépendances
  - Exécution des tests unitaires (Jest)
  - Exécution des tests E2E (Playwright)
  - Upload des rapports de couverture vers Codecov
  - Archivage des résultats de test

**Matrices testées**: Node.js 18.x et 20.x

### 2. **lint.yml** - Linting & Type Checking
- **Déclencheur**: Push et PR sur main, develop, staging
- **Actions**:
  - Vérification ESLint
  - Type checking TypeScript (strict mode)
  - Vérification des console.log en production
  - Upload du rapport ESLint

### 3. **build.yml** - Build & Déploiement
- **Déclencheur**: Push et PR sur main, develop, staging
- **Actions**:
  - Installation des dépendances
  - Linting et type checking
  - Exécution des tests
  - Build Next.js
  - Déploiement sur Vercel (staging ou production selon la branche)
  - Commentaires PR avec URL de déploiement

**Déploiements**:
- `staging` → https://staging.leopardo.com
- `main` → https://leopardo.com (production)

### 4. **lighthouse.yml** - Lighthouse CI
- **Déclencheur**: Push et PR sur main, develop, staging
- **Actions**:
  - Build Next.js
  - Démarrage du serveur
  - Exécution de Lighthouse sur toutes les pages
  - Vérification des scores (> 90)
  - Commentaires PR avec résultats
  - Archivage des rapports

**Pages testées**:
- Landing page
- Gestion Employés
- Gestion Documents
- Comptabilité & Paie
- Marketing Digital
- Pricing
- À Propos
- Blog

## Configuration Requise

### Secrets GitHub

Les secrets suivants doivent être configurés dans les paramètres du repository:

```
VERCEL_TOKEN              # Token d'authentification Vercel
VERCEL_ORG_ID             # ID de l'organisation Vercel
VERCEL_PROJECT_ID         # ID du projet Vercel
NEXT_PUBLIC_ANALYTICS_ID  # ID Google Analytics 4
NEXT_PUBLIC_MIXPANEL_TOKEN # Token Mixpanel
```

### Variables d'Environnement

Les variables d'environnement doivent être configurées dans `.env.local` (développement) et dans Vercel (production/staging).

## Statuts de Build

Les statuts des workflows sont affichés dans le README principal du projet.

## Optimisations

### Caching
- Les dépendances npm sont cachées pour accélérer les builds
- Le cache est basé sur `package-lock.json`

### Parallélisation
- Les tests unitaires et E2E s'exécutent en parallèle
- Les vérifications de linting et type checking s'exécutent en parallèle

### Artifacts
- Les rapports de test sont archivés pendant 30 jours
- Les rapports Lighthouse sont archivés pendant 30 jours
- Les builds Next.js sont archivés pendant 7 jours

## Dépannage

### Build échoue avec "Module not found"
- Vérifier que toutes les dépendances sont listées dans `package.json`
- Exécuter `npm ci` localement pour reproduire

### Tests échouent en CI mais pas localement
- Vérifier les variables d'environnement
- Vérifier les chemins de fichiers (sensibilité à la casse)
- Vérifier les timeouts (augmenter si nécessaire)

### Déploiement Vercel échoue
- Vérifier les secrets Vercel
- Vérifier les variables d'environnement dans Vercel
- Vérifier les logs de build dans Vercel

## Ressources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Vercel GitHub Integration](https://vercel.com/docs/git/vercel-for-github)
- [Lighthouse CI Documentation](https://github.com/GoogleChrome/lighthouse-ci)
- [Jest Documentation](https://jestjs.io/)
- [Playwright Documentation](https://playwright.dev/)
