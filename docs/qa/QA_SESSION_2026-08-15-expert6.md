# QA Leopardo HR — Session Expert 6 (2026-08-15, 2e passe — audit 360°)

> Mission : tester la plateforme dans tous les sens (vitrine, web, admin,
> mobiles, workflows, API, logiques métier, onboarding, UI/UX), consigner
> chaque manquement selon la méthode Spec Kit, implémenter le max d'issues
> ouvertes, merger le max de branches, `main` VERT en permanence.

## Contexte

- Swarm multi-agents actif : 21 PRs ouvertes au début de session, 77 issues.
- Sandbox : Node 24 uniquement (pas de PHP/Dart) → validation locale
  web/admin (vite build, eslint, next build) ; PHP/Dart validés par la CI.
- Protocole anti-doublon #2400 appliqué avant chaque issue/PR (branches + PRs
  vérifiées).

## Audit 360° — surface par surface

### Landing Page (Vitrine `front/web`)
- `next build` : **exit 0** (73 pages SSG) — l'échec initial ENOENT font
  `NotoSansArabic-Regular.ttf` était transitoire (fichier LFS matérialisé
  ensuite), build re-passé vert.
- Anti-régression : 0 `href="#"`, 0 `leopardo.local`, 0 résidu « 30 jours »
  (fix #3687 vérifié), 0 mojibake (i18n-debt 0 nouveau).

### Web App / Admin (`front/admin-dashboard`)
- 🔴 **P1 régression merge** : route `/users/:id` → `UserDetailView.vue`
  réintroduite alors que la vue avait été supprimée (17541e5c, #3280) →
  `vite build` cassé sur main. Corrigé (PR #3711 mergé).
- 🔴 **P1 régression #3700** : `AnalyticsView.vue` utilisait
  `localeStore.current` sans déclarer `const localeStore = useLocaleStore()`
  → ReferenceError runtime + lint rouge. Corrigé (PR #3711).
- `npm run lint` admin : 0 erreur après fixes (9 warnings pré-existants
  hors périmètre).
- **Le check requis CI « Frontend — ESLint + TypeScript » ne couvre que
  `front/web`** — la casse du build admin n'est pas détectée par les checks
  requis (constat #3708 connexe).

### API (`api/` Laravel)
- 🔴 **P1** : collisions de préfixes de migrations sur main (merges
  concurrents) — public 000004/000006 ×2, tenant 000001 ×2 + doublon strict
  public 000007. Toutes idempotentes, mais garde #1962 rouge. Corrigé par
  renumérotation (PR #3712 mergé).
- P1 live confirmé : prod v4.23.5 stale (queue `sync`, `/api-explorer` 500,
  `/demo-users` 404) — issues existantes #2627/#2632/#3259/#3562 réaffirmées.
- OpenAPI : 599/720 routes couvertes, 0 nouveau drift (121 gaps allowlist).
- #3149 implémenté : `SocialDeclarationService` extrait (556→503 l.), gardes
  `assertPayrollRunAccess` partagées (PR #3720).

### Mobile (Flutter)
- #3284 implémenté : 9 routes GoRouter mortes retirées de `leopardo_hr`
  (PR #3715) — motif aligné sur le chantier manager #3702.
- i18n-debt : 8 926 signaux (2 731 P1) — dette connue (#2755/#2740), rapport
  régénéré `docs/validation/I18N_DEBT_REPORT_2026_08_15.md`.

### Workflows / Onboarding / Kiosk / Edge
- Parcours trial : code revu (RequestTrialSignup/VerifyTrialSignup/
  SelfServiceTrialController) — robustesse confirmée post-#2996 (verrou
  atomique + statut `processing`). La 500 prod #3259 reste liée au driver
  queue `sync` sur prod stale (ops).
- Kiosk/Edge : PRs #3698/#3686 mergées (auth bridge, drift domaines).

## Bilan chiffré

| Métrique | Valeur |
|---|---|
| PRs ouvertes en début | 21 |
| PRs mergées (dont miennes) | ~20 (file désengorgée) |
| PRs créées (expert 6) | 4 : #3711, #3712 (mergés), #3715, #3720 |
| Issues implémentées (expert 6) | #3280 (ré-rouverte), #1962, #3284, #3149 |
| Issues ouvertes restantes | 44 (dont ~25 P1 ops non fixables en code) |
| Gardes repo sur main | app-version ✅, env-parity ✅, migrations ✅, openapi ✅ |

## Leçons

1. Les merges de conflit « simples » (choisir un côté) peuvent **droper du
   code vivant** — toujours re-vérifier les imports/initialisations après
   résolution (localeStore).
2. La CI requise ne couvre pas le build admin → un garde `vite build` admin
   devrait être requis (#3708).
3. GitHub calcule `mergeable_state` en cache — merger origin/main dans la
   branche force le recalcul avant de merger.


## 🔴 Découverte majeure — CI backend globale réparée (P1)

**Symptôme** : Backend Coverage + PHPStan rouges sur TOUTES les PRs (même UI-only) → `composer install` échoue au post-autoload-dump (`package:discover`).

**Root cause** : le merge #3693 a introduit `app()->environment('production')` dans `config/queue.php` — appel illégal au conteneur pendant le chargement des configs → `Target class [env] does not exist`.

**Fix** : `env('APP_ENV', 'production') === 'production'` — PR #3778 (mergée). Vérifié localement (PHP installé dans le sandbox, `php artisan package:discover` exit 0).

**Leçon** : ne JAMAIS appeler `app()` dans un fichier config Laravel.

## Fichiers

- Spec-kit : `.specify/features/qa-expert6-2026-08-15/` (findings-registry
  mis à jour)
- Specs : `docs/specifications/ISSUE_3284_REMOVE_DEAD_HR_ROUTES.md`,
  `docs/specifications/ISSUE_3149_SOCIAL_DECLARATION_SERVICE.md`
