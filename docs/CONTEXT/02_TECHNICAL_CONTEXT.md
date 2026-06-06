# Technical context

## Stack

- Backend: Laravel, PostgreSQL multi-tenant schema, Sanctum, queues.
- Mobile: Flutter 3.x, Riverpod 3.x, apps separees Employee/Manager/Platform Admin.
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

## Commandes utiles

- Mobile guard: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/validate-mobile-runtime-smoke.ps1`
- Platform admin guard: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/validate-mobile-plan29.ps1`
- Release readiness: `powershell -ExecutionPolicy Bypass -File dev-hub/tools/release-readiness.ps1 -Strict`
- PR checks: `gh pr checks <numero>`

## Limitations locales connues

La machine Windows peut ne pas avoir PHP/Flutter/Dart. Dans ce cas, GitHub Actions est la preuve de verite.

