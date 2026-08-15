# Feature Specification: Session QA Expert 4 — Runtime, Merge Campaign & Cohérence (2026-08-15)

**Feature Branch**: `qa-expert4-runtime-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission propriétaire (2026-08-15) — merger le max de branches vers
`main` (main vert), tester la plateforme dans tous les sens, consigner chaque
manquement selon la méthode Spec Kit, implémenter les manquements et le max
d'issues ouvertes. Constats dans `findings-registry.md` (2 nouveaux + 2 décisions +
relance P1 déploiement). Les manquements déjà couverts par les vagues 2026-08-14/15
(agent, expert #2, expert v3) ne sont pas dupliqués.

## User Scenarios & Testing

### User Story 1 — Le déploiement staging redevient à jour de main (Priority: P1)

L'API Render staging sert v4.23.5 alors que main est 4.24+ : `/api/v1/supported-countries`
404, `/i18n/catalog/fr` 404, `/api/v1/demo-users` 404, `/api-explorer` 500. Les
workflows `deploy-main.yml` et `deploy-staging.yml` restent `queued`/`cancelled`
derrière la saturation de la file GitHub Actions (#2488). La file doit être
désengorgée (annulation des runs orphelins/supersédés — fait : 84 annulations) et
un déploiement de main doit aboutir, suivi d'un smoke post-deploy.

**Acceptance Scenarios**:
1. **Given** un merge sur main, **When** la file CI est désengorgée, **Then** `deploy-staging.yml` termine en `success`.
2. **Given** le déploiement terminé, **When** on interroge l'API staging, **Then** `/health` rapporte une version ≥ main, `/api/v1/supported-countries` = 200, `/i18n/catalog/fr` = 200, `/api-explorer` = 200, `/api/v1/demo-users` = 200 (démo activée) ou 404 intentionnel (gate démo).
3. **Given** `/api/v1/auth/me` sans token avec `Accept: application/json`, **Then** réponse 401 JSON (jamais 302 HTML).

### User Story 2 — Les canonicals vitrine utilisent une source unique (Priority: P2)

`front/web/src/lib/site.ts` définit `DEFAULT_SITE_URL = https://leopardo-rh.com`
tandis que `front/web/src/lib/site-url.ts` définit `BRAND_SITE_URL =
https://www.leopardo-rh.com` : deux fonctions `getSiteUrl()` aux défauts
divergents. Les canonicals / SEO / données structurées peuvent pointer sur des
domaines différents selon le module importé.

**Acceptance Scenarios**:
1. **Given** n'importe quelle page vitrine, **When** on inspecte son canonical, **Then** il provient d'une seule source de vérité (même domaine de marque par défaut).
2. **Given** le code, **When** on recherche les défauts d'URL de site, **Then** un seul `DEFAULT_SITE_URL`/`BRAND_SITE_URL` (l'autre fichier importe le premier ou partage la constante).

### User Story 3 — Nettoyage et gouvernance des branches (Priority: P3)

La branche `fix/qa-omnichannel-web-2026-08-15` est périmée (contenu déjà fusionné
via #2891) et `stores/realtime.js` génère des ids non persistants
(`Date.now() + Math.random()`).

**Acceptance Scenarios**:
1. **Given** la branche périmée, **When** on vérifie `git log origin/main..origin/<branche>`, **Then** aucun commit utile non fusionné ; la branche est supprimée.
2. **Given** realtime.js, **When** un événement arrive sans id, **Then** l'id de repli est déterministe/stable pour la trace (ex. horodatage seul ou `crypto.randomUUID` documenté).

## User Story 4 — Décisions consignées (délivrable processus)

La durée d'essai vitrine est unifiée sur **14 jours** (décision propriétaire
594c68f2, PRs #2944/#3135) malgré le texte initial des issues #2909/#2721
(« 30 jours ») : la décision est consignée dans le registre pour arbitrage
propriétaire. Les PRs dupliquées sont fermées avec renvoi (protocole #2400).
