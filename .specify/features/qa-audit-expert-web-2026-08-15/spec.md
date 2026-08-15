# Feature Specification: Audit expert Web & Vitrine — 2026-08-15

**Feature Branch**: `qa-audit-expert-web-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test expert de la plateforme (session 2026-08-15) — audit de la vitrine et du web Next.js (`front/web`) : parcours d'achat, onboarding, SEO, liens, i18n, PWA, cohérence. Base : `main` (`8a57dbf8`).

## User Stories & Testing

### User Story C1 — Le parcours d'achat est honnête : pas de faux paiement (Priority: P1)

La page `/checkout` collecte numéro de carte/expiration/CVC dans un formulaire **qui ne les envoie nulle part** (le schéma les ignore) ; sans `STRIPE_SECRET_KEY` (prod), `POST /api/billing/checkout` renvoie un **succès sandbox simulé** (`route.ts:55`) et la page de succès promet un « email de confirmation avec vos identifiants » qui **n'est jamais envoyé** (l'appel sandbox à `/api/v1/trial/signup` saute `requestedWorkflow=guided_trial` → OTP jamais vérifié → aucun compte). Plans payants **non achetables**, utilisateurs laissés dans un faux espoir. L'UI admet « Les Stripe Price IDs ne sont pas encore configurés » (`page.tsx:1011`).

**Pourquoi P1** : un tunnel de paiement qui simule le succès sur la vitrine publique est trompeur et casse l'onboarding payant.

**Test indépendant** : sans `STRIPE_SECRET_KEY`, aucun chemin ne prétend qu'un paiement a abouti ; le formulaire de carte est masqué ou explicitement « démo » ; aucune promesse d'email non envoyé ; le plan gratuit reste pleinement fonctionnel.

**Acceptance Scenarios**:
1. **Given** un environnement sans clé Stripe, **When** on ouvre `/checkout`, **Then** aucun champ carte n'est présent (ou un bandeau « démo, aucun paiement réel » s'affiche) et le paiement ne peut pas « réussir ».
2. **Given** un plan payant sélectionné sans Stripe, **When** on soumet, **Then** un état explicite « paiement non disponible » s'affiche (pas de succès simulé, pas de promesse d'email).
3. **Given** le plan gratuit, **When** on s'inscrit, **Then** le parcours OTP complet (`trial/signup` + verify) aboutit à un compte réel.

### User Story C2 — Le site ne ment pas sur son identité ni ses données (Priority: P2)

- Canonical du blog codé sur `https://gestionemployer-backend.vercel.app/blog` — un domaine étranger (le code lui-même le qualifie d'« entreprise de construction US sans rapport », `JsonLd.tsx:5`).
- Fallbacks `siteUrl` incohérents : 4 fichiers retombent sur `gestionemployer-backend.vercel.app`, `seo.ts` sur `http://localhost:3000`.
- `html lang="fr"` codé en dur (SSR) alors que 4 locales existent — les pages en/tr/ar sont déclarées `lang="fr"` sans `dir` (SEO/ATV).
- Statistique « Live: 18 » codée en dur dans le header du dashboard (`layout.tsx:361`).
- Témoignages **fabriqués** (Amina Diallo/TechAfrika, Mehdi Benali/Atlas Digital, tous 5 étoiles) — le code admet « no real customer photos exist yet ».
- `generateProductSchema` fabrique un `aggregateRating 4.9/500`.

**Test indépendant** : aucune occurrence de `gestionemployer-backend.vercel.app` dans `src/` ; `lang`/`dir` corrects par locale au SSR ; pas de chiffre statique « Live » ; témoignages marqués démo ou retirés.

**Acceptance Scenarios**:
1. **Given** le blog, **When** on inspecte le canonical, **Then** il dérive de `NEXT_PUBLIC_SITE_URL`.
2. **Given** une page en arabe, **When** le SSR la sert, **Then** `lang="ar" dir="rtl"`.
3. **Given** le dashboard, **When** il charge, **Then** aucune statistique codée en dur.
4. **Given** la section témoignages, **When** elle s'affiche, **Then** les citations sont réelles/attribuées ou la section est marquée démo.

### User Story C3 — Les ressources SEO et PWA existent (Priority: P2)

