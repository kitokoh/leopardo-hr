# Audit statique — Frontend vitrine Leopardo HR (front/web)

**Date** : 2026-08-15 · **Repo** : /home/user/.workspace/leopardo-hr (HEAD `0285edc7`, Next.js 16.3.0)
**Méthode** : lecture du code + vérification live de `https://gestionemployer-backend.vercel.app` (domaine réellement en ligne, HTTP 200) + DNS `leopardo-rh.com` / `www.leopardo-rh.com` (NXDOMAIN).
**Périmètre** : front/web/src — aucun fichier modifié.
**Légende** : P1 = visible/live et nuisible · P2 = réel, vérifiable dans le code · P3 = cosmétique/mort.

---

## 1. SEO — canonicals, sitemap, robots

### 1.1 [P1] Canonical = homepage sur 6 pages vitrine (vérifié LIVE)
`/employes`, `/documents`, `/comptabilite`, `/marketing`, `/integrations`, `/demo` émettent tous `<link rel="canonical" href="https://gestionemployer-backend.vercel.app">` (homepage). Cause : `pageMetadata.*` n'ont pas de champ `canonical` et `generateMetadata` retombe sur la homepage.
- `src/modules/vitrine/lib/seo.ts:30` — `const url = seo.canonical || siteUrl;`
- `src/app/(landing)/employes/layout.tsx:4` — `export const metadata: Metadata = generateSEOMetadata(pageMetadata.employes);` (idem documents/comptabilite/marketing)
- `src/app/(landing)/integrations/` — aucun layout.tsx → hérite `alternates.canonical: siteUrl` du layout racine
- `src/app/(landing)/demo/layout.tsx:6-14` — metadata inline sans canonical
- Vérifié live : `/employes`, `/documents`, `/comptabilite`, `/marketing`, `/integrations`, `/demo` → canonical homepage ; `/pricing`, `/signup`, `/faq` → canonical correct.
→ Signaux de contenu dupliqué : 6 pages se déclarent être la homepage.

