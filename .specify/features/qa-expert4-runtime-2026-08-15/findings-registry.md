# Registre des manquements — QA Expert 4 — Runtime & Merge Campaign 2026-08-15

> Session experte du repo kitokoh/leopardo-hr (main mobile, 2026-08-15).
> Mission propriétaire : merger le max de branches vers `main` (main VERT),
> tester la plateforme dans tous les sens (vitrine, web, admin, mobiles,
> workflows, API, logiques, onboarding, cohérence), consigner chaque manquement
> selon la méthode Spec Kit (issue + spec/plan/tasks), puis implémenter les
> manquements et le max d'issues ouvertes.
> Méthode : merge campaign en direct (22→PRs), nettoyage file CI (84 runs
> orphelins/supersédés annulés), audits statiques (checkers repo), builds
> locaux (vitrine Next.js ✅, admin Vue ✅), black-box prod (API Render staging),
> vérification anti-doublon (#2400) sur chaque constat.

## ⚠️ Dé-duplication (protocole #2400)

Les vagues QA 2026-08-14/15 (agent, expert #2 PR #3116, expert v3 PR #3160)
couvrent déjà ~70 constats. Chaque ligne ci-dessous est marquée `NOUVEAU` ou
`DÉJÀ COUVERT (#XXXX)` après vérification des issues ouvertes ET des branches/PRs
en cours au moment de la rédaction. Seuls les `NOUVEAU` font l'objet d'issues.

## A. Validation effectuée (preuves)

- [x] **Merge campaign** : 19 PRs initiales + 4 branches orphelines (3 PRs créées
  #3124/#3125/#3126) → ~25 PRs mergées pendant la session (docs, fixes API/web/
  admin/mobile, CI). Doublons fermés avec renvoi : #2982 (14j + `.rej` commités),
  #3115 (accents, au profit de #3112 puis ré-ouverte par le swarm — #3112 fermée,
  #3115 canonique). Conflits résolus sur 8 branches (merge main + résolution
  manuelle i18n JSON, app.dart ×4, cockpit Vue ×2).
- [x] **File CI** : 247 runs queued au pic → `cancel-orphan-runs.sh --superseded`
  ×3 = **84 runs orphelins/supersédés annulés** (outil officiel issue #2413).
- [x] **Builds locaux** : `front/web` npm build ✅ (0 erreur, ~70 routes) ;
  `front/admin-dashboard` vite build ✅ (0 erreur, après fix #3123).
- [x] **Checkers repo** : OpenAPI route coverage **0 erreur** après merge #3121
  (21 gaps documentés) ; migrations 0 collision ; mobile manifest routes OK ;
  catalogue pays OK ; parité .env.example 272 clés OK ; 0 controller orphelin ;
  0 interface orpheline nouvelle ; mojibake vitrine 0 ; href="#" 0.
- [x] **Black-box staging** (gestionemployerbackend.onrender.com) : **API v4.23.5
  (main 4.24+)** → `/api/v1/supported-countries` 404, `/i18n/catalog/fr` 404,
  `/api/v1/demo-users` 404, `/api-explorer` **500**, `/api/v1/health` 200,
  `/docs` 200. Déploys `deploy-main.yml` / `deploy-staging.yml` bloqués en file
  (queued/cancelled) — cause racine : saturation file GitHub Actions (#2488).
- [x] **Implémentation session** : 7 issues corrigées en 4 PRs (#3175 admin
  cockpit #3034/#3036/#3037/#3038, #3177 API #3055, #3178 web #3022, #3184
  API #3058) + 2 issues fermées avec preuve code (#2697, #2699 — déjà corrigées
  sur main, pattern #2512).

## B. Constats NOUVEAUX de cette vague

| ID | Sév | Surface | Constat | Preuve | Issue |
|----|-----|---------|---------|--------|-------|
| F-E4-01 | P1 | ops | **Déploiement staging toujours stale** : API Render v4.23.5 vs main 4.24+ — /supported-countries, /i18n/catalog/fr, /demo-users 404 ; /api-explorer 500 ; auth/me 302. La file GitHub Actions saturée empêche les deploys de partir (deploy-main/staging queued puis cancelled). Couvert #2627/#2632/#2654 mais **toujours non résolu** — nécessite une fenêtre de déploiement dédiée (file libérée) + vérification post-deploy. | curl staging (14:40) ; runs deploy 14:18-14:39 queued/cancelled | **NOUVEAU (relance)** |
| F-E4-02 | P2 | web | **Défaut de canonical incohérent entre 2 sources** : `src/lib/site.ts` → `https://leopardo-rh.com` vs `src/lib/site-url.ts` → `https://www.leopardo-rh.com` — deux `getSiteUrl()` avec des défauts différents (canonicals/SEO potentiellement divergents selon le module importé). | `front/web/src/lib/site.ts:17`, `front/web/src/lib/site-url.ts:22` | **NOUVEAU** |
| F-E4-03 | P3 | admin | `stores/realtime.js:308` : fallback d'id `Date.now() + Math.random()` sur des événements temps réel — ids non persistants (trace/audit difficiles). | `front/admin-dashboard/src/stores/realtime.js:308` | **NOUVEAU** |
| F-E4-04 | P3 | repo | Branche `fix/qa-omnichannel-web-2026-08-15` périmée : contenu intégralement fusionné via #2891 ; un merge supprimerait des fichiers conservés par main. | `git log origin/main..origin/fix/qa-omnichannel-web-2026-08-15` (2 commits, 1450 suppressions vs main) | **NOUVEAU** |

## C. Décisions consignées (méthode Spec Kit)

| ID | Décision | Pourquoi | Trace |
|----|----------|----------|-------|
| D-E4-01 | **Durée d'essai vitrine = 14 jours** (unifiée, toutes locales) | Commit propriétaire 594c68f2 (« essai 14 jours »), PR #2944 (main), PR #3135 mergée en session (« essai unifié 14 jours », Closes #2909). Les issues #2909/#2721 demandaient « 30 jours » — décision propriétaire postérieure (594c68f2) prise en compte. **À entériner/confirmer par le propriétaire** (le token sera révoqué ; le rapport final signale la contradiction). | #3137 (registre swarm), PR #3135, commit 594c68f2 | 
| D-E4-02 | **PR dupliquées fermées avec renvoi** (#2982 → #2972 puis 14j, #3112/#3115 → canonique accents) ; branche périmée supprimée. Conformité protocole #2400. | 1 PR = 1 issue, éviter le gaspillage CI | commentaires #2982/#3115/#3112 |

## D. Constats DÉJÀ COUVERTS (vérifiés, trace)

| Constat (vérifié en session) | Issue/PR existante |
|------------------------------|--------------------|
| OpenAPI drift 21 routes (/admin/webhooks, impersonations, training, auth forgot/reset, notifications/read-all, departments hierarchy) | **RÉSOLU** par #3121 (vérifié : 0 erreur) |
| Main latent rouge PHPStan Core/Auth (isFuture, AccountLockedException, Socialite with) | #3130, **RÉSOLU** sur main (479b3c43 + 1a6d54b7) ; #3132 fermée sans merge |
| API staging stale / api-explorer 500 / demo-users | #2627, #2632, #2654 (toujours ouvertes — voir F-E4-01) |
| File GitHub Actions saturée | #2488, #2131 (mitigations : F-E4-01) |
| DZD en dur fallback mobile team_screen | #2741 (PR #3113 mergée) |
| Essai 14 vs 30 jours incohérent | #2909, #2721 (voir D-E4-01) |
| UserTable bouton Éditer mort | #2697 — **fermé avec preuve code** (déjà corrigé) |
| UsersView filtre « En attente » ignoré | #2699 — **fermé avec preuve code** (déjà corrigé) |
| `c.otp*` clés i18n brutes flux OTP | #3022 — **implémenté** (#3178) |
| /employees/{id}/leave-balances sans garde | #3055 — **implémenté** (#3177) |
| CompanyDetailView crash + widgets cockpit | #3034/#3036/#3037/#3038 — **implémentés** (#3175) |
| Secret webhook email-bounce absent | #3058 — **implémenté** (#3184) |
