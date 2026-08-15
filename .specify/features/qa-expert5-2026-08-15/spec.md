# Feature Specification: Session QA expert 5 — 2026-08-15 (vague tardive)

**Feature Branch**: `docs/qa-expert5-2026-08-15` + branches `fix/<issue>-*` par correctif

**Created**: 2026-08-15 | **Status**: Audit → Issues → Implémentation

**Input**: Mission du propriétaire — tester la plateforme dans tous les sens
(vitrine, web, admin, mobiles, workflows, APIs, logiques, onboarding, cohérence),
documenter chaque manquement selon la méthode Spec Kit, implémenter les
manquements puis le max d'issues ouvertes, merger le max de branches, main vert.

**Contexte**: Les vagues précédentes (#2 PR #3116, v3 PR #3160, expert 4 #3192)
ont couvert #2605→#3183. Cette session ajoute un audit frais sur `main` courant
(+50 commits pendant l'audit, 17 PRs mergées en parallèle) et dégage 22 nouveaux
constats non couverts : 6 API (dont 3 sécurité P1/P2), 9 vitrine, 6 admin, 1 mobile.

## User Stories

### US1 — Les licences Edge ne sont plus forgeables (P1 — API-1)
`POST /api/v1/edge-node/validate-license` (public) refuse les tokens non signés :
quand `EDGE_LICENSE_PUBLIC_KEY` est absent, la validation échoue (fail-closed) au
lieu de décoder sans vérification. Aucun fallback HS256 implicite.

### US2 — La config SSO OIDC n'est plus un vecteur SSRF (P2 — API-2)
`/sso/configure` est réservé au manager principal (garde `api.manager`) et
rejette les `token_url`/`jwks_uri`/`authorize_url` pointant vers des IP privées
(loopback, RFC1918, link-local, CGNAT, métadonnées cloud) — cohérent avec la
protection RTSP (#3147).

### US3 — L'émission de licence Edge exige un manager (P2 — API-3)
`POST /api/v1/edge/{nodeId}/license` est gardé par `api.manager` ; `valid_days`
est borné (1..3650) et validé par FormRequest.

### US4 — Le checkout vitrine ne crashe plus (P2 — WEB-1)
`/checkout?plan=<inconnu>` affiche une erreur propre (redirection vers
`/pricing` ou message) au lieu d'une page blanche TypeError.

### US5 — Les métriques vitrine sont honnêtes (P2 — WEB-2, WEB-3, WEB-4)
`/testimonials` et `/about` n'affichent plus de chiffres fabriqués (badge
« démo » ou chiffres réels) ; Enterprise cohérent (prix vs « Sur devis ») ;
CTA home pilot → parcours sans carte (cohérent avec /pricing).

### US6 — La console admin affiche le vrai contrat API (P2/P3 — ADM-1..ADM-4)
WebhooksView, EdgeNodesView, DashboardView, CompanyDetailView lisent les champs
réellement exposés par l'API (ou l'API les expose) — plus de colonnes fantômes,
plus de toggle inopérant, plus de sous-titres vides.

### US7 — Hygiène P3 (API-4..6, WEB-5..9, ADM-5..6, MOB-1)
RateLimiter dédupliqué, per_page borné (8 endpoints), contrat OpenAPI
/public-holidays corrigé, résidus FR signup, lien mort /offline, sitemap /share,
guides 2024 → 2026, pages FR-only signalées, CSV anti-injection complet, erreur
ChatView honnête, DateTime.parse sécurisé HR.

## Acceptance Scenarios
1. `POST /api/v1/edge-node/validate-license` sans clé publique configurée → 422 (token invalide) — test dédié.
2. `POST /api/v1/sso/configure` par un employé simple → 403 ; `token_url=169.254.169.254` → 422.
3. `POST /api/v1/edge/{id}/license` par un employé simple → 403 ; `valid_days=999999` → 422.
4. `GET /checkout?plan=bogus` → pas de TypeError ; redirection ou erreur propre.
5. `/testimonials` et `/about` : aucun nombre sans preuve (badge démo ou retrait).
6. Console admin : colonnes Webhooks/EdgeNodes/Dashboard/CompanyDetail alignées sur les payloads réels.
7. Gates : backend tests, PHPStan strict 0 erreur, ESLint/TS 0 erreur, actionlint pass.
