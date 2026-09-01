# Plan: Audit expert Web & Vitrine — 2026-08-15

**Input**: spec.md (US C1-C6) + Constitution + audit 2026-08-15

## Architecture / Décisions techniques

- **C1 Checkout honnête** : quand `STRIPE_SECRET_KEY` est absent ou `sk_test_` → le tunnel **payant** affiche un état « paiement non disponible » explicite (pas de formulaire carte, pas de succès simulé, pas de promesse d'email) ; le plan **gratuit** reste fonctionnel avec le parcours OTP complet (`requestedWorkflow=guided_trial`). Quand une clé **live** est configurée → Stripe Checkout réel (session serveur + redirect), le formulaire carte local est retiré (Stripe.js gère le paiement). Le endpoint `/api/billing/checkout` ne renvoie plus jamais de « succès » sandbox.
- **C2 Identité & données** : un résolveur `siteUrl` unique (`src/lib/site-url.ts`) utilisé par layout/sitemap/robots/seo (fallback prod sûr) ; canonical blog depuis `siteUrl` ; `lang`/`dir` calculés par requête (cookie/header) dans le root layout ; stat « Live: 18 » retiré ou branché sur un endpoint réel ; témoignages marqués démo (`noindex` + bandeau) ou retirés ; `generateProductSchema` sans rating fabriqué.
- **C3 SEO/PWA** : `ogImage` → route générée `/opengraph-image` (ou ajouter `public/og/*.png` générés) ; `sw.js` : précache = `/`, `/offline` + routes réelles uniquement ; manifest : 30 jours + icône `icon.svg`/existant ; suppression de la route orpheline `/api/robots` (ou pointe sur `/sitemap.xml`).
- **C4 OAuth** : `googleAuthHref()` du checkout → même proxy same-origin que login (`/api/v1/auth/google`).
- **C5 i18n** : `SignupForm`, checkout, logout, bannière login, carrières → catalogue i18n (copies existantes par locale) ; le POST d'inscription envoie la locale réelle ; relecture des chaînes arabes.
- **C6 Fallbacks** : section démo du login rendue uniquement si `/demo-users` répond des utilisateurs ; blog : retirer/rafraîchir les posts 2023-24 ; `vercel.json` : redirect mort supprimé, CSP gardé à un seul endroit ; section apps → liens store réels ou retrait, fallback `/download` retiré si aucune cible.

## Phases

### Phase 1 — P1 (C1)
- Checkout honnête (retrait du formulaire carte + succès sandbox + promesse d'email ; état « paiement indisponible » ; plan gratuit OTP complet).

### Phase 2 — P2 (C2, C3, C4)
- Résolveur siteUrl + canonical + lang/dir SSR + Live stat + témoignages + schema rating.
- OG images, SW precache, manifest, route robots.
- OAuth checkout → proxy.

### Phase 3 — P3 (C5, C6)
- i18n SignupForm/checkout/logout/login/carrières + locale POST + typos arabes.
- Modale démo conditionnelle, blog périmé, vercel.json, section apps.

## Fichiers touchés (référence)

- `front/web/src/app/(landing)/checkout/page.tsx`, `src/app/(landing)/checkout/success/page.tsx`, `src/app/api/billing/checkout/route.ts`
- `front/web/src/app/layout.tsx`, `src/app/(landing)/blog/layout.tsx`, `src/app/sitemap.ts`, `src/app/robots.ts`, `src/app/api/robots/route.ts`
- `front/web/src/modules/vitrine/lib/{seo.ts,seo-metadata.ts,structured-data.ts}`, `src/lib/i18n.ts`, `src/lib/backend-url.ts`
- `front/web/src/modules/vitrine/components/forms/SignupForm.tsx`, `src/modules/vitrine/data/{testimonials.ts,blog.ts,pricing.ts}`
- `front/web/src/app/auth/login/page.tsx`, `src/app/auth/logout/page.tsx`
- `front/web/public/sw.js`, `public/manifest.json`, `public/og/` (ou route générée)
- `front/web/vercel.json`, `front/web/src/components/PWAProvider.tsx`, `front/web/src/app/(landing)/{mobile,careers}/page.tsx`

## Contraintes

- Auth : ne pas toucher au cookie `leopardo_token` (httpOnly/Secure) ni au proxy `/api/v1/[...path]` — uniquement l'URL du bouton OAuth.
- Garder `NEXT_PUBLIC_ENABLE_FORMS` / `NEXT_PUBLIC_ENABLE_BLOG` tels quels (flags réels).
- ESLint + TypeScript + build Next verts.
- Les changements de contenu marketing (témoignages) restent minimaux : marquer démo plutôt que réécrire.
