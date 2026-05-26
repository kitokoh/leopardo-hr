# Mobile Firebase Distribution

Date : 2026-05-26

## Decision

Les deux apps mobiles sont maintenant distribuees comme deux produits distincts :

| App | Android package | iOS bundle | Firebase Android secret attendu |
|---|---|---|---|
| Employee | `com.leopardo.employee` | `com.leopardo.employee` | `FIREBASE_EMPLOYEE_ANDROID_APP_ID` |
| Manager | `com.leopardo.manager` | `com.leopardo.manager` | `FIREBASE_MANAGER_ANDROID_APP_ID` |

Le secret commun `FIREBASE_TOKEN` reste requis pour Firebase App Distribution.

## Fichiers Firebase attendus

Les fichiers natifs doivent correspondre exactement aux IDs ci-dessus :

- `front/mobile_apps/leopardo_employee/android/app/google-services.json`
- `front/mobile_apps/leopardo_employee/ios/Runner/GoogleService-Info.plist`
- `front/mobile_apps/leopardo_manager/android/app/google-services.json`
- `front/mobile_apps/leopardo_manager/ios/Runner/GoogleService-Info.plist`

Le script d'installation refuse les fichiers qui ne correspondent pas :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\install-mobile-firebase-configs.ps1
```

## Etat des fichiers recus le 2026-05-26

Les fichiers telecharges ne doivent pas etre poses dans le depot en l'etat :

- Android : `com.leopardo.emplyer` detecte, attendu `com.leopardo.employee` ou `com.leopardo.manager`.
- iOS employee : `com.leopardo.employer` detecte, attendu `com.leopardo.employee`.
- iOS manager : `com.leopardo.manage` detecte, attendu `com.leopardo.manager`.

Il faut recreer ou renommer les apps Firebase avec les IDs exacts avant d'installer les fichiers.

## Distribution CI

`Deploy - Leopardo RH` distribue maintenant les deux APK staging :

- `leopardo_employee` vers `FIREBASE_EMPLOYEE_ANDROID_APP_ID`
- `leopardo_manager` vers `FIREBASE_MANAGER_ANDROID_APP_ID`

Sur le deploy `main`, la distribution d'une app est sautee proprement tant que son secret ou son `google-services.json` manque. Cela evite de bloquer le deploy API/web pendant la preparation Firebase.

Le workflow manuel `Mobile - Build and Firebase Distribution` accepte aussi le choix `employee`, `manager` ou `both`.

## Securite

Les fichiers Firebase mobile ne sont pas des secrets forts, mais leurs API keys doivent etre restreintes dans Google Cloud/Firebase :

- restriction par package Android + SHA-1/SHA-256 ;
- restriction par bundle iOS ;
- App Check a activer avant lancement public ;
- groupes de testeurs limites dans Firebase App Distribution.
