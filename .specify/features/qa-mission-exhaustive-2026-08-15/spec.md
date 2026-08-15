# Feature Specification: Mission QA Exhaustive 2026-08-15

**Feature Branch**: `qa-mission-exhaustive-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission du propriétaire (session 2026-08-15) — tester la plateforme dans tous les sens (vitrine, web, admin, mobile, workflows, APIs, logiques, onboarding, cohérence) sur la prod live (`gestionemployerbackend.onrender.com` v4.23.5, `leo-admin.pages.dev`, `gestionemployer-backend.vercel.app`) + audit statique `main` (`80c034ff`) + stack locale (API Laravel seedée, builds Next/Vue, kiosk, apps Flutter).

## Contexte

Audit complet demandé par le propriétaire : tout manquement constaté doit être rédigé en spec/tâches/incidents (méthode Spec Kit) puis implémenté en fin de session. Constat principal : **la production live est partiellement cassée** (CORS admin, login API 500, onboarding trial 500, queue sync) et **le déploiement prod est en retard sur `main`** (plusieurs fixes mergés le 2026-08-15 matin non live à 09h). La vitrine présente des incohérences de funnel pricing et un blog 404 malgré un sitemap qui le référence.

## User Scenarios & Testing

### User Story 1 — La production live redevient utilisable de bout en bout (Priority: P1)

Un visiteur/lead/utilisateur réel de la plateforme peut se connecter et utiliser chaque surface déployée : le cockpit super-admin (`leo-admin.pages.dev`) charge ses données, un manager/employé démo se connecte à l'espace client web et à l'API sans erreur serveur, et le parcours d'essai guidé aboutit.

**Pourquoi P1** : 3 surfaces publiques documentées (`docs/DEMO_ACCOUNTS.md`, `docs/GUIDES/GUIDE_TESTEURS_PILOTES.md`) sont inutilisables en l'état — c'est la vitrine commerciale du produit.

**Independent Test** : smoke curl + navigateur sur les 3 surfaces live : `POST /api/v1/auth/login` (compte démo) → 2xx avec token ; préflight CORS depuis `https://leo-admin.pages.dev` → en-têtes `access-control-allow-origin` présents ; login `admin@leopardo-rh.com` sur l'admin → redirection dashboard ; `POST /api/v1/trial/verify` → réponse JSON structurée (jamais `{"message":"Server Error"}`).

**Acceptance Scenarios**:

1. **Given** un compte employé/manager démo existant, **When** `POST /api/v1/auth/login` est appelé, **Then** la réponse est 2xx avec token (ou 401/423 explicite avec un message clair si le compte/tenant est invalide) — jamais un 500 `Server Error`.
2. **Given** le navigateur sur `leo-admin.pages.dev`, **When** le dashboard appelle l'API, **Then** les requêtes CORS aboutissent (préflight 204 avec `access-control-allow-origin: https://leo-admin.pages.dev`).
3. **Given** un prospect sur `/signup`, **When** il complète le formulaire et le code OTP, **Then** le flux aboutit à la création du trial ou à un message d'erreur explicite (pas de "Server Error" brut).
4. **Given** la prod, **When** un job lourd est dispatché (provisioning trial, email), **Then** il ne bloque pas la requête HTTP (queue async réelle ou fallback documenté).

### User Story 2 — Le blog vitrine est cohérent avec le sitemap et les liens (Priority: P1)

Un visiteur qui clique « Explore the blog » ou une URL du sitemap arrive sur une page de blog fonctionnelle (ou, si le blog est volontairement désactivé, aucun lien ni URL de sitemap n'y mène).

**Pourquoi P1** : 30+ URLs du sitemap en 404 + liens morts sur la home = pénalisant SEO et image produit.

**Independent Test** : `GET /blog` et `GET /blog/<slug>` → 200 ; `sitemap.xml` ne contient aucune URL `/blog/*` si le flag est off (ou toutes 200 si on) ; aucune occurrence de `/blog` non gatée dans les composants de la home.

**Acceptance Scenarios**:

1. **Given** le flag `NEXT_PUBLIC_ENABLE_BLOG=true` en prod, **When** on visite `/blog` et `/blog/<slug>`, **Then** les pages rendent 200 avec les articles (`src/modules/vitrine/data/blog.ts`).
2. **Given** le flag off, **When** on génère le sitemap, **Then** aucune URL `/blog/*` n'y figure (correction de `src/app/sitemap.ts` qui lit `enableBlog` sans l'utiliser), et la section MarketingReadiness de la home ne propose plus « Explorer le blog ».
3. **Given** n'importe quel état, **When** on vérifie les composants, **Then** Navbar, Footer, MarketingReadinessSection et sitemap utilisent la même source de vérité du flag.

