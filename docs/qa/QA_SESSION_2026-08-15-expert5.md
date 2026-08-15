# QA Leopardo RH — Session expert 5 du 2026-08-15 (audit complet + campagne merge)

Mission (propriétaire) : tester la plateforme dans tous les sens — vitrine, web app, admin,
mobiles, workflows, API, logiques, onboarding, cohérence — consigner chaque manquement selon la
méthode Spec Kit (issue + spec/plan/tasks), implémenter les correctifs, implémenter le maximum
d'issues ouvertes, merger le maximum de branches, garder `main` vert.

## Méthode

1. Revue statique par 5 agents en parallèle : API Laravel, vitrine/dashboard Next.js,
   admin Vue, apps Flutter (393 appels Dart cross-checkés vs 538 routes), cohérence docs/CI.
2. Tests live prod : API Render (gestionemployerbackend.onrender.com — v4.23.5, stale),
   vitrine Vercel (gestionemployer-backend.vercel.app), admin Cloudflare Pages (leo-admin.pages.dev).
3. Runtime local : PHP 8.4 + PostgreSQL 14 + Redis, composer install, migrations, tests ciblés
   (PasswordReset 6/6, Kiosk 7/7, RegisterLogin 3/3), PHPStan Strict 0 erreur sur fichiers modifiés,
   builds Next.js + Vite verts, lint 0.
4. Chaque constat vérifié → issue GitHub `[QA][P#][surface]` + feature spec kit
   `.specify/features/qa-expert5-2026-08-15/{spec,plan,tasks}.md`.
5. Implémentation par lots de surface (5 PRs), une entrée CHANGELOG par lot, `Closes #N` dans le body.

## Constats (41 manquements nouveaux → issues #3363-#3416)

### API (8)
| Issue | Sév. | Sujet | Statut |
|---|---|---|---|
| #3363 | P1 | Password reset KO pour tenants à schéma (search_path) | ✅ PR #3548 |
| #3364 | P2 | /auth/register : doublon employé + mauvais schéma → 401 permanent | ✅ PR #3548 |
| #3365 | P2 | QR punch kiosque : format incohérent + payloads non signés acceptés | ✅ PR #3548 |
| #3366 | P3 | RateLimiter trial-status enregistré 2× (clé token+IP écrasée) | ✅ PR #3548 |
| #3367 | P3 | Bucket kiosk-punch défini mais jamais monté (throttle:api partagé) | ✅ PR #3548 |
| #3368 | P3 | Handlers kiosque laissent le search_path sur le tenant (pas de try/finally) | ✅ PR #3548 |
| #3369 | P3 | syncTrips : plage non bornée + doublons en concurrence (pas d'index unique) | ✅ PR #3548 |
| #3370 | P3 | PasswordResetMail dupliqué (2 classes) + 2 fichiers de test | ✅ PR #3548 |

### Web vitrine/dashboard (11)
| Issue | Sév. | Sujet | Statut |
|---|---|---|---|
| #3372 | P2 | Checkout masque le surcoût/employé actif + sièges inclus | ✅ PR #3549 |
| #3373 | P2 | Home « pilote gratuit » → checkout payant (vs /pricing → signup) | ✅ PR #3549 |
| #3374 | P2 | Enterprise à 3 prix (checkout 299 / devis / sandbox 0) | ✅ PR #3549 |
| #3375 | P2 | robots.txt n'interdit pas les 13 prefixes racine protégés | ✅ PR #3549 |
| #3376 | P2 | sitemap annonce /blog (404 flag off), /share (405), /offline | ✅ PR #3549 |
| #3377 | P2 | Checkout + success 100 % FR (funnel de paiement entier) | 📋 reste (i18n) |
| #3378 | P2 | Pages dashboard métier FR-hardcodées malgré sélecteur de langue | 📋 reste (i18n) |
| #3379 | P2 | Gating rôle fail-open (clé absente → available) | ✅ PR #3549 |
| #3380 | P3 | Billing : upgrade self-service manual sans paiement | ✅ PR #3549 |
| #3381 | P3 | Footer : entrées /about + /videos mortes (index positionnel) | ✅ PR #3549 |
| #3382 | P3 | Portail carrières public 100 % FR | 📋 reste (i18n) |

