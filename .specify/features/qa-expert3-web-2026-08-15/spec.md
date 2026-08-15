# Feature Specification: QA Expert #3 — Web/Vitrine (front/web) (2026-08-15)

**Feature**: `qa-expert3-web-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress

## Findings traités

### #3021 [P2] — og:image 404 sur ~20 pages — **CORRIGÉ** (PR #3408)
> `seo.ts` référençait 20 PNG `/og/*.png` inexistants (seul `default.png` existe). `getOGImageUrl()` + `seo-metadata.ts` → `brand/opengraph.svg` (asset OG canonique AGENTS.md).

### #3022 [P2] — clés i18n brutes affichées dans le flux OTP — **CORRIGÉ** (PR #3408)
> `setOtpError('c.otpInvalidLength')` etc. affichaient les clés littérales → valeurs localisées `c.*`.

### #3027 [P2] — dashboard client : données fabriquées — **CORRIGÉ** (PR #3408)
> Carte « Leo IA » : citation inventée (-15 %) → message de capacité honnête. « Présence hebdo » : bars +12 % codés en dur → données réelles `/dashboard/summary` + état vide honnête.

### NOUVEAU #3443 [P2] — pricing : libellés FR en dur (100% Gratuit / Gratuit / Sans carte bancaire · Pour toujours) affichés aussi en EN/TR/AR — **CORRIGÉ** (PR #3408)
> Ajout `plans.freeBadge/freePriceLabel/freeNoCard` dans les 4 locales.

## Constats environnement (non code — déploiement)
- Vitrine Vercel live périmée : `/blog` 404 (#2647), pricing affiche d'anciennes chaînes (#2813).
- Signup form EN affiché en FR en live = build périmé (#2813/#2727) — le code main est localisé (4 catalogues vérifiés).

## Restants
- #3023/#3024/#3025 (cohérence pricing/cartes), #3028/#3029 (PWA), #3032 (FAQ) — hors périmètre de cette vague (vérifiés contre issues existantes).
