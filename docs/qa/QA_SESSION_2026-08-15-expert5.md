# QA Leopardo HR — Session expert 5 (2026-08-15, tardive)

Mission : tester la plateforme dans tous les sens (vitrine, web, admin, mobile,
workflows, API, logique, onboarding, cohérence), documenter chaque manquement
selon la méthode Spec Kit (`.specify/`), créer les issues GitHub, puis
implémenter les correctifs et merger le maximum de branches — **main doit rester
vert**.

## Méthode

1. Audit **statique** ciblé sur `main` courant (4 agents parallèles : API Laravel,
   vitrine Next.js, console admin Vue, apps Flutter) avec dédup systématique
   contre les 112 issues ouvertes et les ~36 PRs ouvertes de la campagne.
2. Vérification **live** des surfaces déployées : API Render
   (`gestionemployerbackend.onrender.com`, v4.23.5 — stale), vitrine Vercel
   (`gestionemployer-backend.vercel.app` — blog 404 + changelog 4.16.x : déploiement
   en retard sur main, fixes #3014-#3020 non livrés), console admin Cloudflare
   Pages (`leo-admin.pages.dev`).
3. Re-vérification de chaque constat sur `origin/main` (le dépôt bougeait pendant
   l'audit : 50+ commits, 17 PRs mergées en cours de session).
4. Chaque constat confirmé → issue GitHub `[QA][P#][area]` + feature spec kit
   `.specify/features/qa-expert5-2026-08-15/` (spec/plan/tasks).
5. Implémentation des correctifs (PR par issue, `Closes #X`), merge des branches
   vertes, garde-fous : main vert.

## Constats confirmés (code, main courant)

### API (Laravel)
| ID | Sév. | Constat | Preuve |
|----|------|---------|--------|
| API-1 | P1 | Validation licence Edge **fail-open** : `decode()` saute la vérification de signature quand `EDGE_LICENSE_PUBLIC_KEY` est vide (défaut) ; endpoint **public** `POST /api/v1/edge-node/validate-license` → licences forgeables (features/expiration arbitraires) | `api/app/Modules/EdgeSync/Application/Services/EdgeLicenseService.php:117-135`, `api/config/edge.php:55-58`, `api/app/Modules/EdgeSync/routes/api.php:64` |
| API-2 | P2 | SSRF OIDC : `/sso/configure` (accessible à **tout employé** auth, aucun garde `api.manager` malgré le commentaire « manager principal only ») accepte `token_url`/`jwks_uri` avec validation `\|url` qui laisse passer IP privées → POST/GET serveur vers 169.254.169.254, loopback… | `api/app/Core/Auth/Interfaces/Api/V1/SSOController.php:60-72`, `api/routes/modules/sso.php:26-31`, `OidcFlowService.php:172`, `OidcIdTokenValidator.php:135` |
| API-3 | P2 | Émission licence Edge sans garde rôle : `POST /api/v1/edge/{nodeId}/license` sous `auth:sanctum`+tenant seul, `valid_days` non borné → tout employé du tenant s'auto-accorde une licence 10 000 j | `api/app/Modules/EdgeSync/routes/api.php:38-47`, `EdgeNodeController.php:99-106` |
| API-4 | P3 | `RateLimiter::for('trial-status')` défini 2× (`AppServiceProvider.php:186` vs `:226`) — la dernière inscription gagne, l'anti-scraping token+IP (#2621) est du code mort | `api/app/Providers/AppServiceProvider.php:186,226` |
| API-5 | P3 | `per_page` non borné sur 8 endpoints hors liste #3059 : TrainingController ×4 (`:33/121/159/185`), SelfServiceController myTrainings/myLoans (`:74/:113`), EmployeeLoanController (`:46`), WebhookController dead-letters (`:261`) | fichiers ci-dessus |
| API-6 | P3 | OpenAPI documente des routes tenant `/public-holidays` (« manager principal ») inexistantes dans le code (seules les routes `/admin` super-admin existent) → contrat fantôme 404 | `api/openapi.yaml:9327,9378` vs `api/routes/api.php:359-362` |

### Vitrine web (Next.js)
| ID | Sév. | Constat | Preuve |
|----|------|---------|--------|
| WEB-1 | P2 | `/checkout` → **page blanche** (TypeError) sur plan inconnu : fallback `'starter'` absent de `PLAN_CONFIG` → `PLAN_CONFIG['starter']` undefined ; aucun error boundary | `front/web/src/app/(landing)/checkout/page.tsx:1131-1133` |
| WEB-2 | P2 | Métriques fabriquées encore en ligne : `/testimonials` (500+ entreprises, 50K+ employés, 4.8/5) et `/about` (50K+ utilisateurs, 15 pays, 98 %) alors que `data/testimonials.ts` pose `TESTIMONIALS_ARE_DEMO=true` (home déjà corrigée) | `testimonials/page.tsx:64,72-74,165`, `about/page.tsx:70,133-135` |
| WEB-3 | P2 | Enterprise contradictoire : « Sur devis » (`pricing.ts:86-87`) vs 299 €/239 € au checkout + limites « 500+ » vs « 250+ » | `pricing.ts:86`, `checkout/page.tsx:98-99` |
| WEB-4 | P2 | Home `PricingSection` : CTA Pilot « Lancer un pilote gratuit » → `/checkout?plan=pilot` payant avec carte ; `/pricing` renvoie vers `/signup` — incohérence de funnel (résiduel #3013) | `front/web/src/modules/vitrine/components/PricingSection.tsx:26` |
| WEB-5 | P3 | Résidus FR dans SignupForm (« jours », « Se connecter » ×2) après migration i18n (#3031 fermée) | `signup/page.tsx:614,798,810` |
| WEB-6 | P3 | Page `/offline` : lien mort `http://leopardo.local` | `offline/page.tsx:28-29` |
| WEB-7 | P3 | Sitemap publie `/share` — route POST-only (`share_target` PWA) → GET 405 | `sitemap.ts:69`, `share/route.ts` |
| WEB-8 | P3 | Guides périmés « Checklist Paie 2024 » (2026) | `guides/checklist-paie/page.tsx:17,22,36` |
| WEB-9 | P3 | ~12 pages landing FR-only malgré sélecteur de langue (/docs, /videos, /employes, /documents, /comptabilite, /marketing, /mobile, /branding, /testimonials, /case-studies, /guides/*) — résiduel #2642 fermée ; #2605 ne couvre que about/careers/contact/faq | pages `(landing)/*` |

### Console admin (Vue)
| ID | Sév. | Constat | Preuve |
|----|------|---------|--------|
| ADM-1 | P2 | WebhooksView : colonnes `company_name/description/is_active/last_delivery_status` absentes de `WebhookEndpointResource` (id/url/events/active/failure_count) ; le form envoie `is_active`+`description` que le backend ignore → statut toujours « Inactif », toggle inopérant, édition silencieusement ignorée | `views/webhooks/WebhooksView.vue:43-48,84-97` vs `api/.../WebhookEndpointResource.php` |
| ADM-2 | P3 | EdgeNodesView : `node_id/silent_since/license_status/pending_records` absents du payload `listAllNodes` → ID vide, badge licence vide | `views/edge/EdgeNodesView.vue` vs `EdgeNodeController::listAllNodes` |
| ADM-3 | P3 | DashboardView : `item.company.slug` jamais renvoyé par `/platform/companies/health` → sous-titre vide (résiduel #3036) | `views/DashboardView.vue:88-89` vs `PlatformCompanyHealthService` |
| ADM-4 | P3 | CompanyDetailView : `health.company.created_at` absent du payload health → « Inscrit le » toujours « Non renseigné » | `views/CompanyDetailView.vue:306` |
| ADM-5 | P3 | Export CSV LeavesView : `escapeCsvCell` ne neutralise pas `=+-@` (anti-injection de formule) — incohérent avec UsersView #2700 / AnalyticsView #3045 | `views/leaves/LeavesView.vue:278-286` |
| ADM-6 | P3 | ChatView avale le 501 explicite `ADMIN_CHAT_UNAVAILABLE` du backend → erreur générique trompeuse | `views/chat/ChatView.vue:180-184` |

### Mobile (Flutter)
| ID | Sév. | Constat | Preuve |
|----|------|---------|--------|
| MOB-1 | P3 | HR `attendance_repository.dart:543` : `DateTime.parse` sans tryParse (résiduel #3157 — seul manager corrigé) | `leopardo_hr/lib/features/attendance/data/attendance_repository.dart:543-545` |

## Vérifié non re-signalé (couvert par issues/PRs ouvertes)
- PWA precache routes auth + tags sync (`#3029/#3028` — PRs #3206/#3221/#3212 ouvertes)
- Firebase placeholders mobiles (`#3152`), retries non-idempotents (`#3010/#3007` — PR #3216), marketing auth (`#3006`), onboarding HR typage (`#3003` — PR #3125), dead routes HR (`#3151` — mergé via #3213), notifications 405 (`#3005/#3167` — PRs #3217/#3209), trial_days incohérents (`#3056/#3164` — PRs #3218/#3229), plans backend vs vitrine (`#3163`), FAQ i18n (`#2605`), canonical homepage (`#3140/#3190/#3193`), guard admin /chat /training /webhooks (`#3142` — PR #3162), mojibake/accents, `DateTime?` manager, API drift OpenAPI (`#3061`), N+1 (`#3148`), QR fail-open (`#3060`), leave-balances (`#3055`), webhook bounce (`#3058`).
- Déploiement prod stale (vitrine 4.16.x, API 4.23.5) : connu (#2632), hors périmètre code.

## Issues créées
Listées dans `.specify/features/qa-expert5-2026-08-15/tasks.md` (T001-T022).

## Implémentation
Voir `.specify/features/qa-expert5-2026-08-15/plan.md` et `tasks.md`.
