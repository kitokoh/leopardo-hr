# Feature Specification: QA Expert #3 — main vert + essai self-service + cohérence essai 14j (2026-08-15)

**Feature**: `qa-expert3-main-green-2026-08-15`
**Created**: 2026-08-15
**Status**: Implemented (PRs #3139, #3185, #3211, #3215, #3218)
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + mission propriétaire (merger les branches, tester la plateforme, consigner chaque manquement selon la méthode Spec Kit, garder main vert).

## Contexte

Session experte dédiée : (1) réparer le rouge PHPStan Strict accumulé sur main par la vague de merges sans CI (queue GitHub Actions saturée), (2) tester le parcours d'essai self-service de bout en bout sur base fraîche, (3) aligner la durée d'essai sur toutes les surfaces.

## Findings (issues créées)

### #3210 [P1] — Essai self-service mort sur base fraîche : `company_requests.status` processing/active bloqués par la contrainte CHECK (SQLSTATE 23514)
> **Constat** : `VerifyTrialSignup` passe `status=processing` (claim atomique #2996) puis `active`, mais la contrainte `company_requests_status_check` (créée par `2026_05_02_000003`) n'autorise que pending/approved/rejected → 503 sur toute base fraîche (5 tests rouges).
> **Fix** : migration publique idempotente `2026_08_15_000006` recréant la contrainte avec les 5 statuts réels. 8/8 tests verts (PR #3211).

## Findings mineurs (corrigés sans issue dédiée — classes couvertes par #3057/#3056/#3018/#2627)

- **Échec OTP avalé** (→ #3057) : `RequestTrialSignup` avalait l'échec du mail → 200 « code envoyé » sans code. Fix : `execute(): bool` + 502 `OTP_SEND_FAILED` (4 locales) + test.
- **Webhook email-bounce non configurable** (→ #3058) : `services.mail_bounce_webhook.secret` absent de config/.env.example → 503 permanent. Fix : config + `MAIL_BOUNCE_WEBHOOK_SECRET` + 3 tests rendus déterministes.
- **Durée d'essai incohérente** (→ #3056) : verify API `days=30` vs `ends_at=+14j` ; metas SEO « Essai gratuit 30 jours » + plans fantômes Starter/Business ; badge admin « 30 jours » (4 locales). Fix : 14 jours partout (canon #2944/#3012).
- **GenerateBankExportJob runtime bug** (PHPStan) : `generate()` appelé avec 3 params (signature passée à 2) → ArgumentCountError au run. Fix : appel 2 params + bloc mort retiré.
- **PayrollCalculator duplicate keys** (PHPStan) : `rules_version/identifier/period` en double dans `$run->update()` (artefact merge #2973). Fix : bloc dupliqué retiré.
- **Tests dépendants de l'ordre** : `EmailBounceWebhookControllerTest` (3) sans secret ni header ; `GrowthModuleTest` clé `country` dupliquée. Fix : déterministes.
- **Drift mobile manifest** (#2212 checker rouge) : le manifeste sert `/team /tasks /notifications /attendance /me/monthly /history /modules` que le routeur `leopardo_manager` ne déclare pas (post-#3117). À traiter par la vague mobile.
- **Déploiements live périmés** (classe #2627/#2632) : sitemap live liste 10 URLs `/blog/*` en 404 (build Vercel antérieur au gate `enableBlog`) ; `/api-explorer` 500 (fix mergé non déployé) ; Workers Cloudflare « gestionemploye » en échec (infra).