### Admin console (8)
| Issue | Sév. | Sujet | Statut |
|---|---|---|---|
| #3388 | P1 | MarketingOAuthView : template string → page blanche (build runtime-only) | ✅ PR #3552 |
| #3389 | P2 | WebhooksView : is_active vs active (toggle jamais persisté) + événements hors sync | ✅ PR #3552 |
| #3390 | P2 | ChatView : composer mort (501 par conception) sans explication | ✅ PR #3552 |
| #3391 | P3 | realtime read-all POST → 405 | ✅ déjà sur main (fermé preuve code) |
| #3392 | P3 | VITE_WEBSOCKET_URL absent → ws://localhost:6001 en prod | ✅ PR #3552 |
| #3393 | P3 | Raccourci Alt+R obsolète | ✅ PR #3552 |
| #3394 | P3 | GrowthDashboard : affectation morte + fetch commissions jeté | ✅ PR #3552 |
| #3395 | P3 | ExportsView : fetchHistory sans catch (liste vide trompeuse) | ✅ PR #3552 |

### Mobile (7)
| Issue | Sév. | Sujet | Statut |
|---|---|---|---|
| #3400 | P2 | Manager : navigations vers routes non déclarées + garde manifeste rouge | ✅ déjà sur main (bcaadba9, fermé preuve code) |
| #3401 | P2 | read-all : 3 repos POST → 405 (canonique PUT) | ✅ PR #3553 |
| #3402 | P2 | HR : DateTime.parse non gardé sur requested_check_in | ✅ PR #3553 |
| #3403 | P3 | ai_voice transcribe/synthesize : 2 retries auto sur POST | ✅ PR #3553 |
| #3404 | P3 | Route /me/monthly employee « orpheline » | ✅ contrat manifeste (fermé) |
| #3405 | P3 | fr_FR hardcodé pour DateFormat (~25 écrans) | ✅ PR #3553 |
| #3406 | P3 | Casts directs response.data as Map (8 sites) | ✅ PR #3553 |

### Cohérence/docs/tooling (7)
| Issue | Sév. | Sujet | Statut |
|---|---|---|---|
| #3409 | P2 | CHANGELOG : historique release dupliqué (1650→1051 lignes) | ✅ PR #3554 |
| #3410 | P3 | /changelog vitrine : 5 versions fantômes 4.16.55-59 | ✅ PR #3549 |
| #3411 | P3 | FRONTEND_API_CONTRACT_MATRIX : 5 lignes orphelines | ✅ PR #3554 |
| #3412 | P3 | RBAC_ROUTE_MATRIX : famille Payroll dupliquée 4× | ✅ PR #3554 |
| #3413 | P3 | dev-hub tools : refs PLAN_ACTION2 archivées/inexistantes | ✅ PR #3554 |
| #3414 | P3 | openapi-coverage-allowlist : 2 entrées mortes (POST vs PUT) | ✅ PR #3554 |
| #3416 | P3 | web-offline : NEXT_PUBLIC_EDGE_API non documenté | ✅ PR #3554 |

+ drift nouveau : `GET /admin/training/courses` (route ajoutée par le swarm sans doc OpenAPI) documenté dans le lot cohérence.

## PRs de la vague

| PR | Lot | Issues |
|---|---|---|
| #3548 | API | #3363-#3370 (8/8) |
| #3549 | Web | #3372-#3381, #3410 (9) |
| #3552 | Admin | #3388-#3395 (7 + 1 déjà sur main) |
| #3553 | Mobile | #3401-#3406 + #3010 (6) |
| #3554 | Cohérence | #3409-#3416 (6) + spec kit |

## Campagne merge (main vert)

- ~50 PRs ouvertes au pic (swarm multi-agents) ; 108 runs CI orphelins annulés
  (`dev-hub/tools/cancel-orphan-runs.sh`, issue #2413) pour débloquer la file.
- Merge en cascade dès que les 5 checks requis (Backend Coverage, PHPStan Strict,
  Module Structure, Frontend ESLint+TS, actionlint) sont verts ; Vercel externe non bloquant.
- Issues fermées avec preuve code (procédure #2512) : #3391, #3400, #3251, #3404.

## Notes

- Pas de SDK Flutter dans le sandbox : les changements Dart sont validés par les gardes
  statiques (check-mobile-manifest-routes vert) + CI mobile (flutter analyze).
- Déploiements prod toujours périmés (API v4.23.5 vs main 4.24.0+) — incidents #2627/#2632/#2812
  à traiter côté ops (hors code).
- Le reste i18n (#3377/#3378/#3382) et les chantiers lourds (drift OpenAPI #2638/#3233,
  plans #3163, FCM #3152) sont documentés et prêts pour les prochaines vagues.
