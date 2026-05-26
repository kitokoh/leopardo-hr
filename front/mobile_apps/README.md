# Leopardo Mobile Apps

Ce dossier prepare la separation mobile en deux applications sans casser `front/mobile/`.

## Structure

- `leopardo_mobile_legacy/` : archive exacte du mobile historique. Ne pas modifier apres creation.
- `leopardo_core/` : package Flutter partage. Il contient uniquement les briques communes : API client, stockage, i18n, theme, couleurs, typographie, widgets de base, modeles et providers core.
- `leopardo_employee/` : app mobile employe. Elle expose les parcours personnels : connexion, accueil employe, pointage, absences, avances, paie, notifications, documents et compte.
- `leopardo_manager/` : app mobile manager/RH. Elle conserve le perimetre complet du mobile actuel et prepare les routes des futurs ecrans manager.

## Regles de contribution

- Toute modification partagee va dans `leopardo_core`.
- Toute modification d'ecran specifique va dans l'app concernee.
- L'app employe ne doit pas contenir de gestion d'equipe, validations manager, organigramme, approvals ou dashboard manager.
- L'app manager/RH conserve les ecrans complets et gere les differences internes via `employee.managerRole`.
- La differenciation par sous-role manager se fait dans les ecrans concernes, pas dans le router.
- `front/mobile/` reste le mobile historique fonctionnel tant que la bascule produit n'est pas terminee.
- `leopardo_mobile_legacy/` est un filet de securite : ne pas le modifier.

## Garde-fous Plan 26

Le script canonique de validation de structure est :

```bash
pwsh ./dev-hub/tools/validate-mobile-apps-split.ps1
```

Sur Windows sans PowerShell 7 :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1
```

Il verifie notamment :

- aucun dossier manager (`team`, `approvals`, `organigramme`, `modules`) dans `leopardo_employee` ;
- aucun marqueur `isManager`, `canManageTeam`, `managerRole`, `isPrincipal` ou `isHr` dans l'app employe ;
- aucun import `package:leopardo_rh` dans les nouvelles apps ;
- aucun import `leopardo_employee` ou `leopardo_manager` depuis `leopardo_core` ;
- dependance `leopardo_core` presente dans les deux apps ;
- routes manager preparees dans `leopardo_manager` ;
- en pull request, aucune modification de `leopardo_mobile_legacy`.
- identites App Store / Play Store distinctes pour `leopardo_employee` et `leopardo_manager` ;
- endpoints et routes critiques presents pour les workflows mobiles principaux ;
- absence de handlers UI vides sur les apps mobiles.

Si une evolution partagee est necessaire, la placer dans `leopardo_core`, puis consommer cette API depuis les deux apps. Si une evolution ne concerne qu'un persona, la placer uniquement dans `leopardo_employee` ou `leopardo_manager`.

## Identites store

| App | Android applicationId | iOS bundle id | Nom visible |
|---|---|---|---|
| `leopardo_employee` | `com.leopardo.employee` | `com.leopardo.employee` | Leopardo Employee |
| `leopardo_manager` | `com.leopardo.manager` | `com.leopardo.manager` | Leopardo Manager |

Avant un upload public, le mode strict doit passer apres configuration des signatures release :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1 -StrictStores
```

## Validation attendue

Depuis un SDK Flutter compatible Dart 3.8+ :

```bash
pwsh ./dev-hub/tools/validate-mobile-apps-split.ps1
pwsh ./dev-hub/tools/validate-mobile-release-readiness.ps1

cd front/mobile_apps/leopardo_core
flutter pub get
flutter analyze

cd ../leopardo_employee
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1

cd ../leopardo_manager
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
```
