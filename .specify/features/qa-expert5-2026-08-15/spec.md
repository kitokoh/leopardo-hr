# Feature Specification: Vague QA Expert 5 — 2026-08-15 (audit complet + campagne merge)

**Feature Branch**: `fix/qa-expert5-*` (une branche par issue)
**Created**: 2026-08-15
**Status**: Draft → In Progress
**Input**: Mission propriétaire — tester la plateforme dans tous les sens (vitrine, web app, admin,
mobiles, workflows, API, logiques, onboarding, cohérence), consigner chaque manquement selon la
méthode Spec Kit (issue + spec/plan/tasks), puis implémenter les correctifs, implémenter le maximum
d'issues ouvertes, merger le maximum de branches, et garder `main` vert.

Contexte : un swarm d'agents travaille en parallèle (~180 issues ouvertes avant cette session,
~45 PRs ouvertes). Règle anti-doublon (#2400) appliquée : chaque finding vérifié contre les issues
ET branches existantes avant création. Ce spec couvre les manquements **nouveaux** (non couverts).

## User Stories

### US1 — L'auth (login, register, reset) fonctionne pour TOUS les tenants, y compris à schéma (P1)
**Problème** : `PasswordResetController` résout `Employee` sans middleware tenant → jamais trouvé
pour les tenants à schéma (forgot() muet, reset() 422). `/auth/register` crée dans le mauvais
schéma et son unique appelant mobile n'envoie jamais `invitation_token`.

**Independent Test** : test Feature sur tenant à schéma : forgot → token créé ; reset → 200 ;
register avec invitation → employé mis à jour dans le bon schéma, login OK.

### US2 — Le pointage QR kiosque utilise un format signé unique, cohérent avec l'émetteur (P2)
**Problème** : `/kiosks/{deviceCode}/qr-punch` accepte un JSON base64 forgeable et ne sait pas
parsé le format `base64url(payload).signature` produit par `/me/qr-profile` → 404.

**Independent Test** : QR généré par `/me/qr-profile` → `qr-punch` 200 ; payload forgé → 4xx.

### US3 — L'admin console est entièrement fonctionnelle (P1/P2)
**Problème** : MarketingOAuthView (template string → page blanche), WebhooksView (is_active vs
active, événements hors sync), ChatView (composer 501 sans explication), realtime read-all 405,
VITE_WEBSOCKET_URL absent en prod, raccourci Alt+R obsolète, GrowthDashboard code mort,
ExportsView sans catch.

**Independent Test** : lint+build admin verts ; navigation `/marketing/oauth` rend les cartes ;
webhooks save 200 ; read-all 200.

### US4 — La vitrine et le dashboard client sont cohérents et localisés (P2)
**Problème** : checkout masque le surcoût/employé actif, CTA « pilote gratuit » → checkout payant,
Enterprise à 3 prix, robots/sitemap annoncent des routes protégées/404, checkout+dashboard FR-only,
gating rôle fail-open, upgrade billing sans paiement, footer liens morts, portail carrières FR-only.

**Independent Test** : build Next.js vert ; robots.txt couvre les 13 prefixes ; sitemap sans /blog
quand flag off ; checkout affiche le surcoût ; Playwright marketing vert.

### US5 — Les apps mobiles ne naviguent jamais vers des routes inexistantes (P2)
**Problème** : manager pousse vers `/tasks`, `/team`, `/me/monthly` non déclarés ; garde
`check-mobile-manifest-routes.sh` rouge sur main (16 incohérences) ; read-all 405 ; DateTime.parse
HR non gardé ; retries sur POST ai_voice ; route employee orpheline ; fr_FR hardcodé ; casts directs.

**Independent Test** : `check-mobile-manifest-routes.sh` vert ; `flutter analyze` vert (CI) ; 0 push
vers route non déclarée (scan statique).

### US6 — La doc et l'outillage sont alignés sur le code (P2/P3)
**Problème** : CHANGELOG dupliqué (450 lignes), versions fantômes /changelog, refs PLAN_ACTION2
archivées, matrices avec doublons/orphelins, allowlist OpenAPI morte, env var non documentée.

**Independent Test** : gardes du repo verts (`check-openapi-route-coverage.py`, parity env,
i18n-diff) ; CHANGELOG sans doublon (diff des plages).

## Acceptance Scenarios
1. **Given** les issues #3363-#3416, **When** chaque PR est mergée avec `Closes #N`, **Then** les
   issues se ferment avec le code sur main.
2. **Given** main, **When** les checks CI requis tournent, **Then** ils sont verts (Backend Coverage,
   PHPStan Strict, Module Structure, ESLint+TS, actionlint).
3. **Given** la garde mobile manifeste, **When** elle s'exécute sur main, **Then** exit 0.
4. **Given** le CHANGELOG, **When** on compare les plages dupliquées, **Then** diff vide.
