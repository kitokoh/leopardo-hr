# QA Leopardo RH — Session expert #10 du 2026-08-15

Mission : audit 360° (vitrine, web app, admin, mobiles, workflows, API, logiques, onboarding, cohérence),
consignation Spec Kit (issues + spec/plan/tasks), maintien de main vert.

## Méthode
1. Recon : CI/PRs/issues/branches ; vérification anti-doublon systématique contre les 285+ issues ouvertes
   (vagues qa-expert → qa-expert9) avant toute création.
2. Tests live black-box : API Render (health, 404, login, CORS, headers), vitrine Vercel (pages, sitemap,
   robots, og:image, i18n ?lang=), admin leo-admin.pages.dev (headers).
3. Audit statique ciblé sur les surfaces NON couvertes par les vagues précédentes :
   kiosque ZKTeco (bridge Python), edge/ (stack on-premise), render.yaml/docker-compose, jobs Laravel,
   modèles (fillable), validation tenant, collection Postman.

## Constats consignés (16 issues, vague qa-expert10-2026-08-15)
| Issue | Sév | Surface | Résumé |
|---|---|---|---|
| #3586 | P1 | kiosk | Bridge HTTP sans auth — token + PII servis, punch forgeable |
| #3587 | P2 | kiosk | Perte silencieuse de pointages (mark_synced vs skips serveur) |
| #3588 | P2 | kiosk | Poison event bloque la file offline indéfiniment |
| #3590 | P3 | kiosk | config.json non gitignoré + drift apiBaseUrl + roster 15 s |
| #3591 | P1 | edge | Installation Edge cassée en prod (404 downloads + edge-ui non publiée) |
| #3592 | P2 | edge | Drift domaines + Caddyfile 502 + APP_KEY non persistée + healthcheck |
| #3593 | P2 | infra | CACHE_DRIVER obsolète compose + CACHE_STORE absent workers Render |
| #3594 | P3 | infra | Scheduler Render sans MAIL_* |
| #3595 | P2 | mobile | Settings repos : casts ['data'] as Map (6 sites, 3 apps) |
| #3596 | P3 | api | Allowlist OpenAPI : 29 entrées obsolètes à purger |
| #3597 | P3 | api | $fillable sensibles (défense en profondeur) |
| #3598 | P2 | api | N+1 training enrollments ×2 endpoints |
| #3599 | P3 | api | exists: non scopés tenant + FK sans exists + FormRequest morte |
| #3600 | P2 | api | Jobs sans tries/backoff/failed + ProvisionDemoTenantJob catch avalé |
| #3601 | P3 | web | CSP report-only vitrine+admin ; x-powered-by PHP exact |
| #3602 | P3 | tooling | Postman : login dupliqué |

## Anti-doublons vérifiés (non re-créés)
#3500 (marketing runApp + casts smart_attendance), #3433 (DateTime.parse core), #3525 (training/courses),
#3528 (APP_VERSION), #3529/#3530 (edge install.sh/entrypoint), #3250 (hreflang ?lang), #3520 (postman creds),
#3414 (allowlist 2 entrées POST/PUT), #3406 (casts — #3595 ne liste que les 6 sites résiduels).

## Specs créées
.specify/features/qa-expert10-{kiosk,edge,infra,mobile,api,web-tooling}-2026-08-15/ (spec+plan+tasks).

## Notes main vert
- Saturation Actions (768 runs queued, #2488/#2131/#3545) — merges soumis aux checks requis uniquement.
- Checks requis main : Backend Coverage, PHPStan Strict, Module Structure Validator, Frontend ESLint+TS, actionlint.