- `ogImage` pointe vers `/og/<page>.png` pour chaque page — dossier `public/og/` **inexistant** → toutes les OG images 404.
- Service worker (`public/sw.js`) précache `/dashboard/attendance`, `/dashboard/absences`, `/dashboard/employees` — routes **inexistantes** (les vraies sont `/attendance`, `/absences`, `/employees`) → `cache.addAll` rejette → installation SW cassée (PWA offline morte).
- Manifest : « Essai gratuit de 14 jours » (vs 30 jours partout) + `/icon-192.png` inexistant.
- Route orpheline `/api/robots` pointant vers `/api/sitemap` (inexistant — seul `/sitemap.xml` existe).

**Test indépendant** : `ls public/og/` non vide ou `ogImage` régénéré ; installation SW sans erreur (`cache.addAll` résolu) ; manifest cohérent (30 jours, icône existante).

**Acceptance Scenarios**:
1. **Given** n'importe quelle page, **When** un crawler la partage, **Then** l'OG image retourne 200.
2. **Given** la PWA, **When** on l'installe, **Then** le précache inclut des routes réelles (installation réussie).
3. **Given** le manifest, **When** on l'inspecte, **Then** la description de l'essai dit 30 jours et l'icône existe.

### User Story C4 — Le checkout ne contourne plus le proxy OAuth (Priority: P2)

`checkout/page.tsx:190-196` : le bouton Google OAuth utilise `getApiBaseUrl()` → origine API directe (`/api/v1/auth/google`) au lieu du proxy same-origin `/api/v1/auth/google` utilisé par `login/page.tsx:170` — régression du fix QA #2277 (cookie de session posé sur la vitrine, origine directe = cookie perdu).

**Test indépendant** : l'URL du bouton Google du checkout pointe vers le proxy same-origin (même pattern que login).

**Acceptance Scenarios**:
1. **Given** le checkout, **When** on clique « Continuer avec Google », **Then** la requête passe par le proxy `/api/v1/auth/google` de la vitrine.

### User Story C5 — Les textes sont localisés (Priority: P3)

- Wizard d'inscription (`SignupForm.tsx`) 100 % français codé en dur sur une page localisée (4 locales).
- Tunnel checkout + page logout en français uniquement ; le POST d'inscription gratuite code `locale: 'fr'` (`page.tsx:708`).
- Bannière post-inscription du login codée en français (`login/page.tsx:385-390`).
- Page carrières : 5 postes codés en dur, français uniquement, sans parcours de candidature.
- Fautes d'arabe dans les chaînes livrées (« مساد المطور », « الردود الويب », « إلعاء », « الجمارافي »).

**Test indépendant** : `rg` — plus de chaînes françaises codées en dur dans les composants localisés ; les chaînes passent par le catalogue i18n.

**Acceptance Scenarios**:
1. **Given** la page d'inscription en anglais, **When** le wizard s'affiche, **Then** les labels sont en anglais.
2. **Given** la page checkout, **When** on change de locale, **Then** les textes suivent la locale.

### User Story C6 — Pas de fallback trompeur ni de contenu périmé (Priority: P3)

- Modale démo du login : fallback sur des comptes codés en dur (`password123`) quand `/demo-users` 404 (gaté `DEMO_MODE_ENABLED` en API) → en prod le bouton démo propose des comptes inutilisables.
- Blog : 11 posts datés 2023-12/2024-01 toujours en tête du sitemap en 2026 (« Les Tendances RH à Surveiller en 2024 »).
- `vercel.json` : redirect `/old-page` → `/new-page` (route inexistante → 404) + CSP-RO dupliqué avec `next.config.ts`.
- Section apps mobiles : placeholders « Bientôt disponible » + fallback `/download` → `/signup`.

**Test indépendant** : le bouton démo n'apparaît que si l'API renvoie réellement des utilisateurs ; aucune URL morte dans `vercel.json` ; dates du blog ≤ 2025-12 ou contenu rafraîchi.

**Acceptance Scenarios**:
1. **Given** le login, **When** l'API démo est désactivée, **Then** aucune proposition de compte démo.
2. **Given** le sitemap, **When** on l'inspecte, **Then** aucun contenu obsolète en tête.

## Edge Cases

- Checkout : en présence d'une clé Stripe test (`sk_test_`), le mode sandbox reste actif — le bandeau « démo » doit l'être aussi.
- `siteUrl` : un seul résolveur partagé ; défaut de build sûr (échouer si absent en prod).
- SW : retirer `/dashboard*` du précache (hors ligne = page `/offline` + routes publiques).
- OG images : préférer la route générée `/opengraph-image` (aucun fichier statique à maintenir).
