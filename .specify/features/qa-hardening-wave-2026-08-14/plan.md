# Plan: Vague QA & Durcissement Plateforme 2026-08-14

**Input**: spec.md (US1-US5) + Constitution + constats reconnaissance 2026-08-14

## Architecture / Décisions techniques

- **US1 Backend** : la suite de tests complète (`php artisan test`) est exécutée localement (PHP 8.4 + PostgreSQL 16 + Redis 7, env identique CI). Tout échec est corrigé à la source (pas de skip). PHPStan strict + Pint en validation. Nouveaux tests Feature pour le module `user` (contrat register/login/me/logout/employee-links) en suivant le pattern des tests existants (RefreshDatabase + tenant). `BankExportGenerator` : IBAN/BIC résolus depuis la config tenant (`company.bank_iban`/`bank_bic` ou settings), refus explicite (ValidationException) si absents. Routes notifications : conserver `PUT /notifications/read-all` (convention rh.php), ajouter alias POST `mark-all-read` → même controller (compat clients), supprimer le doublon si aucune référence client.
- **US2 Web** : câbler les boutons du dashboard sur des routes réelles existantes (router Next) : actions rapides → `/employees/new`?, `/leave`, `/reports`, `/exports` selon l'existant ; cloche → panneau notifications coulissant ou route ; Leo IA → lien chat IA ; activité → route journal ; recherche → filtre client des cartes du dashboard (recherche locale, pas d'endpoint dédié). Détail bulletin (œil) → modal de détail avec les champs du payslip (réutiliser les données déjà chargées). Aucun nouveau composant lourd : rester sur le design system existant.
- **US3 Admin** : `AnalyticsView.vue` — écouter les événements `view-users`, `create-campaign`, `export-list`, `view-details`, `export-forecast`, `analyze-features`, `create-campaign` des widgets et implémenter les handlers (router.push vers les routes existantes : users, campaigns/marketing, exports). `CompanyDetailView` — « Accès Super-Console » → `router.push` vers la console (route existante `super-console` ou défaut). `GrowthDashboardView` — « Gérer » → navigation vers le détail partenaire (route existante ou toast explicite si pas de route). `EditUserModal` — « Changer l'avatar » → `<input type="file" accept="image/*">` déclenché au clic, prévisualisation, upload via le service users existant (ou désactivation explicite documentée si l'API n'existe pas).
- **US4 Mobile** : `user_auth_repository.dart` (3 apps) — remplacer la mutation `apiClient.dio.options.headers['Accept-Language']` par le passage de la langue dans les appels `requestWithRetry` (header par requête). `leopardo_marketing` — remplacer l'import `PrimaryButton` par `PulseButton` (widget existant dans core) ou pointer vers le vrai chemin ; vérifier la cohérence des autres imports.
- **US5 Traçabilité** : ouvrir des issues GitHub pour SSO (ref #1694), push FCM/APNs, magic link démo, drift OpenAPI (168 routes non documentées + 15 chemins fantômes — ne pas toucher openapi.yaml à cause des PR en cours), fériés placeholder PA2-COUNTRY-012 ; référencer la vague dans chaque issue.

## Fichiers touchés (référence)

- `api/tests/Feature/User/**` (nouveaux), `api/app/Modules/User/**` (si correctifs)
- `api/app/Modules/Payroll/Infrastructure/Services/BankExportGenerator.php` + tests
- `api/routes/modules/rh.php` / `dashboard.php` (notifications) + tests
- `front/web/src/app/(dashboard)/dashboard/page.tsx`, `front/web/src/app/(dashboard)/payroll/page.tsx`, `front/web/src/app/[companySlug]/careers/**`
- `front/admin-dashboard/src/views/analytics/AnalyticsView.vue`, `src/views/companies/CompanyDetailView.vue`, `src/views/growth/GrowthDashboardView.vue`, `src/components/users/EditUserModal.vue` + widgets
- `front/mobile_apps/*/lib/features/auth/data/user_auth_repository.dart` (3 apps), `front/mobile_apps/leopardo_marketing/lib/**`
- `CHANGELOG.md`, `.specify/features/qa-hardening-wave-2026-08-14/*`

## Contraintes

- **Ne pas toucher** aux zones des PR en cours : `openapi.yaml`/SDK (PR #2147/#2156), web compliance badge (PR #2157), CI saturation (PR #2159), TG onboarding (PR #2160).
- Multi-tenant inviolable : tout nouveau test isole par tenant (`company_id`), aucun accès cross-tenant.
- Un commit par tâche ou groupe logique ; PR unique pour la vague avec `Closes #<issue-vague>`.
- Validation finale : `php artisan test` + `phpstan-strict` + `pint --test` (api) ; `npm run build` + `npm run lint` (web, admin) ; grep patterns mobile.
