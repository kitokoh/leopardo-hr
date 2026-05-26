# Leopardo Platform Admin Mobile

Application Flutter reservee aux super-admins Leopardo RH. Elle administre la plateforme elle-meme, pas les workflows RH d'un tenant.

## Architecture

- Framework : Flutter 3.x
- State management : `flutter_riverpod` 3.3
- Package partage : `../leopardo_core`
- API cible : `/api/v1/platform/*`
- Identite Android/iOS : `com.leopardo.platformadmin`

## Perimetre Plan 29

- Connexion super-admin via `/platform/auth/login`
- Hydratation session via `/platform/auth/me`
- Deconnexion via `/platform/auth/logout`
- Cockpit metriques via `/platform/metrics/overview`
- Liste clients via `/platform/companies`
- Creation client via `POST /platform/companies`
- Demandes clients via `/platform/company-requests`

Cette app ne doit pas contenir de pointage, absences, avances, equipe, approvals RH ou autres workflows tenant.

## Demarrage

```bash
cd front/mobile_apps/leopardo_platform_admin
flutter pub get
flutter run --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
```

## Validation

```powershell
powershell -ExecutionPolicy Bypass -File ..\..\..\dev-hub\tools\validate-mobile-plan29.ps1
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
```
