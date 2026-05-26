# Mobile Firebase Distribution

Date : 2026-05-26

## Decision

Les apps mobiles sont maintenant separees comme des produits distincts :

| App | Android package | iOS bundle | Firebase Android secret attendu |
|---|---|---|---|
| Employee | `com.leopardo.employee` | `com.leopardo.employee` | `FIREBASE_EMPLOYEE_ANDROID_APP_ID` |
| Manager | `com.leopardo.manager` | `com.leopardo.manager` | `FIREBASE_MANAGER_ANDROID_APP_ID` |
| Platform Admin | `com.leopardo.platformadmin` | `com.leopardo.platformadmin` | `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` |

Le secret commun `FIREBASE_TOKEN` reste requis pour l'upload Firebase App Distribution. Le secret `FIREBASE_SERVICE_ACCOUNT_JSON` est recommande pour rendre le readback strict via service account.

## Fichiers Firebase attendus

Les fichiers natifs doivent correspondre exactement aux IDs ci-dessus :

- `front/mobile_apps/leopardo_employee/android/app/google-services.json`
- `front/mobile_apps/leopardo_employee/ios/Runner/GoogleService-Info.plist`
- `front/mobile_apps/leopardo_manager/android/app/google-services.json`
- `front/mobile_apps/leopardo_manager/ios/Runner/GoogleService-Info.plist`
- `front/mobile_apps/leopardo_platform_admin/android/app/google-services.json`
- `front/mobile_apps/leopardo_platform_admin/ios/Runner/GoogleService-Info.plist`

Le script d'installation refuse les fichiers qui ne correspondent pas :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\install-mobile-firebase-configs.ps1
```

## Etat des fichiers recus le 2026-05-26

Premier lot refuse :

- Android : `com.leopardo.emplyer` detecte, attendu `com.leopardo.employee` ou `com.leopardo.manager`.
- iOS employee : `com.leopardo.employer` detecte, attendu `com.leopardo.employee`.
- iOS manager : `com.leopardo.manage` detecte, attendu `com.leopardo.manager`.

Second lot installe :

- Employee Android : `google-services (3).json`, package `com.leopardo.employee` detecte.
- Manager Android : `google-services (4).json`, package `com.leopardo.manager` detecte.
- Employee iOS : `GoogleService-Info (3).plist`, bundle `com.leopardo.employee` detecte.
- Manager iOS : `GoogleService-Info (2).plist`, bundle `com.leopardo.manager` detecte.

Note Android : les exports Firebase peuvent contenir plusieurs clients dans un meme `google-services.json`. Le script choisit le fichier le plus specifique disponible pour chaque app, mais Gradle selectionne le client correspondant a `applicationId`. Toute cle API associee a un client historique doit rester restreinte cote Google Cloud/Firebase.

## Distribution CI

`Deploy - Leopardo RH` distribue maintenant les deux APK staging :

- `leopardo_employee` vers `FIREBASE_EMPLOYEE_ANDROID_APP_ID`
- `leopardo_manager` vers `FIREBASE_MANAGER_ANDROID_APP_ID`

`leopardo_platform_admin` est preparee cote code et CI debug. Sa distribution Firebase sera activee quand les apps Firebase `com.leopardo.platformadmin` Android/iOS et les secrets dedies seront fournis.

Sur le deploy `main`, la distribution d'une app est sautee proprement tant que son secret ou son `google-services.json` manque. Cela evite de bloquer le deploy API/web pendant la preparation Firebase.

Le workflow manuel `Mobile - Build and Firebase Distribution` accepte aussi le choix `employee`, `manager` ou `both`.

Depuis v4.16.149, les deux workflows relisent Firebase apres l'upload avec :

```bash
firebase appdistribution:releases:list --app <firebase-app-id> --limit 10 --json
```

Le job echoue si le `buildVersion` du build courant n'apparait pas dans App Distribution. Cela evite les faux positifs ou un upload semble vert cote GitHub mais reste invisible cote Firebase.

Derniere verification connue :

- Employee Android : release `main-1568 (1568)` visible dans `leopardo-rh` sous `android:com.leopardo.employee`.
- Manager Android : release `main-1568 (1568)` visible dans `leopardo-rh` sous `android:com.leopardo.manager`.

Important : Firebase App Distribution affiche les releases par app. Dans la console, selectionner le projet `leopardo-rh`, puis App Distribution, puis l'app Android `com.leopardo.employee` ou `com.leopardo.manager`. Les fichiers iOS sont installes dans le depot, mais la distribution iOS necessitera un workflow macOS signe produisant un `.ipa`.

## Securite

Les fichiers Firebase mobile ne sont pas des secrets forts, mais leurs API keys doivent etre restreintes dans Google Cloud/Firebase :

- restriction par package Android + SHA-1/SHA-256 ;
- restriction par bundle iOS ;
- App Check a activer avant lancement public ;
- groupes de testeurs limites dans Firebase App Distribution.