### User Story 3 — Le funnel pricing est honnête et cohérent (Priority: P1)

Un visiteur qui clique « Start for free » (plan Free) n'atterrit pas sur un écran de paiement d'un plan payant ; les noms de plans et prix affichés sont cohérents entre la home, la page pricing, le checkout, le backend et les docs.

**Pourquoi P1** : un CTA « gratuit » qui mène à un paywall (24 €/mois) est un défaut commercial critique et une incohérence produit majeure.

**Independent Test** : crawl des CTA (`/checkout?plan=*`) depuis home et pricing : plan Free → `plan=free` (0 €) ; plan Pilot → `plan=pilot` ; Operations → `plan=operations` ; Enterprise → `plan=enterprise` ou contact ; labels identiques sur toutes les surfaces ; meta description pricing à jour.

**Acceptance Scenarios**:

1. **Given** la home, **When** on clique « Start for free » dans la section pricing, **Then** on arrive sur le checkout du plan Free (0 €), pas du plan Pilot.
2. **Given** `PLAN_CONFIG` du checkout, **When** on inspecte les clés, **Then** chaque plan a une seule clé canonique (`free`, `pilot`, `operations`, `enterprise`) — plus de doublons `starter`/`business` masqués ; le label Enterprise est « Enterprise » (pas « Scale »).
3. **Given** la page pricing, **When** on inspecte le SEO, **Then** la meta description reflète les plans affichés (Pilot/Operations) et une durée d'essai unique (30 jours) cohérente avec le hero et la FAQ.

### User Story 4 — Le contrat API (OpenAPI) est à jour (Priority: P2)

Un intégrateur qui suit `api/openapi.yaml` trouve chaque endpoint réel du backend documenté.

