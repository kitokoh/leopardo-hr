# Plan — #7553 / #7557 / #7554

## Backend (#7553)

1. `api/database/migrations/public/2026_09_16_000001_7553_add_platform_role_to_super_admins.php`
   — colonne additive `platform_role` (défaut `super_admin`), idempotente
   (`hasColumn`), CHECK PostgreSQL gardé par `pg_constraint`.
2. `api/app/Modules/Platform/Domain/Enums/PlatformPermission.php` — 18 permissions.
3. `api/app/Modules/Platform/Domain/Enums/PlatformRole.php` — 6 rôles + `permissions()`,
   `permissionValues()` (liste JSON garantie), `hasPermission()`, `canManageTeam()`, `matrix()`.
4. `api/app/Http/Middleware/EnsurePlatformPermissionMiddleware.php` + alias
   `platform.permission` dans `api/bootstrap/app.php`.
5. `api/app/Core/Tenant/Domain/Models/SuperAdmin.php` — `platformRole()`,
   `hasPlatformPermission()`, `isPlatformActive()` ; `platform_role` hors `$fillable`,
   ajouté à la garde `tests/Unit/SensitiveFillableGuardTest.php`.
6. `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PlatformTeamController.php`
   — index / store / updateRole / activate / deactivate + audit + garde-fous.
7. `api/routes/api.php` — groupe `team` (`team.manage`) + `platform.permission` sur
   49 routes sensibles des groupes `platform` et `admin`.
8. `api/app/Core/Auth/Interfaces/Api/V1/Controllers/PlatformAuthController.php` —
   `platform_role` + `permissions` dans login / me / updateProfile (3 occurrences).
9. `api/lang/{fr,en,ar,tr}/errors.php` — 4 messages (`PLATFORM_PERMISSION_REQUIRED`,
   `PLATFORM_ACCOUNT_REQUIRED`, `CANNOT_CHANGE_OWN_PLATFORM_ROLE`, `LAST_SUPER_ADMIN_REQUIRED`).
10. Tests : `api/tests/Feature/Platform/PlatformTeamApiTest.php` (11 cas).
11. Miroirs de schéma de test + `api/openapi.yaml` (5 paths, 4 schémas) + régénération
    SDK via `node dev-hub/tools/generate-openapi-sdk.mjs`.
12. Docs : `docs/security/PLATFORM_INTERNAL_ROLES.md` (nouveau),
    `docs/security/RBAC_ROUTE_MATRIX.md` (légende + ligne « Platform administration »).

## Console (#7557 puis #7554)

13. `src/stores/auth.js` — garde assouplie (compte plateforme actif), `platformRole`,
    `permissions`, `hasPermission()`.
14. `src/views/team/PlatformTeamView.vue` + route `/team`.
15. `src/navigation/navigation.js` — source de vérité unique ; `Sidebar.vue`,
    `CommandPalette.vue`, `useKeyboardShortcuts.js`, `Header.vue` la consomment.
16. Corrections #7554 : `showcase-editor`, comptabilité par permission, icônes,
    pages orphelines, i18n du bloc « Système », place réservée Travel, pied de sidebar.
17. i18n admin (4 locales) + specs Playwright (`e2e/platform-team.spec.js`).

## Vérification

- `php vendor/bin/phpunit --filter PlatformTeamApiTest` (nouvelle suite).
- Non-régression : `--filter 'Platform|KillSwitch|MetricsAccess|Impersonation'`.
- `php vendor/bin/phpstan analyse --configuration=phpstan-strict.neon` (0 erreur).
- `python3 dev-hub/tools/check-openapi-route-coverage.py --strict-staleness` (0 route non documentée).
- `bash dev-hub/tools/check-migration-basename-collisions.sh`.
- Console : `npx eslint .`, `VITE_API_URL=… npm run build`, `npx playwright test`.
- i18n : `node shared/i18n/sync/sync-web.js` + `node shared/i18n/validators/validate.js`
  (centralisés après fusion, `versions.json` régénéré, jamais arbitré à la main).
