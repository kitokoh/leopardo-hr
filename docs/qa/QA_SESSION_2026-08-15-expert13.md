# QA Leopardo RH — Session expert #13 du 2026-08-15

Mission : implémenter le maximum d'issues ouvertes (spec-kit), rebaser/merger les branches ouvertes,
maintien de `main` vert, audit 360° et consignation Spec Kit.

## Méthode
1. Recon CI/PRs/issues/branches ; protocole anti-doublon #2400 appliqué systématiquement
   (self-assign + marker branch `fix/<issue>-<slug>` avant tout code).
2. Rebase + résolution de conflits des PRs ouvertes contre `origin/main` (le main bouge vite :
   ~20 PRs mergées pendant la session par les agents concurrents).
3. Implémentation web/admin (testable localement : lint, tsc, jest, next/vite build,
   check-i18n-diff.js) puis docs/CI.
4. Audit ciblé des surfaces restantes + réparation des gardes CI rouges sur main.

## PRs mergées (agent expert13)
| PR | Issue | Surface | Contenu |
|---|---|---|---|
| #3797 | #3732 | web | a11y FAQ + Navbar : label recherche, aria-expanded/aria-controls accordéons, drawer localisé |
| #3798 | #3731 | web | OG/Twitter guides ×3 + /demo via generateSEOMetadata + 5 og:image 1200×630 |
| #3799 | #3730 | web | /mobile piloté par useVitrineLocale (fin du useState FR en dur) |
| #3815 | — | ci/docs | main vert : checksum i18n fr resynchronisé + route edge Caddyfile documentée OpenAPI |
| #3817 | — | docs | session report expert13 (v1) |
| #3836 | #3818 | web | 7 suites UI Jest réactivées — assertions alignées sur les design tokens |
| #3844 | #3819 | api | matrice PHP standardisée sur 8.4.1 (composer.json/lock/doc) |
| #3847 | — | docs | registre spec-kit qa-360 : 20/24 tâches done |
| #3886 | — | api | endpoints SHA-256 edge documentés dans openapi.yaml |
| #3992 | #3927/#3924 | web | skip-link localisé (SSR) + SocialShare i18n |
| #3996 | #3922 | web | carte OG alignée sur 14 jours d'essai (4 locales) |
| #4014 | #3921 | web | JSON-LD FAQPage localisé — /faq + /pricing depuis le contenu visible |
| #4030 | #3923 | web | blog — métadonnées/bannière localisées, slug encodé sitemap |
| #4041 | #3926 | web | /changelog — entrées lisibles (10 releases réécrites) |
| #4048 | #3919 | web | garde d'alignement pricing ↔ checkout |
| #4056 | #3925 | web | confetti checkout — aléa figé (fin mismatch SSR/hydration) |
| #4063 | #3937 | admin | window.confirm remplacé par ConfirmDialog i18n (5 sites) |
| #4072 | #3940 | admin | CompaniesView — pays via source canonique useSupportedCountries |
| #4073 | #3936 | admin | toasts temps réel dédupliqués |
| #4075 | #3935 | admin | breadcrumb parent résolu via router.getRoutes() |

## Contributions aux PRs d'autres agents
- #3802 (vitest imports retirés) : détecté le blocage CI global, PR #3803 créée puis fermée au profit de la canonique #3802
- #3821 (canonical #3806 ?lang= links) : ajout de la couverture HeroSection/CTASection
- #3880 : i18n catalog sync — fermée comme superseded par l'approche sync-web.js d'un autre agent (#3853) ; cause racine documentée

## Réparations CI (main rouge)
1. Vitest TS2307 (régression #3734/#3735) → #3802 mergée
2. `[fr] checksum mismatch versions.json` → #3815 puis #4027
3. OpenAPI edge routes manquantes → #3815, #3886
4. Orphan runs / runs bloquées (file saturée) → cancel-orphan-runs + annulation des runs de branches mergées

## Constats vérifiés déjà corrigés (commentaires postés, non re-créés)
#3917 (lien mort /download), #3920 (case studies fictifs + badge illustratif), #3933 (card-lg),
#3934 (CommandPalette predictions), #3939 (Alt+R → /recruitment), #3272 (exports/fleet débloqués)

## Leçons pour les prochains agents
- Le CI est saturé (tous les agents poussent en parallèle) : vérifier actions/runs avant de merger.
- Toujours rebaser sur origin/main frais juste avant de merger (main avance de ~1 merge/min).
- check-i18n-diff.js bloque les littéraux ajoutés dans src/app/** : passer par pageMetadata ou catalogues.
- Après tout merge touchant shared/i18n/locales/*.json, régénérer versions.json sinon validate-and-sync casse.
- `git checkout -B branche origin/branche` peut écraser une branche d'un autre agent : vérifier
  l'auteur des commits avant tout force-push (leçon #3834).
- Issues P0/P1 restantes nécessitant le propriétaire : #3876 (plans tarifaires), #3879 (trial signup prod),
  #3765/#3766/#3767 (stabilisation prod Render/Vercel).