### 1.2 [P1] Sitemap publie `/blog` qui renvoie 404 (vérifié LIVE)
- `src/app/sitemap.ts:65` — `page('/blog', today, 'weekly', 0.7)` inconditionnel (seuls les posts /blog/* sont gatés par le flag, commentaire lignes 76-86)
- `src/app/(landing)/blog/layout.tsx:26` — `notFound()` si `NEXT_PUBLIC_ENABLE_BLOG !== 'true'` (défaut = false, cf. `.env.local.example:41`)
- Vérifié live : `GET /blog` → **404**, mais `/sitemap.xml` contient `<loc>…/blog</loc>` → crawl 404 garanti.

### 1.3 [P2] Source de vérité d'URL contradictoire entre 3 fichiers ; défauts sur des domaines NXDOMAIN
- `src/lib/site.ts:9` — `DEFAULT_SITE_URL = 'https://leopardo-rh.com'` (commentaire ligne 7 : « domaine produit officiel »)
- `src/lib/site-url.ts:20` — `BRAND_SITE_URL = 'https://www.leopardo-rh.com'`
- `.env.local.example:20-22` — « l'URL réellement en ligne aujourd'hui est `gestionemployer-backend.vercel.app` » → `NEXT_PUBLIC_SITE_URL=https://gestionemployer-backend.vercel.app`
- Vérifié : `leopardo-rh.com` et `www.leopardo-rh.com` → NXDOMAIN (curl 000) ; `gestionemployer-backend.vercel.app` → 200.
→ Tout build sans `NEXT_PUBLIC_SITE_URL` (preview/staging/CI) émet canonicals, `metadataBase` (`layout.tsx:31`), OG et sitemap sur des domaines morts ; de plus `sitemap.ts` (via `getSiteUrl` → `leopardo-rh.com`) et `robots.ts:34` (via `SITE_URL` → `www.leopardo-rh.com`) émettent des **domaines morts différents** l'un de l'autre.

### 1.4 [P2] Metadata racine 100 % FR codée en dur pour un site 4 langues
- `src/app/layout.tsx:15-45` — title/description/OG/twitter fixes en français ; les hreflang (`sitemap.ts:16-25`) annoncent en/tr/ar mais le HTML title/description reste FR quelle que soit la langue.

### 1.5 [P3] URL relatives dans des métadonnées
- `src/app/(landing)/demo/layout.tsx:12` — `url: '/demo'` (OG exige une URL absolue)
- `src/app/privacy/page.tsx:9` et `src/app/terms/page.tsx:9` — `alternates.canonical: '/privacy'` / `'/terms'` (relatif, résolu contre `metadataBase`).

---

## 2. Contenu factice / métriques inventées

### 2.1 [P1] « 50K+ utilisateurs », « 99.9% », « 500+ entreprises » affichés alors que le repo admet 0 client payant
- `src/app/(landing)/about/page.tsx:70-71` — `{ value: '50K+', label: 'Utilisateurs Actifs' }`, `{ value: '99.9%', label: 'Précision' }`
- `src/app/(landing)/testimonials/page.tsx:72-73,165` — `'500+' Entreprises clientes`, `'50 000+' Employés gérés`, « Rejoignez nos 500+ clients satisfaits »
- `src/app/(landing)/case-studies/page.tsx:150` — `"Rejoignez 500+ entreprises qui ont choisi Leopardo RH"`
- Contre-preuve dans le repo : `PILOTAGE.md:177` — `| Clients payants | 0 | 3-5 | 20-30 | 100-150 |` et le commentaire de `src/modules/vitrine/components/sections/SocialProofMetrics.tsx:19-22` déclare explicitement que « 500+ active companies », « 50K+ employees managed », « 99.9% SLA uptime » — « none of which are true » (section remplacée pour cette raison… mais l'About/Testimonials/Case-studies affichent toujours ces chiffres).
- `src/modules/vitrine/data/features.ts:31` — `stats: '50K+'` / `statsLabel: 'Utilisateurs'` ; `features.ts:67,123…` — `stats: '4.9'` / `statsLabel: 'App store'` alors que les apps ne sont pas sur les stores (`src/modules/vitrine/lib/mobile-download.ts:40-48` : fallback Firebase App Distribution / signup) → note « 4.9 App store » inventée.

### 2.2 [P2] Études de cas et témoignages « clients » fictifs présentés sans mention démo
- `src/app/(landing)/case-studies/page.tsx:10-52` — « TechCorp Algérie », « Atlas Industries », « LogiTrans Express » avec chiffres précis (`-80%`, `-95%`, `-60%`…) et citations signées de dirigeants ; aucune mention « démo/illustratif ».
- `src/modules/vitrine/lib/content.ts:64-100` — mêmes cas fictifs (`Satisfaction 98%`, `Fraude réduite 95%`…) injectés sur les pages modules.
- NB : le repo sait faire mieux ailleurs (`data/testimonials.ts:5` — `TESTIMONIALS_ARE_DEMO = true`).

### 2.3 [P2] « 6 pays » (FAQ + metrics) vs « 5 pays » (demo) vs 19+ codes réels côté backend
- `src/app/(landing)/faq/page.tsx:44` — « supporte la paie pour 6 pays (France, Algerie, Turquie, Senegal, Maroc, Tunisie) »
- `src/app/(landing)/demo/page.tsx:74,113,152` — « Modeles DZ, MA, TN, FR et TR » (5 pays, sans le Sénégal)
- `src/modules/vitrine/components/sections/SocialProofMetrics.tsx:33` — « 6 Pays avec regles de paie dediees »
- Réel backend : `api/app/Modules/Payroll/Infrastructure/Services/CountryRulesResolver.php:65-70,89` (DZ, MA, TN, FR, TR, SN, CA) + `CemacPayrollRules.php:30` (CM, CF, TD, CG, GA, GQ) + `CedeaoPayrollRules.php:38` (CI, ML, BF, BJ, TG, NE) → 19 codes. Incohérence interne + sous-évaluation factuelle.

### 2.4 [P3] Contenu daté 2024 en 2026
- `src/app/(landing)/guides/checklist-paie/page.tsx:13,18,32,38,93-95` — « Checklist Paie 2024 », « Conformité 2024 » ; `layout.tsx:4-9` (« Checklist Paie 2024 »)
- `src/modules/vitrine/data/blog.ts:72,147-149…` — posts datés 2023-11 → 2024-01 (« Tendances RH à surveiller en **2024** ») ; blog désactivé par flag par défaut.

---

## 3. Pricing — incohérences

### 3.1 [P2] Enterprise : « Sur devis » (pricing) vs 299 €/mois (checkout) vs 0 € (API sandbox)
- `src/modules/vitrine/data/pricing.ts:86-87` — Enterprise `price: 'Sur devis'`
- `src/app/(landing)/checkout/page.tsx:98-99` — `enterprise: { priceMonthly: 299, priceAnnual: 239, savings: 720 }`
- `src/app/api/billing/checkout/route.ts:37` — `enterprise: { monthly: 0, annual: 0 }` (SANDBOX_PRICES)
- `src/modules/vitrine/lib/seo-metadata.ts` (pricingMetadata) : « Enterprise sur devis » — 3 représentations contradictoires selon le chemin d'accès.

### 3.2 [P2] Surcharge par employé affichée sur les cartes pricing, invisible au checkout
- `src/modules/vitrine/data/pricing.ts:27-28,36-37` — Pilot « 29/mois + 2 EUR/employe actif », Operations « 99/mois + 4 EUR/employe actif »
- `src/app/(landing)/checkout/page.tsx:54-98` — PLAN_CONFIG ne contient que des prix plats (29/24, 99/79) ; `PlanSummaryCard` (l. 186-215) affiche « EUR 29 /mois » sans aucune mention du surcoût/employé → le montant au checkout sous-estime la facture réelle décrite par la FAQ (`pricing/page.tsx:160`).

### 3.3 [P2] Checkout et badges pricing codés en dur en FR
- `src/app/(landing)/pricing/page.tsx:859,867,905,1025,1030` — « Le plus populaire », « 100% Gratuit », « Sans carte bancaire · Pour toujours », « ★ top », « gratuit » (non localisés malgré copy fr/en/tr/ar complète)
- `src/app/(landing)/checkout/page.tsx:1138-1140` — stepLabels « Récapitulatif / Créer mon compte / Paiement » ; l. 948, 988 « Informations de paiement », « Numéro de carte » ; l. 315 « Paiement sécurisé TLS 1.3… » ; `checkout/success/page.tsx:178,212` (« 14 jours offerts… ») — tunnel entier FR-only pour un produit multilingue.

### 3.4 [P3] « Économisez 20% » faux pour Pilot ; limite employés Enterprise divergente
- `src/modules/vitrine/data/pricing.ts:25-26` — 29 → 24 = **17,2 %** de remise, pas 20 % (affiché `pricing/page.tsx:82` et `components/PricingSection.tsx:49-52` « Economisez 20% »)
- `src/modules/vitrine/data/pricing.ts:91` — Enterprise « 500+ employes » vs `src/app/(landing)/checkout/page.tsx:110` — « 250+ employés ».

### 3.5 [P3] Checkout payant indisponible sans clé Stripe (état prod à risque)
- `src/app/api/billing/checkout/route.ts:66-76` — sans `STRIPE_SECRET_KEY` live → `503 CHECKOUT_UNAVAILABLE` (message FR « Le paiement en ligne est temporairement indisponible… »). Le CTA Operations (`pricing/page.tsx:649` → `/checkout?plan=business`) aboutit donc à une erreur tant que Stripe n'est pas configuré ; le fallback sandbox (`NEXT_PUBLIC_CHECKOUT_SANDBOX`, route.ts:20-29) est correctement verrouillé hors prod.

---

## 4. Liens morts / paramètres ignorés

### 4.1 [P2] `/contact?topic=enterprise` et `/contact?type=enterprise` : paramètre non reconnu
- `src/app/(landing)/contact/page.tsx:29-38` — `TOPIC_TO_SUBJECT` ne contient pas `enterprise` (clés : password, upgrade, support, community, download-*, download)
- `src/app/(landing)/pricing/page.tsx:647,720,1248` — CTA Enterprise → `/contact?topic=enterprise` (le select « Sujet » reste sur la valeur par défaut)
- `src/modules/vitrine/components/PricingSection.tsx:15` — CTA Enterprise home → `/contact?type=enterprise` (mauvais nom de param, jamais lu par la page contact qui ne lit que `topic`, l. 89).

### 4.2 [P2] Bug visible : « {copy.info.responseTime} » littéral affiché sur /contact (FR)
- `src/app/(landing)/contact/page.tsx:47` — `responseTime: '{copy.info.responseTime}'` (placeholder non interpolé, unique à la locale fr)
- `src/app/(landing)/contact/page.tsx:161` — `{copy.info.responseTime}` rendu → le texte littéral s'affiche côté FR.

### 4.3 [P3] Entrées mortes dans la map du Footer
- `src/modules/vitrine/components/Footer.tsx:41-46` — routes `0-6` (/about) et `0-7` (/videos) inatteignables : chaque section ne fournit que 6 liens (`vitrine-locale.ts` footer.sections), la map va jusqu'à `0-7`.
- Liens externes vérifiés OK : `linkedin.com/company/leopardo` (200), `github.com/kitokoh/leopardo-hr`, liens Firebase App Distribution (200), `gestionemployerbackend.onrender.com` (répond).

---

## 5. Onboarding / Signup / Checkout

### 5.1 [P2] Tunnel signup/checkout FR-only et incohérence de parcours payant
- Voir 3.3 : SignupForm utilise bien les catalogues i18n (`signup.*` complets dans les 4 locales, vérifié), mais le checkout entier et les écrans OTP/paiement associés sont FR codé en dur.
- Vérifié positif : essai « 14 jours » cohérent entre la vitrine (`pricing.ts` priceNote, `checkout/page.tsx` trialDays 14) et le backend (`api/…/ProvisionGuidedTrial.php:56,123` → `addDays(14)`, `trial_days => 14`). Aucun écart trouvé.

### 5.2 [P3] Service worker : précache de routes dashboard authentifiées → install cassée
- `public/sw.js:10-16` — `PRECACHE_ASSETS` inclut `/dashboard`, `/attendance`, `/absences`, `/employees`
- `src/middleware.ts:33-38` — ces routes sont protégées (redirect 307 vers /auth/login sans cookie `leopardo_token`) → `cache.addAll` (sw.js:25) reçoit des 307 pour un visiteur anonyme → l'`install` échoue (le SW ne s'installe jamais, offline PWA mort).
- `public/sw.js:85` — écoute le tag `leopardo-sync`, jamais enregistré : `src/components/PWAProvider.tsx:139-143` enregistre `sync-forms`/`sync-analytics` → handler sync mort.

---

## 6. Code mort (vérifié : 0 référence externe hors définitions/tests)

- **`src/modules/vitrine/lib/seo-metadata.ts`** (331 lignes : landingMetadata, pricingMetadata…) — aucun import dans src.
- **`src/lib/caching-config.ts`** — aucun import.
- **`src/modules/vitrine/components/sections/PricingCard.tsx`** + **`PricingSection.tsx`** — importés uniquement par leurs tests ; la page d'accueil utilise l'autre `components/PricingSection.tsx` (locale-aware).
- **`src/modules/vitrine/components/animations/GradientOrbs.tsx`, `ScrollAnimations.tsx`, `AnimatedCounter.tsx`** — non importés (HeroSection définit son propre `AnimatedCounter` inline, `HeroSection.tsx:12`).
- **`src/modules/vitrine/types/index.ts`** — aucun import.
- **`src/modules/vitrine/hooks/useScrollAnimation.ts`, `useFormSubmit.ts`** — aucun import ; `hooks/useIntersectionObserver.ts` n'est utilisé que par AnimatedCounter (mort).

---

## 7. i18n — pages hors catalogue (7 pages publiques 100 % FR codé en dur)

- **0 référence locale** dans : `src/app/(landing)/about/page.tsx`, `case-studies/page.tsx`, `case-studies/[slug]/page.tsx`, `testimonials/page.tsx`, `videos/page.tsx`, `branding/page.tsx`, `faq/page.tsx` (vérifié par grep `locale|useVitrineLocale` = 0), + `checkout/*` (voir 3.3).
- Les catalogues JSON `src/lib/i18n/locales/{fr,en,tr,ar}.json` sont complets (556 clés, aucune clé manquante, traductions réelles vérifiées) → le problème est l'architecture : la copie vitrine vit en dur dans `src/modules/vitrine/lib/vitrine-locale.ts` (landingCopy TS, ~700 lignes) hors des catalogues partagés.
- Dérive de copy entre locales : `vitrine-locale.ts:170/314` badge « OS mobile-first… » vs `:458` (tr) « Leo IA 2.0 hazir » / `:602` (ar) « Leo IA 2.0 متاح الان » — messages différents selon la langue.
- Fallback FR silencieux : `locale-catalog.ts:33-35` (t() retombe sur fr), `i18n.ts:1718-1731` (défaut fr) — fonctionnel mais masque les trous.

---

## Top constats (résumé compact, les plus solides)

1. **[P1]** Canonical = homepage sur /employes, /documents, /comptabilite, /marketing, /integrations, /demo — `seo.ts:30` + `pageMetadata` sans canonical ; **vérifié live**.
2. **[P1]** Sitemap publie /blog → 404 live (`sitemap.ts:65` vs `blog/layout.tsx:26`, flag off) ; **vérifié live**.
3. **[P1]** Métriques inventées : 50K+ utilisateurs, 99.9%, 500+ entreprises/clients — `about/page.tsx:70-71`, `testimonials/page.tsx:72-73,165`, `case-studies/page.tsx:150`, `features.ts:31,67` vs `PILOTAGE.md:177` (0 client payant) et `SocialProofMetrics.tsx:19-22` (« none of which are true »).
4. **[P2]** Défauts d'URL contradictoires : `site.ts:9` (leopardo-rh.com) vs `site-url.ts:20` (www.leopardo-rh.com) vs `.env.local.example:22` (gestionemployer-backend.vercel.app) ; les 2 premiers NXDOMAIN vérifiés.
5. **[P2]** Pays : FAQ « 6 pays » (`faq/page.tsx:44`) vs demo « 5 pays » (`demo/page.tsx:74`) vs 19 codes réels (`CountryRulesResolver.php:65-89`).
6. **[P2]** Enterprise : « Sur devis » (`pricing.ts:86`) vs 299 € (`checkout/page.tsx:98`) vs 0 € sandbox (`checkout/route.ts:37`).
7. **[P2]** Surcharge/employé (+2/+4 €) affichée sur les cartes (`pricing.ts:27,36`) mais absente du checkout (`checkout/page.tsx:54-98`).
8. **[P2]** 7 pages publiques FR-only hors i18n (about, case-studies, testimonials, videos, branding, faq, checkout).
9. **[P2]** Bug visible /contact FR : placeholder littéral « {copy.info.responseTime} » (`contact/page.tsx:47,161`).
10. **[P2]** `?topic=enterprise` / `?type=enterprise` ignorés par la page contact (`contact/page.tsx:29-38`, `PricingSection.tsx:15`).
11. **[P2]** SW précache des routes auth-gated 307 → install PWA cassée (`sw.js:10-16` vs `middleware.ts:33-38`) + tag sync mort (`sw.js:85` vs `PWAProvider.tsx:139-143`).
12. **[P2]** Checkout FR-only + badges pricing non localisés (« Le plus populaire », « 100% Gratuit »…) — `pricing/page.tsx:859,867,905` ; `checkout/page.tsx:1138-1140`.
13. **[P2]** Études de cas fictives (TechCorp Algérie, Atlas Industries…) présentées sans mention démo — `case-studies/page.tsx:10-52`, `content.ts:64-100`.
14. **[P3]** Code mort : seo-metadata.ts, caching-config.ts, sections/PricingCard+PricingSection, animations/GradientOrbs+ScrollAnimations, vitrine/types (0 imports).
15. **[P3]** « Checklist Paie 2024 » en 2026 (`guides/checklist-paie/page.tsx:13,18`) ; « Économisez 20% » = 17,2 % réel pour Pilot (`pricing.ts:25-26`) ; limite Enterprise 500+ vs 250+ (`pricing.ts:91` vs `checkout/page.tsx:110`).

**Vérifié OK (non signalé)** : essai 14 jours cohérent front/back ; catalogues i18n JSON complets et réellement traduits ; liens externes (LinkedIn, GitHub, Firebase, Render) vivants ; robots.txt live → sitemap sur le bon domaine (grâce à NEXT_PUBLIC_SITE_URL posé en prod).