**Pourquoi P2** : la doc API publique est une surface produit pour intégrateurs/partenaires ; 176 verbes-routes manquants (dont `/trial/*`, `/health/live`, `/health/ready`, `/payrolls`, `/user/*`, `/platform/*`, `/edge/*`) contredisent la revendication « drift à ZÉRO » du commit `ce611dd3` (#2490).

**Independent Test** : `python3 scripts/route_openapi_compare.py` → `=== In PHP routes but MISSING in OpenAPI (0) ===` et `=== In OpenAPI but MISSING in PHP routes (0) ===`.

**Acceptance Scenarios**:

1. **Given** le backend (`php artisan route:list`), **When** on compare à `openapi.yaml`, **Then** 0 route manquante et 0 fantôme via l'outil maison.
2. **Given** les routes ajoutées, **When** on vérifie, **Then** chaque route a son bloc OpenAPI complet (params, réponses, security).

### User Story 5 — Le login API est résilient face aux données orphelines (Priority: P2)

Un compte dont le tenant/schéma est absent ou corrompu (état possible en prod avec seed partiel) ne provoque jamais un 500 : l'utilisateur reçoit une erreur métier explicite.

**Pourquoi P2** : la prod contient des comptes démo dont le schéma tenant est absent → 500 non géré sur le parcours de connexion principal.

**Independent Test** : test de régression avec un `user_lookups` pointant vers un schéma inexistant → réponse 401/423 avec message clair, jamais 500 ; la suite AuthServiceTest reste verte.

**Acceptance Scenarios**:

1. **Given** un `user_lookups` avec `schema_name` inexistant, **When** login, **Then** réponse 401 `INVALID_CREDENTIALS` (ou 423 `ACCOUNT_UNAVAILABLE`) avec message explicite, sans stack trace.
2. **Given** un compte valide, **When** login, **Then** comportement inchangé (2xx token).

### User Story 6 — La dette de qualité est tracée et les gates sont mesurables (Priority: P3)

Les métriques de qualité locales reflètent la réalité : formatage Pint, warnings PHPUnit, état de la suite de tests.

**Pourquoi P3** : la dette de formatage (686 fichiers) et les warnings PHPUnit 12 créent du churn sur les PRs et masquent les vrais problèmes.

**Independent Test** : `php vendor/bin/pint --test` → nombre de fichiers à formater < seuil défini ; `php artisan test` → suite verte (ou échecs documentés avec issues).

**Acceptance Scenarios**:

1. **Given** le repo, **When** `pint --test` est lancé, **Then** le rapport liste les fichiers restants (tâche de nettoyage tracée, pas silencieuse).
2. **Given** la suite de tests, **When** elle tourne en séquentiel local, **Then** elle est verte ou chaque échec est documenté (issue) — pas d'échecs silencieux dus à l'ordre.

### User Story 7 — Surfaces mobiles et kiosk : cohérence et états d'erreur honnêtes (Priority: P3)

Les apps mobiles et le kiosk n'exposent pas de parcours cassés ni d'états d'erreur bruts : les écrans d'auth legacy (`/user-*`) sont cohérents avec l'auth principale, et le kiosk sans configuration affiche un état explicite.

**Pourquoi P3** : confiance des testeurs terrain ; le kiosk affiche un « Error 404 » brut sans config.

**Independent Test** : audit statique des routes publiques mobiles + kiosk sans config.json → message « configuration requise » au lieu d'une erreur brute.

**Acceptance Scenarios**:

1. **Given** le kiosk sans `config.json`, **When** il se charge, **Then** un état « borne non configurée » explicite s'affiche (pas de `Error 404`).
2. **Given** les apps mobiles, **When** un écran `user_login`/`user_register` est ouvert, **Then** il mène vers le parcours d'auth canonique (`/auth/login`) ou fonctionne de bout en bout — pas de doublon divergent.

---

## Success Criteria

1. **Prod live** : 100 % des smoke tests US1 passent sur les 3 surfaces (API, admin, web) — mesuré par un script de smoke rejouable (`scripts/qa_api_smoke.py` étendu).
2. **Blog** : 0 URL `/blog/*` en 404 référencée par le sitemap ou la home (choix flag documenté).
3. **Funnel** : 0 CTA « gratuit » menant à un plan payant ; 0 doublon de clé plan dans le checkout.
4. **Contrat** : 0 dérive route↔openapi via l'outil maison.
5. **Qualité** : `pint --test` rapporté (dette tracée), suite de tests verte ou échecs documentés.

## Assumptions

- Le blog a du contenu prêt (`src/content/blog/*.md` + données `blog.ts`) : le fix recommandé est d'**activer** le flag en prod plutôt que de supprimer le contenu, mais le choix produit est laissé ouvert dans la tâche.
- Les comptes démo ne doivent pas être seedés en prod sans décision explicite (sécurité) : le fix du login 500 est la résilience + l'alignement de la doc, pas le re-seeding.
- La correction CORS passe par un redéploiement prod (le fix #2333 est déjà dans main) : la tâche vérifie le pipeline de déploiement et ajoute un garde-fou de vérification CORS post-deploy.

## Key Entities

- `front/web/src/app/(landing)/blog/layout.tsx` (flag), `src/app/sitemap.ts`, `src/modules/vitrine/components/sections/MarketingReadinessSection.tsx`, `Navbar.tsx`, `Footer.tsx`
- `front/web/src/modules/vitrine/components/PricingSection.tsx` (`getPlanCtaHref`), `src/app/(landing)/checkout/page.tsx` (`PLAN_CONFIG`), `src/modules/vitrine/data/pricing.ts`, `src/app/(landing)/pricing/page.tsx`
- `api/config/cors.php`, `api/app/Core/Auth/Infrastructure/Services/AuthService.php`, `api/app/Modules/Billing/Interfaces/Api/V1/SelfServiceTrialController.php`
- `api/openapi.yaml`, `scripts/route_openapi_compare.py`
- `front/zkteco-kiosk/app.js`, `front/admin-dashboard/src/views/auth/LoginView.vue`
