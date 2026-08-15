# Plan — Session QA expert 5 (2026-08-15, vague tardive)

## Objectifs
1. Documenter 22 nouveaux constats (issues + spec kit).
2. Implémenter les correctifs P1/P2 puis P3 — 1 branche `fix/<issue>-<slug>` par issue (protocole #2400).
3. Merger le max de branches vertes ; main doit rester vert.

## Architecture des correctifs

### API (api/)
| Constat | Correctif | Fichiers |
|---------|-----------|----------|
| API-1 (P1) fail-open licence Edge | `decode()` lève une exception si `license_public_key` absent → `validateLicense` retourne 422 ; test dédié sans clé | `EdgeLicenseService.php`, `EdgeNodeController.php`, test `EdgeLicenseValidationTest` |
| API-2 (P2) SSRF OIDC + garde rôle | middleware `api.manager` sur le groupe `/sso` (configure/disable/status) ; validation `SsoConfigureRequest` avec blocage IP privées (réutiliser le helper anti-SSRF de #3147) ; tests 403 + 422 | `routes/modules/sso.php`, `SSOController.php`, FormRequest dédié, `PrivateIpGuard` (shared) |
| API-3 (P2) licence Edge rôle + bornes | `api.manager` sur `edge/{nodeId}/license` + `valid_days` 1..3650 via FormRequest ; tests 403/422 | `routes/api.php` (module EdgeSync), `EdgeNodeController`, FormRequest |
| API-4 (P3) RateLimiter dupliqué | supprimer la 2e définition (garde IP seule) ; garder token+IP | `AppServiceProvider.php` |
| API-5 (P3) per_page | `min(100, ...)` sur les 8 endpoints (Training ×4, SelfService ×2, EmployeeLoan, Webhook dead-letters) — aligné #3059 | 4 contrôleurs |
| API-6 (P3) OpenAPI /public-holidays | supprimer les chemins tenant fantômes de `openapi.yaml` (garder `/admin/*`) | `openapi.yaml` |

### Vitrine (front/web/)
| Constat | Correctif | Fichiers |
|---------|-----------|----------|
| WEB-1 (P2) checkout crash | fallback `PLAN_CONFIG[plan] ?? free` + redirection propre si inconnu + error boundary | `checkout/page.tsx` |
| WEB-2 (P2) métriques fabriquées | badge « Chiffres de démonstration » sur /testimonials + /about (pattern home) ou chiffres réalistes sourcés | `testimonials/page.tsx`, `about/page.tsx` |
| WEB-3 (P2) Enterprise incohérent | harmoniser « Sur devis » partout ou prix partout (aligner `pricing.ts` ↔ checkout) | `pricing.ts`, `checkout/page.tsx` |
| WEB-4 (P2) CTA home pilot | CTA → `/signup?source=home_pilot` (comme /pricing) au lieu de checkout payant | `PricingSection.tsx` |
| WEB-5 (P3) résidus FR signup | catalogues `signup.*` — « jours », « Se connecter » | `signup/page.tsx`, catalogues i18n |
| WEB-6 (P3) lien mort /offline | retirer le lien `leopardo.local` | `offline/page.tsx` |
| WEB-7 (P3) sitemap /share | retirer `/share` du sitemap (POST-only) | `sitemap.ts` |
| WEB-8 (P3) guides 2024 | mettre à jour « Checklist Paie 2025/2026 » + liens | `guides/checklist-paie/page.tsx` |
| WEB-9 (P3) pages FR-only | issue de suivi (travail i18n séparé, aligné #2605) | — |

### Admin (front/admin-dashboard/)
| Constat | Correctif | Fichiers |
|---------|-----------|----------|
| ADM-1 (P2) WebhooksView contract | aligner colonnes/form sur `WebhookEndpointResource` (active/failure_count) | `WebhooksView.vue` |
| ADM-2 (P3) EdgeNodesView phantom | aligner sur payload `listAllNodes` (id, is_online, license_valid, company_name) | `EdgeNodesView.vue` |
| ADM-3 (P3) DashboardView slug | ajouter `slug` au payload health OU retirer la ligne | `PlatformCompanyHealthService.php` ou `DashboardView.vue` |
| ADM-4 (P3) CompanyDetailView created_at | exposer `created_at` dans le payload health | `PlatformCompanyHealthService.php` |
| ADM-5 (P3) CSV injection | `escapeCsvCell` neutralise `=+-@\t\r` | `LeavesView.vue` (+ Payroll si présent) |
| ADM-6 (P3) ChatView 501 | afficher le message backend (`ADMIN_CHAT_UNAVAILABLE`) au lieu d'erreur générique | `ChatView.vue` |

### Mobile (front/mobile_apps/)
| Constat | Correctif | Fichiers |
|---------|-----------|----------|
| MOB-1 (P3) DateTime.parse HR | `DateTime.tryParse` + fallback (aligné manager #3157) | `leopardo_hr/.../attendance_repository.dart:543` |

## Validation
- Backend : `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` (0 erreur), `vendor/bin/pint --test`, tests ciblés.
- Web/admin : `npm run lint` + `tsc`/build.
- Mobile : `flutter analyze` (leopardo_hr).
- Merge : vérifier branches existantes avant de créer (`gh api branches | grep <issue>`), PR avec `Closes #X`, CHANGELOG sous [Unreleased].

## Risques
- CI saturée (campagne multi-agents) → gates locales prioritaires.
- Conflits de merge fréquents (main bouge) → rebaser systématiquement avant PR.
- Ne pas dupliquer les PRs existantes (protocole #2400) : vérifier branches/PRs avant chaque issue.

---

<!-- Version antérieure de la même session (fusionnée via #3300) — conservée pour traçabilité -->

# Plan: QA Expert #5 — Test exhaustif plateforme (2026-08-15)

**Input**: spec.md

## Stratégie

1. **Audit** (terminé) : 4 subagents experts (api/web/admin/mobile) + tests live HTTP (Render/Vercel/CF Pages). 62 nouveaux constats, tous dédupliqués contre les issues/branches existantes (#2400).
2. **Tickets** (terminé) : 62 issues GitHub créées (#3231-#3294), label `qa-expert5-2026-08-15`, format `[QA][Px][surface]`.
3. **Implémentation** : par vagues P1 → P2 → P3, chaque correctif en branche `fix/<issue>-<slug>` + PR avec `Closes #N` + CHANGELOG. Priorité aux P1 sécurité (IDOR, SSRF, messages bruts) et aux parcours mobiles cassés (#3282, #3283).
4. **En parallèle** : campagne de merge des 22 PRs ouvertes (dont les waves QA précédentes) — vérifier les checks, merger les vertes, réparer/fermer les rouges (#3111, #3125, #3128).
5. **Garde** : main doit rester vert (5 checks requis) ; vérifier les runs post-merge avant d'annoncer.

## Ordre d'exécution conseillé

| Vague | Contenu | Issues |
|---|---|---|
| 0 | Merge campaign PRs vertes + réparation des PRs cassées | — |
| 1 | P1 API (company_id, policy, per_page, messages bruts) | #3231 #3232 #3234 #3235 |
| 2 | P1 mobile (GoRoutes manager, 405 read-all) | #3282 #3283 |
| 3 | P1 admin (WebhooksView, UsersView, realtime, i18n) | #3267 #3268 #3269 #3270 |
| 4 | P1 vitrine (preuve sociale, tarifs, FR-only) | #3246 #3247 #3248 |
| 5 | P2 quick wins (throttle, Log, FR API, sitemap, ancres…) | #3236 #3237 #3241 #3252 #3253 #3255… |
| 6 | P3 cleanup | restantes |

## Risques

- **CI saturée** : 22 PRs × ~20 workflows — merger par vagues, prioriser les vertes, éviter de créer des PRs concurrentes sur les mêmes fichiers.
- **Régression par branches périmées** (leçon 2026-08-15) : avant merge d'une vieille branche, vérifier `git diff origin/main...HEAD` sur les fichiers partagés.
- **Anti-doublon** : un seul `fix/<issue>-*` par issue ; vérifier les branches avant de coder.
