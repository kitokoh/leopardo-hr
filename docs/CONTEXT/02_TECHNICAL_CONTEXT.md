# Technical context

## Stack

- Backend: Laravel, PostgreSQL multi-tenant schema, Sanctum, queues.
- Mobile: Flutter 3.x, Riverpod 3.x, apps separees Employee/Manager/HR/Platform Admin (`front/mobile_apps/leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`), plus le package partage `leopardo_core`.
- Push: Firebase/FCM, mais aucune dependance externe ne doit bloquer le premier ecran.
- CI/CD: GitHub Actions source de verite.
- Deploiement: Render, Vercel, Firebase App Distribution.

## Invariants critiques

- Ne jamais bloquer `runApp()` sur Firebase, FCM, Hive, Google Sign-In, API ou reseau.
- `StartupGate` doit rester le premier garde anti-ecran noir.
- `PushNotificationService` doit rester lazy: pas de `FirebaseMessaging.instance` avant init Firebase protegee.
- Les routes tenant doivent rester scopees par tenant/company.
- Ne pas reutiliser les routes tenant `/device-tokens` pour le super-admin platform.
- `api/openapi.yaml` est la spec canonique.
- Mettre a jour `CHANGELOG.md` et `AGENTS.md` pour tout changement comportemental.

## Invariants architecture backend (mis a jour 2026-07-19)

- `App\Http\Controllers\Api\V1\*` **supprime** (PR #824, 2026-07-01) - tout nouveau controller dans `App\Modules\<Module>\Interfaces\Api\V1\`
- `App\Services\*` **n'est pas vide** : services legacy dupliques supprimes, mais des services specialises non-DDD restent (`Cache/`, `Communication/`, `Payroll/`, `SSO/`, `Security/`, `Tracking/`, etc.) + le shim `TenantManager.php` - tout nouveau service metier dans `App\Modules\<Module>\Infrastructure\Services\`
- `App\Models\*` **supprime**, migration terminee - ne pas y ajouter de nouveau modele ; tout modele vit dans `App\Modules\<Module>\Domain\Models\` ou `App\Core\Tenant\Domain\Models\`
- 19 modules actifs sous `api/app/Modules/` (voir `find api/app/Modules -maxdepth 1 -mindepth 1 -type d` pour la liste a jour)
- Source de verite architecture : `api/ARCHITECTURE.md`

## Commandes utiles

- Mobile guard: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/validate-mobile-runtime-smoke.ps1`
- Platform admin guard: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/validate-mobile-plan29.ps1`
- Release readiness: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict`
- PR checks: `gh pr checks <numero>`

## Limitations locales connues

La machine Windows peut ne pas avoir PHP/Flutter/Dart. Dans ce cas, GitHub Actions est la preuve de verite.

