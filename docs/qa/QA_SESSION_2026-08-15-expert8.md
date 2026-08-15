# QA Leopardo RH — Session Expert 8 (audit multi-surface) du 2026-08-15

Mission (propriétaire) : tester la plateforme dans tous les sens (vitrine, web, admin,
mobiles, workflows, API, logiques, onboarding, cohérence), consigner chaque manquement
selon la méthode Spec Kit (issue + spec/plan/tasks), implémenter en fin de test, merger
le max de branches, main VERT.

**Contexte** : swarm d'agents très actif (~210 issues ouvertes, ~100 PRs créées en session,
merges continus sur main). Règle anti-doublon (#2400) appliquée sur chaque constat
(issues + branches + PRs vérifiées au moment de la rédaction).

## Validation effectuée (preuves)

- **Suite backend locale** : PHP 8.4 + PostgreSQL 16 + Redis — tests Unit 497 passed / 4
  skipped ; suite complète (Unit + Feature) lancée sur DB fraîche.
- **PHPStan Strict level 8** : 14-15 erreurs hors baseline sur main (GenerateBankExportJob,
  PayrollCalculator dup keys, fixtures caméras, TrialWelcomeMail) — PRs de fix en vol
  (#3415/#3398/#3455) ; **mes fichiers modifiés : 0 erreur**.
- **Builds locaux** : vitrine Next.js ✅ (0 erreur, lint 0) ; admin Vue ✅ (0 erreur, lint 0).
- **Checkers repo** : migrations 0 collision ; parité .env.example 272 clés OK ;
  0 controller orphelin ; catalogue pays OK ; check-mobile-manifest-routes couvert #3205.
- **Black-box prod** : API v4.23.5 stale (#2627/#2812) ; vitrine /blog 404 malgré sitemap
  (#2906/#2813) ; médias LFS servis comme pointeurs en prod (fix #2868 en code, prod stale) ;
  sitemap publie /signup+/checkout noindex (**#3486**) ; admin pages.dev 200.
- **Audits statiques par surface** (4 scouts) : 50 constats dont ~33 nouveaux dédupliqués.

## Issues créées en session (16)

#3485 SSO vendu vs coming_soon · #3486 sitemap noindex · #3487 seo.ts t('fr') ·
#3488 MiniCaseStudies chiffres · #3489 4 pages FR · #3490 EditUserModal orphelin ·
#3491 états d'erreur silencieux admin · #3492 toasts Leaves/Payroll ·
#3493 Growth confirm+NaN · #3494 Webhooks confirm + KPIs Reports ·
#3495 UsersView placeholders + modale · #3496 PlanSeeder 30j · #3497 throttle SSO ·
#3498 routes mortes employee · #3499 Sentry 1.0 hr/manager · #3500 marketing await + dead code.

## Implémentation (PR #3581)

- Web : SSO retiré des plans vendus (fusionné avec le fix swarm « bientôt disponible »),
  sitemap sans /signup+/checkout, meta pricing localisée par requête, MiniCaseStudies sans
  chiffres (disclaimer swarm conservé), meta checkout alignée.
- Admin : EditUserModal supprimé, états d'erreur Training/Predictions/Reports, KPIs
  overtime/payroll chargés, toasts Leaves/Payroll, dialogs in-app (Growth/Webhooks),
  UsersView summary + modale glass-card.
- API : PlanSeeder 14j (fusionné avec centralisation config), throttle SSO + test.
- Mobile : 4 routes mortes employee retirées, tracesSampleRate 0.2 (hr/manager), marketing
  await dans StartupGate, extractDataList smart_attendance.

## Constats swarm couverts pendant la session (dédupliqués, pas re-créés)

#3434/#3435/#3436/#3425/#3426/#3429/#3430/#3437/#3432/#3433/#3442/#3444/#3447/#3450/#3451
+#3282-3296 (mobile) + PRs en vol sur PHPStan (#3415/#3398/#3455), per_page (#3418/#3420),
SSO anti-SSRF (#3439), checkout fallback (#3371/#3440), sitemap share/offline (#3355),
canonicals (#3408), etc.

## État final

- PR #3581 en attente de checks (file CI saturée — issue #2488).
- Main : merges continus par le swarm pendant la session ; PHPStan main en cours de
  correction par PRs dédiées.
- Token propriétaire : révoqué en fin de session (jamais persisté hors env de session).

Spec Kit : `.specify/features/qa-expert8-session-2026-08-15/{spec,plan,tasks}.md`.
