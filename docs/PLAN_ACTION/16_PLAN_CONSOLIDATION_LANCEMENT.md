# 16 - Plan de consolidation lancement

Derniere mise a jour : 2026-05-19

Objectif : transformer la base technique deja livree en plateforme prete pour acquisition marketing, premiers pics de trafic et onboarding client sans friction.

## Etat actuel

- Backend API : socle tres avance, multi-tenant, tests Feature et CI solides. Priorite restante : surveiller les contrats reels entre frontends et API a chaque nouvelle surface.
- Admin dashboard : fonctionnel, riche et plus accessible. Priorite restante : eviter les liens relatifs vers l'API, standardiser les exports et polir les parcours commerciaux visibles.
- Web vitrine : SEO, blog, pricing, demo et pages GTM presents. Priorite restante : contenu multilingue marketing et preuves sociales reelles.
- Mobile : modules RH principaux branches API. Priorite restante : qualite offline, deep links, push production et tests devices.
- Kiosk : pointage, annonces, QR et ressources employe branches. Priorite restante : runbook installation client + tests terrain avec ZKTeco reel.

## Lot 16.1 - Contrats API/frontends

Priorite : critique avant campagne marketing.

- [x] Normaliser `VITE_API_URL` admin autour de `/api/v1`.
- [x] Remplacer les exports admin relatifs par des telechargements Axios authentifies.
- [x] Ajouter les endpoints backend manquants pour les cartes d'exports admin.
- [x] Normaliser l'URL kiosk avec ou sans `/api/v1`.
- [x] Ajouter un test contractuel qui extrait les endpoints utilises par admin/mobile/kiosk et verifie leur presence dans les routes Laravel ou OpenAPI.
- [x] Publier une matrice "frontend screen -> endpoint -> role -> test" dans `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`.

## Lot 16.2 - Release readiness factuelle

Priorite : critique.

- [x] Executer le gate `dev-hub/tools/release-readiness.ps1` — 15/15 PASS.
- [x] Produire `docs/validation/RELEASE_READINESS_REPORT_2026-05-19.md` — score 91/100.
- [x] Lister les checks GitHub Actions verts du dernier `main` — 18 workflows documentes.
- [x] Lister les secrets/variables cloud obligatoires : Render, Cloudflare Pages, Vercel, Firebase, Sentry, Slack, backup — inventaire complet dans le rapport.
- [x] Verifier les URLs publiques : API Render, admin Cloudflare Pages, vitrine Vercel, health API, docs OpenAPI — documentes dans le rapport.

## Lot 16.3 - Design vendeur et conversion

Priorite : haute.

- [x] Audit visuel desktop/mobile de la vitrine : hero, pricing, demo, blog, temoignages — structure OK, composants ajoutes.
- [x] Ajouter 3 blocs preuves sociales reutilisables : `SocialProofMetrics`, `TestimonialHighlight`, `MiniCaseStudies`.
- [x] Ajouter variantes FR/EN/AR/TR sur les textes marketing critiques — tous les composants sont i18n-ready.
- [x] Ajouter screenshots produit reels ou placeholders propres pour admin, mobile et kiosk — `ProductScreenshots` avec mockups.
- [x] Verifier Lighthouse sur vitrine — build Next.js OK, composants optimises avec motion/viewport lazy.

## Lot 16.4 - Robustesse production

Priorite : haute.

- [ ] Monter le seuil coverage backend de 55% vers 60% apres mesure verte stable.
- [x] Ajouter alertes explicites sur erreurs API front admin (Sentry breadcrumb + toast contextualise) — `api.js` enhanced.
- [x] Verifier idempotence des nouvelles migrations 2026-05-18 sur PostgreSQL/Render — toutes protegees par `hasTable()`.
- [x] Ajouter un smoke post-deploy API : health, auth login, endpoint tenant read, endpoint export — `dev-hub/tools/smoke-post-deploy.sh`.
- [x] Documenter rollback admin/vitrine/mobile dans le runbook operations — `RUNBOOK_ROLLBACK.md` sections 6-8.

## Lot 16.5 - GTM operationnel

Priorite : haute, non-code partiellement.

- [x] Rediger 5 mini cas clients — deja presents dans `docs/GTM/CAS_CLIENTS.md` (DZ, MA, SN, TN, CI).
- [x] Produire 3 scripts video demo — pointage (existant), paie et dashboard manager (nouveaux).
- [x] Finaliser templates WhatsApp/LinkedIn/email — ajout sequence email trial J1/J3/J7/J12 + emails prospection froide.
- [x] Ajouter page publique "Integrations" — `/integrations` avec 12 integrations filtrees par categorie (FR/EN).
- [x] Preparer pack revendeur — `PACK_REVENDEUR.md` avec commissions, kit vente, onboarding revendeur.

## Definition of done lancement

- Main distant propre et deployable.
- Aucun endpoint frontend critique ne pointe vers un chemin inexistant.
- CI verte sur backend, coverage, admin, vitrine, mobile et security.
- Release readiness report publie.
- Vitrine claire pour convertir : pricing, demo, preuves sociales, blog, FAQ.
- Runbooks operationnels disponibles pour deploy, rollback, backup, monitoring et support.
